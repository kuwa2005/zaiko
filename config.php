<?php
declare(strict_types=1);

// アプリ定数
const APP_NAME     = '在庫管理システム';
const APP_VERSION  = '1.1.0';

// データベースファイルのパス（webapp/db/zaiko.db）
// テスト等では環境変数 ZAIKO_DB_PATH で切替可能
define('DB_PATH', getenv('ZAIKO_DB_PATH') ?: __DIR__ . '/db/zaiko.db');
define('SCHEMA_PATH', __DIR__ . '/db/schema.sql');
define('SCHEMA_MYSQL_PATH', __DIR__ . '/db/schema_mysql.sql');

// DB ドライバ（sqlite=開発/テスト / mysql=本番）
// 本番では公開ディレクトリ外の設定ファイル（下記 $zaiko_db_conf）を読んで mysql に切替。
// 環境変数 ZAIKO_DB_DRIVER を明示すればその値が優先される。
// テスト/開発（ZAIKO_DB_PATH 指定）では sqlite 固定。
$zaiko_db_conf = '/virtual/pcm/.config/zaiko.db.php';
$driver = getenv('ZAIKO_DB_DRIVER');
if ($driver === false || $driver === '') {
    if (getenv('ZAIKO_DB_PATH') !== false && getenv('ZAIKO_DB_PATH') !== '') {
        $driver = 'sqlite';
    } elseif (is_file($zaiko_db_conf) && is_readable($zaiko_db_conf)) {
        $driver = 'mysql';
    } else {
        $driver = 'sqlite';
    }
}
define('DB_DRIVER', $driver);

if (DB_DRIVER === 'mysql') {
    $c = (is_file($zaiko_db_conf) && is_readable($zaiko_db_conf)) ? require $zaiko_db_conf : [];
    $c = is_array($c) ? $c : [];
    define('DB_HOST', getenv('ZAIKO_DB_HOST') ?: ($c['host'] ?? 'localhost'));
    define('DB_PORT', getenv('ZAIKO_DB_PORT') ?: ($c['port'] ?? '3306'));
    define('DB_NAME', getenv('ZAIKO_DB_NAME') ?: ($c['name'] ?? ''));
    define('DB_USER', getenv('ZAIKO_DB_USER') ?: ($c['user'] ?? ''));
    define('DB_PASS', getenv('ZAIKO_DB_PASS') ?: ($c['pass'] ?? ''));
}

// タイムゾーン
date_default_timezone_set('Asia/Tokyo');

// デバッグ表示（本番では false。開発時は環境変数 ZAIKO_DEBUG=1）
define('APP_DEBUG', getenv('ZAIKO_DEBUG') === '1');
