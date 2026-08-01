// Playwright E2E 設定：ローカルphpサーバ（テスト専用DB）で起動する
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/e2e',
  timeout: 60000,
  retries: 0,
  workers: 1,
  reporter: [['list']],
  use: {
    baseURL: 'http://127.0.0.1:23111',
    headless: true,
    viewport: { width: 1280, height: 900 },
    trace: 'retain-on-failure',
  },
  webServer: {
    command:
      'rm -f /tmp/zaiko_e2e.db /tmp/zaiko_e2e.db-wal /tmp/zaiko_e2e.db-shm ' +
      '&& ZAIKO_DB_PATH=/tmp/zaiko_e2e.db ZAIKO_DEBUG=1 php -S 127.0.0.1:23111 -t .',
    port: 23111,
    reuseExistingServer: false,
    timeout: 120000,
  },
});
