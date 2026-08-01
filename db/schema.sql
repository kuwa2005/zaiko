PRAGMA foreign_keys = ON;

-- 担当者（ログイン相当）
CREATE TABLE IF NOT EXISTS 担当者 (
  id        INTEGER PRIMARY KEY AUTOINCREMENT,
  名前      TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

-- 採番（DB自動採番: 手配=H+10桁 / 出庫=S+10桁）
CREATE TABLE IF NOT EXISTS 採番 (
  種別 TEXT PRIMARY KEY,
  連番 INTEGER NOT NULL DEFAULT 0
);

-- 在庫マスタ（商品マスタ）
CREATE TABLE IF NOT EXISTS 在庫マスタ (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  コード        TEXT NOT NULL UNIQUE,
  品名          TEXT NOT NULL,
  基本数量      REAL NOT NULL DEFAULT 1 CHECK (基本数量 > 0),
  単位          TEXT NOT NULL,
  単価          REAL NOT NULL DEFAULT 0 CHECK (単価 >= 0),
  残数量        REAL NOT NULL DEFAULT 0,
  安全在庫数    REAL NOT NULL DEFAULT 0,
  最小発注数量  REAL NOT NULL DEFAULT 0,
  適正在庫数    REAL NOT NULL DEFAULT 0,
  標準納入日数  INTEGER,
  棚番          TEXT,
  取引先        TEXT,
  登録者        TEXT,
  登録日        TEXT,
  更新者        TEXT,
  更新日        TEXT,
  備考          TEXT,
  削除フラグ    INTEGER NOT NULL DEFAULT 0,
  削除日        TEXT,
  削除者        TEXT
);
CREATE INDEX IF NOT EXISTS idx_items_code ON 在庫マスタ (コード);

-- 発注先マスタ（仕入れ先メーカー・卸）
CREATE TABLE IF NOT EXISTS 発注先マスタ (
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

-- 出庫先マスタ（販売先・小売店）
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
);

-- 発注下書き（発注依頼シート相当。DB未確定の下書き）
CREATE TABLE IF NOT EXISTS 発注下書き (
  id        INTEGER PRIMARY KEY AUTOINCREMENT,
  コード    TEXT NOT NULL,
  品名      TEXT NOT NULL,
  数量      REAL NOT NULL,
  納期      TEXT,
  型式      TEXT,
  発注先    TEXT,
  依頼者    TEXT,
  依頼日    TEXT,
  備考      TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

-- 発注データ（発注伝票）
CREATE TABLE IF NOT EXISTS 発注データ (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  管理NO      TEXT NOT NULL UNIQUE,
  注番        TEXT,
  コード      TEXT NOT NULL,
  品名        TEXT NOT NULL,
  数量        REAL NOT NULL CHECK (数量 > 0),
  納期        TEXT,
  型式        TEXT,
  発注先      TEXT,
  依頼者      TEXT,
  依頼日      TEXT,
  受付者      TEXT,
  受付日      TEXT,
  入庫者      TEXT,
  入庫日      TEXT,
  ステータス  TEXT NOT NULL DEFAULT '未受付',
  備考        TEXT,
  created_at  TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  更新日      TEXT,
  削除フラグ  INTEGER NOT NULL DEFAULT 0,
  削除日      TEXT,
  削除者      TEXT
);
CREATE INDEX IF NOT EXISTS idx_orders_code ON 発注データ (コード);
CREATE INDEX IF NOT EXISTS idx_orders_status ON 発注データ (ステータス);

-- 出庫データ（出庫実績）
CREATE TABLE IF NOT EXISTS 出庫データ (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  管理NO      TEXT NOT NULL UNIQUE,
  コード      TEXT NOT NULL,
  品名        TEXT NOT NULL,
  出庫数      REAL NOT NULL CHECK (出庫数 > 0),
  出庫先      TEXT,
  出庫者      TEXT,
  出庫日      TEXT,
  備考        TEXT,
  created_at  TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  更新日      TEXT,
  削除フラグ  INTEGER NOT NULL DEFAULT 0,
  削除日      TEXT,
  削除者      TEXT
);
CREATE INDEX IF NOT EXISTS idx_ship_code ON 出庫データ (コード);

-- 月次締め履歴（期首残の記録。リアルタイム残高とは別に月次スナップショット）
CREATE TABLE IF NOT EXISTS 締め履歴 (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  対象月      TEXT NOT NULL,
  コード      TEXT NOT NULL,
  品名        TEXT,
  前月残数量  REAL NOT NULL DEFAULT 0,
  当入数量    REAL NOT NULL DEFAULT 0,
  当出数量    REAL NOT NULL DEFAULT 0,
  当残数量    REAL NOT NULL DEFAULT 0,
  締め日      TEXT,
  締め者      TEXT
);
CREATE INDEX IF NOT EXISTS idx_close_month ON 締め履歴 (対象月);

-- 操作ログ
CREATE TABLE IF NOT EXISTS 操作ログ (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  操作種別    TEXT,
  対象テーブル TEXT,
  対象KEY     TEXT,
  操作者      TEXT,
  操作日時    TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  変更内容    TEXT
);
