<?php
declare(strict_types=1);

/**
 * 結合テスト：HTTP 層（ビルトインサーバで起動し、ルーティング・認証・CSRF を検証）
 */
final class HttpSmokeTest extends PHPUnit\Framework\TestCase
{
    private static $proc = null;
    private static string $port = '23000';
    private static string $base = '';
    private static string $logFile = '';

    public static function setUpBeforeClass(): void
    {
        // テスト専用DB + デバッグON でビルトインサーバを起動
        $http_db = sys_get_temp_dir() . '/zaiko_http_' . getmypid() . '.db';
        @unlink($http_db);
        self::$logFile = sys_get_temp_dir() . '/zaiko_http_' . getmypid() . '.log';
        @unlink(self::$logFile);

        $port = random_int(23100, 29000);
        self::$port = (string)$port;
        self::$base = "http://127.0.0.1:{$port}";

        $cmd = [PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', dirname(__DIR__, 2)];
        $env = [
            'PATH' => getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'ZAIKO_DB_PATH' => $http_db,
            'ZAIKO_DEBUG' => '1',
        ];
        self::$proc = proc_open(
            $cmd,
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', self::$logFile, 'w']],
            $pipes,
            null,
            $env
        );

        // 起動待ち
        $deadline = microtime(true) + 10;
        while (microtime(true) < $deadline) {
            $ctx = stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]]);
            $body = @file_get_contents(self::$base . '/index.php', false, $ctx);
            if ($body !== false) {
                usleep(200000);
                break;
            }
            usleep(100000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$proc)) {
            proc_terminate(self::$proc);
            proc_close(self::$proc);
        }
        @unlink(self::$logFile);
    }

    /** HTTPリクエスト。戻り: [status, body, cookies, location] */
    private function request(string $url, ?array $post = null, array $cookies = []): array
    {
        $header = "User-Agent: phpunit-smoke\r\n";
        if ($cookies) {
            $header .= 'Cookie: ' . implode('; ', array_map(
                fn($k, $v) => $k . '=' . $v,
                array_keys($cookies),
                array_values($cookies)
            )) . "\r\n";
        }
        $opts = [
            'http' => [
                'method' => $post === null ? 'GET' : 'POST',
                'header' => $header,
                'ignore_errors' => true,
                'follow_location' => false,
                'timeout' => 5,
            ],
        ];
        if ($post !== null) {
            $opts['http']['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $opts['http']['content'] = http_build_query($post);
        }
        $ctx = stream_context_create($opts);

        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            return [0, '', $cookies, ''];
        }

        $status = 0;
        $location = '';
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
                $status = (int)$m[1];
            } elseif (preg_match('/^Location:\s*(.+)$/i', $line, $m)) {
                $location = trim($m[1]);
            } elseif (preg_match('/^Set-Cookie:\s*([^=;]+)=([^;]*)/i', $line, $m)) {
                $cookies[$m[1]] = $m[2];
            }
        }
        return [$status, $body, $cookies, $location];
    }

    private function extractCsrf(string $html): string
    {
        if (preg_match('/name="csrf" value="([a-f0-9]+)"/', $html, $m)) {
            return $m[1];
        }
        return '';
    }

    public function testUnauthenticatedRedirectsToLogin(): void
    {
        [$status, , , $location] = $this->request(self::$base . '/index.php');
        $this->assertSame(302, $status);
        $this->assertStringContainsString('p=login', $location);
    }

    public function testLoginPageRenders(): void
    {
        [$status, $body] = $this->request(self::$base . '/index.php?p=login');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('担当者選択', $body);
        $this->assertStringContainsString('name="担当者"', $body);
        $this->assertNotEmpty($this->extractCsrf($body));
    }

    public function testCsrfIsRequired(): void
    {
        [$status, , $cookies] = $this->request(self::$base . '/index.php?p=login');
        $csrf = $this->extractCsrf($this->request(self::$base . '/index.php?p=login')[1]);
        // トークンなしPOST
        [$status2] = $this->request(self::$base . '/index.php?p=login', ['担当者' => '担当A'], $cookies);
        $this->assertSame(403, $status2);
        // 不正トークンPOST
        [$status3] = $this->request(self::$base . '/index.php?p=login', ['csrf' => str_repeat('0', 64), '担当者' => '担当A'], $cookies);
        $this->assertSame(403, $status3);
        $this->assertNotEmpty($csrf);
    }

    public function testLoginThenDashboardAndLogout(): void
    {
        // ログイン
        [$s1, $body1, $cookies] = $this->request(self::$base . '/index.php?p=login');
        $csrf = $this->extractCsrf($body1);
        [$s2, , $cookies, $loc] = $this->request(self::$base . '/index.php?p=login', ['csrf' => $csrf, '担当者' => '担当B'], $cookies);
        $this->assertSame(302, $s2);
        $this->assertStringContainsString('p=dashboard', $loc);

        // ダッシュボード
        [$s3, $body3] = $this->request(self::$base . '/index.php?p=dashboard', null, $cookies);
        $this->assertSame(200, $s3);
        $this->assertStringContainsString('担当B', $body3);
        $this->assertStringContainsString('ダッシュボード', $body3);

        // 各ページが描画される
        foreach (['items', 'draft', 'orders', 'receive', 'inbound', 'outbound', 'monthly', 'logs'] as $p) {
            [$st] = $this->request(self::$base . "/index.php?p={$p}", null, $cookies);
            $this->assertSame(200, $st, "ページ {$p} が200で返らない");
        }

        // ログアウト → 未認証へ
        [$s4, , $cookies] = $this->request(self::$base . '/index.php?p=logout', null, $cookies);
        $this->assertSame(302, $s4);
        [$s5] = $this->request(self::$base . '/index.php?p=dashboard', null, $cookies);
        $this->assertSame(302, $s5); // 再びログインへ
    }

    public function testInvalidPageReturns404(): void
    {
        // 認証状態でないとルーティング前にログインへ遷移するため、ログイン済みで確認する
        [, , $cookies] = $this->loginAs('担当A');
        [$status] = $this->request(self::$base . '/index.php?p=no_such_page', null, $cookies);
        $this->assertSame(404, $status);
    }

    /** ログインしてセッションCookieを返す */
    private function loginAs(string $name): array
    {
        [$s1, $body1, $cookies] = $this->request(self::$base . '/index.php?p=login');
        $csrf = $this->extractCsrf($body1);
        [$s2, , $cookies] = $this->request(self::$base . '/index.php?p=login', ['csrf' => $csrf, '担当者' => $name], $cookies);
        $this->assertSame(302, $s2);
        return [$s2, '', $cookies];
    }
}
