#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * SQLite → MySQL 変換スクリプト
 * 現在の SQLite データベースを MySQL 8.0+ へインポートできる SQL ダンプとして出力する。
 *
 * 使い方:
 *   php scripts/export_to_mysql.php > dump.sql
 *   php scripts/export_to_mysql.php --output=dump.sql
 *   php scripts/export_to_mysql.php --schema-only
 *   ZAIKO_DB_PATH=/path/to/zaiko.db php scripts/export_to_mysql.php > dump.sql
 *
 * 出力内容:
 *   - SET NAMES utf8mb4 / FOREIGN_KEY_CHECKS=0
 *   - CREATE TABLE（MySQL型変換済み）
 *   - INSERT（バッチ100行）
 *   - CREATE INDEX（IF NOT EXISTS 削除済み）
 *
 * MySQL 側の要件:
 *   - 文字セット: utf8mb4 / utf8mb4_general_ci（サーバ・DB・テーブルすべて）
 *   - バージョン: MySQL 8.0 以上を推奨
 */
require __DIR__ . '/../app/bootstrap.php';

// ---- オプション解析 ----
$outFile     = null;
$schemaOnly  = false;
foreach ($argv as $arg) {
    if (preg_match('/^--output=(.+)$/', $arg, $m)) { $outFile = $m[1]; }
    if ($arg === '--schema-only') { $schemaOnly = true; }
}

$fp = $outFile !== null ? fopen($outFile, 'w') : null;
if ($fp === false) {
    fwrite(STDERR, "ファイルを開けません: {$outFile}\n");
    exit(1);
}

function out(string $text): void {
    global $fp;
    if ($fp !== null) { fwrite($fp, $text); } else { echo $text; }
}

// ---- SQLite 接続 ----
$pdo = new PDO('sqlite:' . DB_PATH);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// ---- MySQL エスケープ ----
function mysqlEscape(string $val): string {
    return str_replace(
        ["\\", "\0", "\n", "\r", "\x1a", "'"],
        ["\\\\", "\\0", "\\n", "\\r", "\\Z", "\\'"],
        $val
    );
}

function mysqlValue($val): string {
    if ($val === null) { return 'NULL'; }
    if (is_int($val) || is_float($val)) { return (string)$val; }
    return "'" . mysqlEscape((string)$val) . "'";
}

// ---- スキーマ変換（SQLite → MySQL） ----
function convertSchema(string $sql): string {
    // CHECK 制約を除去（MySQL 5.7 以前は非対応）
    $sql = preg_replace('/\s*CHECK\s*\([^)]+\)/i', '', $sql);
    // AUTOINCREMENT → AUTO_INCREMENT
    $sql = preg_replace('/INTEGER\s+PRIMARY\s+KEY\s+AUTOINCREMENT/i',
                        'INT PRIMARY KEY AUTO_INCREMENT', $sql);
    // 残りの INTEGER → INT
    $sql = preg_replace('/\bINTEGER\b/i', 'INT', $sql);
    // REAL → DOUBLE
    $sql = preg_replace('/\bREAL\b/i', 'DOUBLE', $sql);
    // DEFAULT datetime('now','localtime') → CURRENT_TIMESTAMP
    $sql = preg_replace("/DEFAULT\s*\(\s*datetime\s*\(\s*'now'\s*,\s*'localtime'\s*\)\s*\)/i",
                        'DEFAULT CURRENT_TIMESTAMP', $sql);
    // テーブル名にバッククォート
    $sql = preg_replace('/CREATE\s+TABLE\s+(IF\s+NOT\s+EXISTS\s+)(\S+)/i',
                        'CREATE TABLE $1`$2`', $sql);
    return $sql;
}

// ---- ヘッダー ----
out("-- ==========================================\n");
out("-- 在庫管理システム MySQL ダンプ\n");
out("-- SQLite: " . basename(DB_PATH) . "\n");
out("-- 生成日: " . date('Y-m-d H:i:s') . "\n");
out("-- MySQL: 8.0+ / utf8mb4\n");
out("-- ==========================================\n\n");
out("SET NAMES utf8mb4;\n");
out("SET FOREIGN_KEY_CHECKS = 0;\n");
out("SET UNIQUE_CHECKS = 0;\n\n");

// ---- テーブル取得（sqlite内部テーブルを除外） ----
$tables = $pdo->query(
    "SELECT name, sql FROM sqlite_master
     WHERE type='table' AND name NOT LIKE 'sqlite_%'
     ORDER BY name"
)->fetchAll();

foreach ($tables as $tbl) {
    $name = $tbl['name'];
    out("-- ---- {$name} ----\n");
    out(convertSchema($tbl['sql']) . ";\n\n");

    if ($schemaOnly) { continue; }

    // データ取得
    $rows = $pdo->query("SELECT * FROM \"$name\"")->fetchAll();
    if (empty($rows)) { continue; }

    $cols   = array_keys($rows[0]);
    $colSQL = implode(', ', array_map(fn($c) => "`$c`", $cols));

    // 100行ずつバッチ INSERT
    foreach (array_chunk($rows, 100) as $chunk) {
        $lines = [];
        foreach ($chunk as $row) {
            $vals = array_map(fn($v) => mysqlValue($v), $row);
            $lines[] = '(' . implode(', ', $vals) . ')';
        }
        out("INSERT INTO `{$name}` ({$colSQL}) VALUES\n");
        out(implode(",\n", $lines) . ";\n\n");
    }
}

// ---- インデックス ----
if (!$schemaOnly) {
    out("-- ---- インデックス ----\n");
    $idxs = $pdo->query(
        "SELECT sql FROM sqlite_master
         WHERE type='index' AND sql IS NOT NULL AND name NOT LIKE 'sqlite_%'
         ORDER BY name"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($idxs as $idxSQL) {
        // MySQL は CREATE INDEX に IF NOT EXISTS 未対応
        $idxSQL = preg_replace('/IF\s+NOT\s+EXISTS\s+/i', '', $idxSQL);
        // テーブル名にバッククォート（ON テーブル の部分）
        $idxSQL = preg_replace('/ON\s+(\S+)\s*\(/i', 'ON `$1` (', $idxSQL);
        out($idxSQL . ";\n");
    }
    out("\n");
}

out("SET FOREIGN_KEY_CHECKS = 1;\n");
out("SET UNIQUE_CHECKS = 1;\n\n");
out("-- Dump complete\n");

// ---- 結果出力 ----
$tableCount = count($tables);
$rowCounts  = [];
foreach ($tables as $tbl) {
    $rowCounts[$tbl['name']] = (int)$pdo->query("SELECT COUNT(*) FROM \"{$tbl['name']}\"")->fetchColumn();
}
$totalRows = array_sum($rowCounts);

$dest = $outFile ?? 'stdout';
fwrite(STDERR, "変換完了: {$tableCount}テーブル / {$totalRows}行 → {$dest}\n");
foreach ($rowCounts as $t => $c) {
    if ($c > 0) { fwrite(STDERR, "  {$t}: {$c}行\n"); }
}

if ($fp !== null) { fclose($fp); }
