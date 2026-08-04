<?php
declare(strict_types=1);

// ---- システム管理（URL 直叩きのみ。ナビ非表示） ----
require_担当者();

$MASTER_TABLES = ['担当者', '採番', '在庫マスタ', '発注先マスタ', '出庫先マスタ'];
$TRANS_TABLES  = ['発注下書き', '発注データ', '出庫データ', '締め履歴', '操作ログ'];
$ALL_TABLES    = array_merge($MASTER_TABLES, $TRANS_TABLES);

$msg  = '';
$kind = 'success';

// ============================================================
// POST ハンドラ
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act   = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';
    csrf_check($token);

    try {
        switch ($act) {

        case 'backup_full':
            download_backup($ALL_TABLES, 'full');
            exit;
        case 'backup_master':
            download_backup($MASTER_TABLES, 'master');
            exit;

        case 'clear_full':
            if (($_POST['confirm'] ?? '') !== 'DELETE') {
                throw new \RuntimeException('確認テキストが一致しません');
            }
            clear_data($ALL_TABLES);
            log_op('管理操作', '全データクリア', '', current_担当者() ?? '');
            $msg = '全データをクリアしました。';
            break;

        case 'clear_master':
            if (($_POST['confirm'] ?? '') !== 'DELETE') {
                throw new \RuntimeException('確認テキストが一致しません');
            }
            clear_data($MASTER_TABLES);
            log_op('管理操作', 'マスターデータクリア', '', current_担当者() ?? '');
            $msg = 'マスターデータをクリアしました。';
            break;

        case 'restore_full':
            restore_from_upload($ALL_TABLES, 'フル');
            $msg = 'フルリストアが完了しました。';
            break;
        case 'restore_master':
            restore_from_upload($MASTER_TABLES, 'マスター');
            $msg = 'マスターリストアが完了しました。';
            break;
        }
    } catch (\Throwable $e) {
        $msg = 'エラー: ' . $e->getMessage();
        $kind = 'error';
    }
}

// ============================================================
// ヘルパー関数
// ============================================================

function table_counts(array $tables): array
{
    $out = [];
    foreach ($tables as $t) {
        $out[$t] = (int)db_val("SELECT COUNT(*) FROM `$t`");
    }
    return $out;
}

function build_insert_lines(string $table): array
{
    $rows = db_all("SELECT * FROM `$table`");
    if ($rows === []) return [];

    $cols   = array_keys($rows[0]);
    $quoted = array_map(fn($c) => "`$c`", $cols);
    $lines  = ["INSERT INTO `$table` (" . implode(', ', $quoted) . ") VALUES"];

    foreach ($rows as $i => $row) {
        $vals = array_map(function ($v) {
            if ($v === null) return 'NULL';
            return "'" . str_replace("'", "''", (string)$v) . "'";
        }, array_values($row));
        $sep = $i < count($rows) - 1 ? ',' : ';';
        $lines[] = '  (' . implode(', ', $vals) . ')' . $sep;
    }
    return $lines;
}

function download_backup(array $tables, string $label): void
{
    $ts   = date('Ymd-His');
    $body = "-- ZAIKO Backup ($label) {$ts}\n"
          . "-- Driver: " . DB_DRIVER . "\n\n";

    if (DB_DRIVER === 'mysql') {
        $body .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    }

    foreach ($tables as $t) {
        $body .= "-- $t\n";
        $body .= "DELETE FROM `$t`;\n";
        foreach (build_insert_lines($t) as $line) {
            $body .= $line . "\n";
        }
        $body .= "\n";
    }

    $body .= "-- 採番\n";
    $body .= "DELETE FROM `採番`;\n";
    foreach (db_all("SELECT 種別, 連番 FROM `採番`") as $r) {
        $body .= "INSERT INTO `採番` (`種別`, `連番`) VALUES ('{$r['種別']}', {$r['連番']});\n";
    }
    $body .= "\n";

    if (DB_DRIVER === 'mysql') {
        $body .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    }

    $size = strlen($body);
    header('Content-Type: application/sql; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"zaiko_{$label}_{$ts}.sql\"");
    header("Content-Length: {$size}");
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo $body;
}

function restore_from_upload(array $tables, string $label): void
{
    if (!isset($_FILES['restore_file']) || $_FILES['restore_file']['error'] !== UPLOAD_ERR_OK) {
        throw new \RuntimeException('ファイルのアップロードに失敗しました');
    }
    $sql = file_get_contents($_FILES['restore_file']['tmp_name']);
    if ($sql === false) {
        throw new \RuntimeException('ファイルの読み込みに失敗しました');
    }

    $pdo = db();
    if (DB_DRIVER === 'mysql') {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    }

    $pdo->beginTransaction();
    try {
        foreach ($tables as $t) {
            $pdo->exec("DELETE FROM `$t`");
        }

        // INSERT 文のみ抽出・実行
        foreach (preg_split('/;\s*\n/s', $sql) as $chunk) {
            $s = trim($chunk);
            if ($s === '' || str_starts_with($s, '--') || str_starts_with($s, 'SET ')) continue;
            if (!preg_match('/^INSERT\s+INTO\s+`?(\w+)`?/i', $s, $m)) continue;
            if (!in_array($m[1], $tables, true)) continue;
            $pdo->exec($s);
        }

        // 採番リセット
        reset_sequence($tables);

        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    if (DB_DRIVER === 'mysql') {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }

    log_op('管理操作', "{$label}リストア実行", '', current_担当者() ?? '');
}

function clear_data(array $tables): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($tables as $t) {
            $pdo->exec("DELETE FROM `$t`");
        }
        reset_sequence($tables);
        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function reset_sequence(array $tables): void
{
    $pdo = db();
    if (DB_DRIVER === 'sqlite') {
        $pdo->exec("DELETE FROM sqlite_sequence");
        foreach ($tables as $t) {
            if ($t === '採番') continue;
            $max = (int)db_val("SELECT COALESCE(MAX(id), 0) FROM `$t`");
            if ($max > 0) {
                $pdo->exec("INSERT INTO sqlite_sequence (name, seq) VALUES ('$t', $max)");
            }
        }
    } else {
        foreach ($tables as $t) {
            if ($t === '採番') continue;
            $max = (int)db_val("SELECT COALESCE(MAX(id), 0) FROM `$t`");
            $pdo->exec("ALTER TABLE `$t` AUTO_INCREMENT = " . ($max + 1));
        }
    }
}

// ============================================================
// 表示
// ============================================================
$title = 'システム管理';
$counts = table_counts($ALL_TABLES);
$csrf   = csrf_token();
require 'app/layouts/header.php';
?>

<?php if ($msg): ?>
  <div class="alert <?= $kind === 'error' ? 'alert-error' : 'alert-info' ?>"><?= h($msg) ?></div>
<?php endif; ?>

<div class="form-card">
  <h2>現在のデータ状況</h2>
  <table class="grid">
    <tr><th>テーブル</th><th>種別</th><th>件数</th></tr>
    <?php foreach ($ALL_TABLES as $t): ?>
    <tr>
      <td><?= h($t) ?></td>
      <td><?= in_array($t, $MASTER_TABLES, true) ? 'マスター' : 'トランザクション' ?></td>
      <td class="num"><?= number_format($counts[$t]) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="form-card">
  <h2>バックアップ</h2>
  <p>現在のデータを SQL ファイルとしてダウンロードします。</p>
  <div class="form-grid" style="grid-template-columns:1fr 1fr">
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="backup_full">
      <button class="btn" type="submit">フルバックアップ（全テーブル）</button>
    </form>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="backup_master">
      <button class="btn" type="submit">マスターバックアップ（担当者・採番・マスタ系）</button>
    </form>
  </div>
</div>

<div class="form-card">
  <h2>リストア</h2>
  <p>バックアップファイル（.sql）をアップロードしてリストアします。既存データは上書きされます。</p>
  <div class="form-grid" style="grid-template-columns:1fr 1fr">
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="restore_full">
      <div class="form-item"><label>フルリストア用ファイル</label>
        <input type="file" name="restore_file" accept=".sql" required></div>
      <div class="form-item"><button class="btn" type="submit" onclick="return confirm('全データが上書きされます。続行しますか？')">フルリストア実行</button></div>
    </form>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="restore_master">
      <div class="form-item"><label>マスターリストア用ファイル</label>
        <input type="file" name="restore_file" accept=".sql" required></div>
      <div class="form-item"><button class="btn" type="submit" onclick="return confirm('マスターデータが上書きされます。続行しますか？')">マスターリストア実行</button></div>
    </form>
  </div>
</div>

<div class="form-card" style="border-color:#c0392b">
  <h2 style="color:#c0392b">データクリア</h2>
  <p><strong>この操作は取り消せません。</strong>確認テキスト「DELETE」を入力してください。</p>
  <div class="form-grid" style="grid-template-columns:1fr 1fr">
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="clear_full">
      <div class="form-item"><label>確認テキスト</label>
        <input name="confirm" required placeholder="DELETEと入力"></div>
      <div class="form-item"><button class="btn btn-danger" type="submit" onclick="return confirm('全データを削除します。本当によろしいですか？')">全データクリア</button></div>
    </form>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="clear_master">
      <div class="form-item"><label>確認テキスト</label>
        <input name="confirm" required placeholder="DELETEと入力"></div>
      <div class="form-item"><button class="btn btn-danger" type="submit" onclick="return confirm('マスターデータを削除します。本当によろしいですか？')">マスターデータクリア</button></div>
    </form>
  </div>
</div>

<?php require 'app/layouts/footer.php'; ?>
