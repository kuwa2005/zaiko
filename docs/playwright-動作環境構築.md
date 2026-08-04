# Playwright 本番E2E を動かすまでにやったこと

`https://debugprint.com/zaiko/` に対して Playwright で E2E テストを実行するまでの記録。
本番（MySQL）環境を直接操作するため、設定は `playwright.prod.config.js` を利用する。

## 前提
- OS: AlmaLinux 8.10（glibc 2.28）
- Playwright ブラウザ: `/virtual/pcm/.cache/ms-playwright/` 配下の
  `chromium_headless_shell-1234`（chrome-headless-shell）
- `sudo` 不可 → システムへパッケージをインストールできない
  → RPM をダウンロードし、ホームディレクトリへローカル展開して `LD_LIBRARY_PATH` で使う方針

## 手順

### 1. テスト基盤の準備
- `npm install`（@playwright/test 1.62.1）
- `playwright.prod.config.js` 作成
  - `baseURL: 'https://debugprint.com/zaiko/'`
  - `use.launchOptions.args: ['--no-sandbox', '--disable-dev-shm-usage']`（後述のサンドボックス対策）

### 2. Chromium の共有ライブラリ不足への対処
起動時に `libnspr4.so / libnss3.so / libnssutil3.so / libatk-bridge-2.0.so.0 / libgbm.so.1 / libasound.so.2 / libatspi.so.0`
などが `not found` で失敗。`ldd` で未解決を洗い出しながら RPM を追加した。

- `yumdownloader --disablerepo=pgdg14 <パッケージ名>` で RPM を取得
  - `pgdg14` は GPG 署名エラーで全体が失敗するため必ず `--disablerepo=pgdg14` を付ける
- `rpm2cpio <rpm> | cpio -idm` で展開し、`~/lib/chromium-deps/` に `.so*` を集約
- 作業は `/tmp` で行い、展開物と RPM は最後に削除（ホームへはコピー済み）

必要だったパッケージ（x86_64）:

| パッケージ | 入るライブラリ | 備考 |
| --- | --- | --- |
| nss / nspr | libnspr4, libnss3, libnssutil3(は nss-util)… | ベース |
| nss-util | libnssutil3.so | `nss` には含まれない |
| at-spi2-atk / at-spi2-core | libatk-bridge-2.0, libatspi | アクセシビリティ |
| mesa-libgbm | libgbm.so.1 | GPU 関連 |
| alsa-lib | libasound.so.2 | オーディオ（存在しなくても警告で済む場合あり） |
| libdrm | libdrm.so.2 | |
| libwayland-server-0 | libwayland-server.so.0 | `wayland` という名前では見つからない。`repoquery --whatprovides "libwayland-server.so.0()(64bit)"` で発見 |
| nss-softokn | libsoftokn3.so, libnssdbm3.so | **ldd では検出されない**（NSS が実行時に dlopen） |
| nss-softokn-freebl | libfreebl3.so, libfreeblpriv3.so | 同上。softokn の暗号プリミティブ |

#### ハマりポイント: `ldd` は「すべて解決」でも TLS 接続でクラッシュ
`ldd` で `not found` が 0 件になっても、`https://` へ接続するときだけ
`libsoftokn3.so: cannot open shared object file` → `FATAL:crypto/nss_util.cc:146 nss_error=-5925` でクラッシュした。
NSS 関連は **実行時の dlopen で解決**するため ldd に現れない。
`nss-softokn` / `nss-softokn-freebl` を追加するとエラーが -8023（freebl 不足）→ 解決、と段階的に直る。

確認は実バイナリで直接叩くのが確実:
```
LD_LIBRARY_PATH=~/lib/chromium-deps \
/virtual/pcm/.cache/ms-playwright/chromium_headless_shell-1234/chrome-headless-shell-linux64/chrome-headless-shell \
  --no-sandbox --disable-gpu --dump-dom "https://debugprint.com/zaiko/index.php?p=login"
```

### 3. サンドボックス問題
`--no-sandbox` なしでは起動直後に
`FATAL:zygote_host_impl_linux.cc:128 No usable sandbox!` で落ちる。
→ `playwright.prod.config.js` の `launchOptions.args` に `--no-sandbox` を追加。

### 4. baseURL のパスが消える問題
`page.goto('/index.php?p=login')` のように **先頭スラッシュ付き** の URL は
絶対パス扱いになり、baseURL `https://debugprint.com/zaiko/` の `/zaiko/` が消えて
`https://debugprint.com/index.php?p=login`（404）に遷移してしまう。
→ 先頭スラッシュを外した `page.goto('index.php?p=login')` にする。
path-relative として baseURL のパスに結合され `https://debugprint.com/zaiko/index.php?p=login` になる。
開発用 baseURL（`http://127.0.0.1:23111`、パスなし）でも解決先は同じで動作は不変。

### 5. 実行
```
export LD_LIBRARY_PATH=~/lib/chromium-deps
npx playwright test --config=playwright.prod.config.js
```

結果: 4 passed（受注→月次締め一連フロー / 発注先・出庫先マスタ連動 / 未ログイン遷移 / 発注依頼ヘルパー）

## 再実行時の注意
- `LD_LIBRARY_PATH=~/lib/chromium-deps` を忘れない
- ライブラリ追加が起きたら /tmp の一時展開物・RPM は必ず削除し、ホーム側にコピーして使う
