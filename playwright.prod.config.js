// 本番確認用 Playwright 設定：https://debugprint.com/zaiko/ を直接操作する
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/e2e',
  timeout: 60000,
  retries: 0,
  workers: 1,
  reporter: [['list']],
  use: {
    baseURL: 'https://debugprint.com/zaiko/',
    headless: true,
    launchOptions: {
      args: ['--no-sandbox', '--disable-dev-shm-usage'],
    },
    viewport: { width: 1280, height: 900 },
    trace: 'retain-on-failure',
  },
});
