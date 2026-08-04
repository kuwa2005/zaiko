-- MySQL / MariaDB 用スキーマ（db/schema.sql の移植版）
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS 担当者 (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  名前       VARCHAR(255) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS 採番 (
  種別 VARCHAR(64) PRIMARY KEY,
  連番 INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS 在庫マスタ (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  コード       VARCHAR(255) NOT NULL UNIQUE,
  品名         VARCHAR(255) NOT NULL,
  基本数量     DOUBLE NOT NULL DEFAULT 1,
  単位         VARCHAR(64) NOT NULL,
  単価         DOUBLE NOT NULL DEFAULT 0,
  残数量       DOUBLE NOT NULL DEFAULT 0,
  安全在庫数   DOUBLE NOT NULL DEFAULT 0,
  最小発注数量 DOUBLE NOT NULL DEFAULT 0,
  適正在庫数   DOUBLE NOT NULL DEFAULT 0,
  標準納入日数 INT,
  棚番         VARCHAR(64),
  取引先       VARCHAR(255),
  登録者       VARCHAR(255),
  登録日       VARCHAR(32),
  更新者       VARCHAR(255),
  更新日       VARCHAR(32),
  備考         TEXT,
  削除フラグ   INT NOT NULL DEFAULT 0,
  削除日       VARCHAR(32),
  削除者       VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE INDEX idx_items_code ON 在庫マスタ (コード);

CREATE TABLE IF NOT EXISTS 発注先マスタ (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  発注先コード VARCHAR(255) NOT NULL UNIQUE,
  発注先名     VARCHAR(255) NOT NULL,
  住所         VARCHAR(255),
  電話番号     VARCHAR(64),
  担当者       VARCHAR(255),
  備考         TEXT,
  登録者       VARCHAR(255),
  登録日       VARCHAR(32),
  更新者       VARCHAR(255),
  更新日       VARCHAR(32),
  削除フラグ   INT NOT NULL DEFAULT 0,
  削除日       VARCHAR(32),
  削除者       VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS 出庫先マスタ (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  出庫先コード VARCHAR(255) NOT NULL UNIQUE,
  出庫先名     VARCHAR(255) NOT NULL,
  住所         VARCHAR(255),
  電話番号     VARCHAR(64),
  担当者       VARCHAR(255),
  備考         TEXT,
  登録者       VARCHAR(255),
  登録日       VARCHAR(32),
  更新者       VARCHAR(255),
  更新日       VARCHAR(32),
  削除フラグ   INT NOT NULL DEFAULT 0,
  削除日       VARCHAR(32),
  削除者       VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS 発注下書き (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  コード     VARCHAR(255) NOT NULL,
  品名       VARCHAR(255) NOT NULL,
  数量       DOUBLE NOT NULL,
  納期       VARCHAR(32),
  型式       VARCHAR(255),
  発注先     VARCHAR(255),
  依頼者     VARCHAR(255),
  依頼日     VARCHAR(32),
  備考       TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS 発注データ (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  管理NO     VARCHAR(64) NOT NULL UNIQUE,
  注番       VARCHAR(64),
  コード     VARCHAR(255) NOT NULL,
  品名       VARCHAR(255) NOT NULL,
  数量       DOUBLE NOT NULL,
  納期       VARCHAR(32),
  型式       VARCHAR(255),
  発注先     VARCHAR(255),
  依頼者     VARCHAR(255),
  依頼日     VARCHAR(32),
  受付者     VARCHAR(255),
  受付日     VARCHAR(32),
  入庫者     VARCHAR(255),
  入庫日     VARCHAR(32),
  ステータス VARCHAR(32) NOT NULL DEFAULT '未受付',
  備考       TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  更新日     VARCHAR(32),
  削除フラグ INT NOT NULL DEFAULT 0,
  削除日     VARCHAR(32),
  削除者     VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE INDEX idx_orders_code ON 発注データ (コード);
CREATE INDEX idx_orders_status ON 発注データ (ステータス);

CREATE TABLE IF NOT EXISTS 出庫データ (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  管理NO     VARCHAR(64) NOT NULL UNIQUE,
  コード     VARCHAR(255) NOT NULL,
  品名       VARCHAR(255) NOT NULL,
  出庫数     DOUBLE NOT NULL,
  出庫先     VARCHAR(255),
  出庫者     VARCHAR(255),
  出庫日     VARCHAR(32),
  備考       TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  更新日     VARCHAR(32),
  削除フラグ INT NOT NULL DEFAULT 0,
  削除日     VARCHAR(32),
  削除者     VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE INDEX idx_ship_code ON 出庫データ (コード);

CREATE TABLE IF NOT EXISTS 締め履歴 (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  対象月     VARCHAR(16) NOT NULL,
  コード     VARCHAR(255) NOT NULL,
  品名       VARCHAR(255),
  前月残数量 DOUBLE NOT NULL DEFAULT 0,
  当入数量   DOUBLE NOT NULL DEFAULT 0,
  当出数量   DOUBLE NOT NULL DEFAULT 0,
  当残数量   DOUBLE NOT NULL DEFAULT 0,
  締め日     VARCHAR(32),
  締め者     VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE INDEX idx_close_month ON 締め履歴 (対象月);

CREATE TABLE IF NOT EXISTS 操作ログ (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  操作種別     VARCHAR(64),
  対象テーブル VARCHAR(64),
  対象KEY      VARCHAR(255),
  操作者       VARCHAR(255),
  操作日時     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  変更内容     TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
