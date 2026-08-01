<?php
declare(strict_types=1);

// ---- 共通ユーティリティ ----

/** リダイレクト */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/** クエリ付きページURL生成 */
function url(string $page, array $params = []): string
{
    $qs = http_build_query(array_merge(['p' => $page], $params));
    return 'index.php?' . $qs;
}

/** CSRFトークン生成/取得 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** CSRFチェック */
function csrf_check(?string $token): void
{
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('CSRF検証に失敗しました。');
    }
}

/** POST判定 */
function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** 数値チェック */
function is_num(mixed $v): bool
{
    return is_numeric($v);
}

/** フラッシュメッセージ設定 */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** フラッシュ取得（表示後にクリアするため次リクエストへ退避） */
function flash_pull(): void
{
    if (!empty($_SESSION['flash_next'])) {
        $_SESSION['flash'] = $_SESSION['flash_next'];
    }
    $_SESSION['flash_next'] = [];
}

/** フラッシュをレイアウトへ渡す（表示後クリア） */
function flash_take(): array
{
    $msgs = $_SESSION['flash'] ?? [];
    $_SESSION['flash'] = [];
    return $msgs;
}

/** 操作ログ記録 */
function log_op(string $種別, string $対象テーブル = '', string $対象KEY = '', string $変更内容 = ''): void
{
    db_exec(
        "INSERT INTO 操作ログ (操作種別, 対象テーブル, 対象KEY, 操作者, 変更内容)
         VALUES (?, ?, ?, ?, ?)",
        [$種別, $対象テーブル, $対象KEY, current_担当者() ?? '-', $変更内容]
    );
}

/** 日付フォーマット (yyyy/mm/dd) */
function jdate(?string $d): string
{
    if ($d === null || $d === '') {
        return '';
    }
    return date('Y/m/d', strtotime($d));
}

/** 数値の表示用整形 */
function fmt_num($v, int $dec = 0): string
{
    return number_format((float)$v, $dec);
}

/** 安全なHTMLエスケープ */
function h(mixed $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
