# 在庫管理システムを MySQL 対応で動かすまでにハマったこと

SQLite 製の在庫管理システムを本番（MySQL / MariaDB）対応にした際の記録。
デプロイ先は `https://debugprint.com/zaiko/`（`/virtual/pcm/public_html/debugprint.com/zaiko`）。

## 前提
- 本番DB: MariaDB 10.6.24 / DB名 `pcm_zaiko` / ユーザー `pcm_zaiko` / host `localhost` / port `3306`
- Web PHP: PHP 8.5.2（`/usr/local/bin/php85`、ユーザー `pcm`）
- 要件: SQLite（開発・テスト）と MySQL（本番）の両対応

## ハマったこと一覧

### 1. デフォルト `php` は古い
`php -v` は PHP 7.4（旧版）。Web は別系統の `/usr/local/bin/php85`（8.5.2）で動く。
CLI での確認・lint・テストは `php83cli` / `php85` を使うこと。

### 2. DB が空・スキーマなし
`pcm_zaiko` は接続できてもテーブルが一切なかった。
`db/schema_mysql.sql`（10テーブル）を作成して import した後、
`db_init_mysql()` が information_schema で採番テーブルの有無を見て、なければ schema を適用し、
採番（手配/出庫=0）と担当者（担当A〜E）を冪等投入するよう実装。

### 3. シークレットは公開ディレクトリ外へ
DB 接続情報を webroot 内に置くと読み取られるため、
`/virtual/pcm/.config/zaiko.db.php`（chmod 600）へ配置。
`config.php` が存在を検知して `DB_DRIVER=mysql` にする。

### 4. `db_init_mysql()` が `db()` を呼ぶ前に lib/db.php が未ロード
当初 `require lib/db.php` が `db_init()` より後だったため、
MySQL 初期化処理が `Call to undefined function db()` で落ちた。
→ `require lib/db.php` を `db_init()` より **前に** 移動して解決。

### 5. テスト環境が勝手に MySQL を向く
`tests/bootstrap.php` は `ZAIKO_DB_PATH` を設定する。
`config.php` 側では **`ZAIKO_DB_PATH` が設定されていれば sqlite を強制** しないと、
シークレットファイル（本番 DB 情報）が存在するせいでテストが MySQL へ接続してしまう。

### 6. サーバーの SQLite が古く `RETURNING` 非対応
採番の `next_番号()` で `INSERT ... RETURNING 連番` を書くと、
サーバーの古い SQLite が構文エラー（`Too many SQL variables` 系 / 構文非対応）で失敗。
PHPUnit 11 では「There is already an active transaction」なども絡んで動かなかった。
→ SQLite 側を **INSERT OR IGNORE → UPDATE → SELECT のトランザクション方式** に書き換え、
MySQL 側は `INSERT ... ON DUPLICATE KEY UPDATE 連番 = LAST_INSERT_ID(連番 + 1)` + `SELECT LAST_INSERT_ID()` を使い分け。
最終確認は PHPUnit 9.6.35（`/usr/local/bin/php83cli ~/bin/phpunit9.phar --configuration tests/phpunit.xml`）で
**98 tests / 260 assertions 全パス**。

### 7. ドライバで動きが変わる箇所の書き分け
- 接続: `app/lib/db.php`
  - MySQL: `charset=utf8mb4` + `PDO::ATTR_EMULATE_PREPARES=false`
  - SQLite: `PRAGMA foreign_keys` 等
- 採番: `app/lib/business.php` の `next_番号()`（上記 6）
- 表示名: `app/lib/util.php` の `db_label()` でフッター表記を切り替え

### 8. フッターの表記（ユーザー指摘で修正）
当初「在庫管理システム（pcm_zaiko版） v1.1.0」と DB 名を出していたが、
指示により「使用中DBの種別」を出す形へ変更。
`db_label()` が `DB_DRIVER === 'mysql' ? 'MySQL' : 'SQLite'` を返し、
フッターは「在庫管理システム（MySQL版） v1.1.0」等になる。

### 9. MySQL CLI スモークテスト
実データを投げて確認: `H0000000001 / H0000000002 / S0000000001` の採番、品目 CRUD、`add_stock`。
採番が MySQL 側で一意に連番されること（`ON DUPLICATE KEY UPDATE` + `LAST_INSERT_ID`）を検証。

### 10. デプロイ後のライブ確認
`curl -s -k "https://debugprint.com/zaiko/index.php?p=login"` が HTTP 200 で
`<title>担当者選択 | 在庫管理システム</title>`、フッターに「在庫管理システム（MySQL版） v1.1.0」が
出ることを確認。

## 現在の構成（動く状態）
- `config.php`: `DB_DRIVER` 切替（env / `ZAIKO_DB_PATH` / シークレットファイルで判定）
- `app/bootstrap.php`: `db_init()` → mysql なら `db_init_mysql()`（schema 自動作成 + 初期データ）
- `app/lib/db.php`: mysql / sqlite の二重 PDO
- `app/lib/business.php`: `next_番号()` のドライバ別実装
- `app/lib/util.php`: `db_label()`（MySQL版 / SQLite版）
- シークレット: `/virtual/pcm/.config/zaiko.db.php`（公開ディレクトリ外）
- テスト: PHPUnit 9.6.35（SQLite）で 98 tests パス
- E2E: `playwright.prod.config.js` で本番 URL に対して 4 passed（詳細は `docs/playwright-動作環境構築.md`）
