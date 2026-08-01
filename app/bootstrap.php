<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

// ---- セッション ----
if (session_status() !== PHP_SESSION_ACTIVE) {
    // クッキー強度設定（HttpOnly / SameSite / HTTPS時は Secure）
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $secure,
    ]);
    session_name('zaiko_session');
    session_start();
}

// ---- セキュリティヘッダ ----
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// ---- エラー表示 ----
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');

// ---- DB初期化（初回起動時にスキーマ作成・初期データ投入） ----
function db_init(): void
{
    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $needSchema = !file_exists(DB_PATH) || filesize(DB_PATH) === 0;
    if ($needSchema) {
        $sql = file_get_contents(SCHEMA_PATH);
        $pdo->exec($sql);
    }

    // 採番の初期化
    $count = (int)$pdo->query("SELECT COUNT(*) FROM 採番")->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare("INSERT INTO 採番 (種別, 連番) VALUES (?, 0)");
        $stmt->execute(['手配']);
        $stmt->execute(['出庫']);
    }

    // 既存DBへの列追加（スキーマ移行）
    $cols = $pdo->query("PRAGMA table_info(在庫マスタ)")->fetchAll(PDO::FETCH_ASSOC);
    $names = array_column($cols, 'name');
    if (!in_array('適正在庫数', $names, true)) {
        $pdo->exec("ALTER TABLE 在庫マスタ ADD COLUMN 適正在庫数 REAL NOT NULL DEFAULT 0");
    }

    // マスタ系テーブル（既存DBへ追加）
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS 発注先マスタ (
           id          INTEGER PRIMARY KEY AUTOINCREMENT,
           発注先コード TEXT NOT NULL UNIQUE,
           発注先名     TEXT NOT NULL,
           住所        TEXT,
           電話番号     TEXT,
           担当者       TEXT,
           備考        TEXT,
           登録者       TEXT,
           登録日       TEXT,
           更新者       TEXT,
           更新日       TEXT,
           削除フラグ   INTEGER NOT NULL DEFAULT 0,
           削除日       TEXT,
           削除者       TEXT
         );
         CREATE TABLE IF NOT EXISTS 出庫先マスタ (
           id          INTEGER PRIMARY KEY AUTOINCREMENT,
           出庫先コード TEXT NOT NULL UNIQUE,
           出庫先名     TEXT NOT NULL,
           住所        TEXT,
           電話番号     TEXT,
           担当者       TEXT,
           備考        TEXT,
           登録者       TEXT,
           登録日       TEXT,
           更新者       TEXT,
           更新日       TEXT,
           削除フラグ   INTEGER NOT NULL DEFAULT 0,
           削除日       TEXT,
           削除者       TEXT
         )"
    );

    // 出庫データへの出庫先列追加
    $cols = $pdo->query("PRAGMA table_info(出庫データ)")->fetchAll(PDO::FETCH_ASSOC);
    $names = array_column($cols, 'name');
    if (!in_array('出庫先', $names, true)) {
        $pdo->exec("ALTER TABLE 出庫データ ADD COLUMN 出庫先 TEXT");
    }

    // 発注下書き / 発注データ への発注先列追加
    $cols = $pdo->query("PRAGMA table_info(発注下書き)")->fetchAll(PDO::FETCH_ASSOC);
    $names = array_column($cols, 'name');
    if (!in_array('発注先', $names, true)) {
        $pdo->exec("ALTER TABLE 発注下書き ADD COLUMN 発注先 TEXT");
    }
    $cols = $pdo->query("PRAGMA table_info(発注データ)")->fetchAll(PDO::FETCH_ASSOC);
    $names = array_column($cols, 'name');
    if (!in_array('発注先', $names, true)) {
        $pdo->exec("ALTER TABLE 発注データ ADD COLUMN 発注先 TEXT");
    }

    // 担当者の初期データ（例: 担当A〜担当E）
    $count = (int)$pdo->query("SELECT COUNT(*) FROM 担当者")->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare("INSERT INTO 担当者 (名前) VALUES (?)");
        foreach (['担当A', '担当B', '担当C', '担当D', '担当E'] as $name) {
            $stmt->execute([$name]);
        }
    }
}

db_init();

// ---- 共通ライブラリ読み込み ----
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/util.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/business.php';

// 前回リクエストのフラッシュメッセージを移す
flash_pull();
