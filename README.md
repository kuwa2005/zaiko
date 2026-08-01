# 在庫管理システム

発注（手配）・受付・入庫・出庫・月次締めの一連の在庫業務をブラウザから行える在庫管理Webアプリです。

## 主な機能

- **担当者選択ログイン**（パスワードなしの内部ツール向けセッション管理）
- **在庫マスタ**（商品登録・変更・削除、安全在庫数・適正在庫数・最小発注数量の管理）
- **発注依頼（下書き）→ 確定**（管理NO は DB 自動採番 `H` + 10桁）
- **受付**（未受付一覧から受付/削除の一括処理）
- **入庫 / 入庫取消**（入庫時に在庫残数量へ自動加算）
- **出庫登録**（出庫管理NO `S` + 10桁、在庫残数量へ自動減算）
- **発注の分割**（数量・納期の2件分割）
- **月次集計・月次締め**（前月残/当入/当出/当残、期首残スナップショット）
- **警告一覧**（在庫0・安全在庫以下・発注予定）
- **操作ログ**（全書き込み操作の記録）

## 技術スタック

| 項目 | 内容 |
|------|------|
| 言語 | PHP 8.3（`strict_types`） |
| DB | SQLite（PDO。`prepare` + バインドによる全クエリ） |
| フロント | 素の HTML/CSS/JS（フレームワーク不使用） |
| テスト | PHPUnit（unit/integration）、Playwright（E2E） |

## 構成

```
app/
  bootstrap.php   セッション・DB初期化・共通読み込み
  layouts/        ヘッダー/フッター
  lib/
    db.php        PDO 接続・ヘルパー
    business.php  業務ロジック（採番・在庫・発注・入出庫・月次）
    util.php      CSRF・フラッシュ・操作ログ・h()エスケープ
    auth.php      担当者セッション管理
  pages/          各画面
assets/           CSS
config.php        アプリ定数（ZAIKO_DB_PATH でDB切替）
db/schema.sql     スキーマ定義
index.php         エントリポイント
scripts/
  seed_testdata.php     テストデータ登録（冪等）
  reset_production.php  テストデータ全消去（本番開始準備）
tests/            テスト一式
```

## セットアップ（開発）

```bash
# 要件: PHP 8.0+（pdo_sqlite 拡張）
php -S 127.0.0.1:8000 -t .
```

初回アクセス時に `db/zaiko.db` が自動生成され、スキーマ・採番・担当者（担当A〜E）が初期化されます。

テストデータを入れたい場合:

```bash
php scripts/seed_testdata.php
```

## テスト

```bash
# PHPUnit（unit + integration。テスト専用DB /tmp/zaiko_phpunit.db を使用）
phpunit --configuration tests/phpunit.xml

# Playwright E2E（テスト専用DBでローカルサーバを自動起動）
npm install
npm run test:e2e
```

## 本番デプロイ

- サーバ上のドキュメントルートへ `webapp/` の内容を配置します（`db/` は書込み可能に）。
- 環境変数 `ZAIKO_DB_PATH` で DB ファイルを切替可能（未設定時は `db/zaiko.db`）。
- `ZAIKO_DEBUG=1` を設定するとエラー表示が有効になります（本番では設定しない）。
- php-fpm 構成の例: `/var/www/<site>/zaiko` に配置し、リロードします。

本番運用開始時はテストデータを全消去します:

```bash
sudo -u www-data ZAIKO_DB_PATH=/var/www/<site>/zaiko/db/zaiko.db \
  php scripts/reset_production.php --yes
```

実行前に確認プロンプトが表示され、`db/backup/` へ自動バックアップされます（`--dry-run` で件数確認のみ）。

## セキュリティ対策

- **SQL インジェクション**: 全クエリを `prepare` + バインド。動的 `ORDER BY` はホワイトリストのみ。
- **XSS**: 全出力を `h()`（`htmlspecialchars`）でエスケープ。
- **CSRF**: 全 POST フォームで `random_bytes(32)` のトークンを `hash_equals` で検証。
- **セッション**: HttpOnly / SameSite=Lax、HTTPS 時は Secure、ログイン時に ID 再生成。
- **セキュリティヘッダ**: `X-Content-Type-Options` / `X-Frame-Options` / `Referrer-Policy`。
- **エラー表示**: 本番（`APP_DEBUG=false`）では `display_errors` を無効化。

## ライセンス

（未設定。内部利用を目的とした実装です）
