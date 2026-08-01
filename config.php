<?php
declare(strict_types=1);

// アプリ定数
const APP_NAME     = '在庫管理システム';
const APP_VERSION  = '1.0.0';

// データベースファイルのパス（webapp/db/zaiko.db）
// テスト等では環境変数 ZAIKO_DB_PATH で切替可能
define('DB_PATH', getenv('ZAIKO_DB_PATH') ?: __DIR__ . '/db/zaiko.db');
define('SCHEMA_PATH', __DIR__ . '/db/schema.sql');

// タイムゾーン
date_default_timezone_set('Asia/Tokyo');

// デバッグ表示（本番では false。開発時は環境変数 ZAIKO_DEBUG=1）
define('APP_DEBUG', getenv('ZAIKO_DEBUG') === '1');
