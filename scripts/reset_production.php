<?php
declare(strict_types=1);

/**
 * テストデータ全消去スクリプト（本番運用開始の準備）
 * テスト投入したデータを全て消去し、本番運用を開始できる状態に戻す。
 *
 * 消去対象:
 *   - 操作ログ / 締め履歴 / 出庫データ / 発注データ / 発注下書き
 *   - 在庫マスタ / 発注先マスタ / 出庫先マスタ
 *   - 採番（連番を 0 にリセット）
 * 保持対象:
 *   - 担当者（ログイン用の担当者リスト）とテーブル構造（スキーマ）
 *
 * 安全対策:
 *   - 実行前に確認プロンプト（DBファイルの絶対パスを入力。--yes で省略可）
 *   - 実行前に DB ファイルを <DBのディレクトリ>/backup/ へバックアップ
 *   - --dry-run で消去対象の件数確認のみ行える
 *
 * 使い方:
 *   sudo -u www-data ZAIKO_DB_PATH=/var/www/trashbox.in/zaiko/db/zaiko.db php scripts/reset_production.php
 *   sudo -u www-data ZAIKO_DB_PATH=/var/www/trashbox.in/zaiko/db/zaiko.db php scripts/reset_production.php --yes
 *   sudo -u www-data ZAIKO_DB_PATH=/var/www/trashbox.in/zaiko/db/zaiko.db php scripts/reset_production.php --dry-run
 */
require __DIR__ . '/../app/bootstrap.php';

$dbPath = realpath(DB_PATH) ?: DB_PATH;
if (!is_file($dbPath)) {
    fwrite(STDERR, "DBファイルが見つかりません: {$dbPath}\n");
    exit(1);
}

$targets = ['操作ログ', '締め履歴', '出庫データ', '発注データ', '発注下書き', '在庫マスタ', '発注先マスタ', '出庫先マスタ'];
$dryRun = in_array('--dry-run', $argv, true);
$force  = in_array('--yes', $argv, true);

echo "== テストデータ消去の確認 ==\n";
echo "対象DB: {$dbPath}\n";
echo "消去: " . implode(' / ', $targets) . "\n";
echo "リセット: 採番（連番=0）\n";
echo "保持: 担当者・テーブル構造\n";
echo "---- 現在の件数 ----\n";
$counts = [];
foreach ($targets as $t) {
    $counts[$t] = (int)db_val("SELECT COUNT(*) FROM $t");
    printf("  %-10s %d 件\n", $t, $counts[$t]);
}
$担数 = (int)db_val("SELECT COUNT(*) FROM 担当者");
printf("  担当者   %d 名（保持）\n", $担数);
foreach (db_all("SELECT 種別, 連番 FROM 採番 ORDER BY 種別") as $r) {
    printf("  採番 %s = %d\n", $r['種別'], $r['連番']);
}

if ($dryRun) {
    echo "\n(dry-run) 消去は行いませんでした。\n";
    exit(0);
}

// ---- 確認プロンプト ----
if (!$force) {
    echo "\n実行するには DB ファイルの絶対パスを入力して下さい（中止は Ctrl+C）:\n> ";
    $input = trim((string)fgets(STDIN));
    if ($input !== $dbPath) {
        echo "入力が一致しないため中断しました。\n";
        exit(1);
    }
}

// ---- バックアップ ----
$backupDir = dirname($dbPath) . '/backup';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0775, true);
}
$backupFile = $backupDir . '/' . basename($dbPath) . '.' . date('Ymd-His');
if (!copy($dbPath, $backupFile)) {
    fwrite(STDERR, "バックアップに失敗しました: {$backupFile}\n");
    exit(1);
}

// ---- 消去（トランザクション） ----
$pdo = db();
$pdo->beginTransaction();
try {
    foreach ($targets as $t) {
        $pdo->exec("DELETE FROM $t");
    }
    $pdo->exec("UPDATE 採番 SET 連番 = 0");
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('" . implode("','", $targets) . "')");
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    throw $e;
}

// 物理ファイルサイズ縮小（できなくても致命的ではない）
try {
    $pdo->exec("VACUUM");
} catch (Throwable $e) {
    // VACUUM に失敗しても続行（他の接続が開いている場合など）
}

echo "\n== テストデータを全消去しました ==\n";
echo "バックアップ: {$backupFile}\n";
echo "---- 残件数 ----\n";
foreach ($targets as $t) {
    printf("  %-10s %d 件\n", $t, (int)db_val("SELECT COUNT(*) FROM $t"));
}
printf("  担当者   %d 名（保持）\n", (int)db_val("SELECT COUNT(*) FROM 担当者"));
foreach (db_all("SELECT 種別, 連番 FROM 採番 ORDER BY 種別") as $r) {
    printf("  採番 %s = %d\n", $r['種別'], $r['連番']);
}
echo "本番運用を開始できます。\n";
