// E2E: 在庫登録 → 発注依頼 → 確定 → 受付 → 入庫 → 出庫 → 月次締め → 操作ログ の一連フロー
const { test, expect } = require('@playwright/test');

const CODE = 'E2E001';
const NAME = 'E2Eテスト品目';

test.beforeEach(async ({ page }) => {
  page.on('dialog', (d) => d.accept());
});

async function login(page) {
  await page.goto('/index.php?p=login');
  await expect(page.locator('h1')).toHaveText('担当者選択');
  await page.selectOption('select[name="担当者"]', { label: '担当A' });
  await page.getByRole('button', { name: 'ログイン' }).click();
  await page.waitForURL(/p=dashboard/);
}

test('受注から月次締めまでの一連の業務フロー', async ({ page }) => {
  await login(page);

  // 1. 在庫マスタへ登録
  await page.getByRole('link', { name: '在庫マスタ' }).click();
  await page.waitForURL(/p=items/);
  await page.fill('input[name="コード"]', CODE);
  await page.fill('input[name="品名"]', NAME);
  await page.fill('input[name="基本数量"]', '1');
  await page.fill('input[name="単位"]', '個');
  await page.fill('input[name="単価"]', '100');
  await page.getByRole('button', { name: '登録' }).click();
  await expect(page.locator('table.grid')).toContainText(CODE);

  // 2. 発注依頼の下書きを追加
  await page.getByRole('link', { name: '発注依頼' }).click();
  await page.waitForURL(/p=draft/);
  await page.selectOption('#draft_code', CODE);
  await page.fill('input[name="数量"]', '10');
  await page.getByRole('button', { name: '下書きに追加' }).click();
  await expect(page.locator('.alert-info')).toContainText('下書きを追加しました');

  // 3. 下書きを確定
  await page.getByRole('button', { name: /発注依頼を確定/ }).click();
  await expect(page.locator('.alert-info')).toContainText('確定しました');

  // 4. 発注状況に未受付の H0000000001 が表示される
  await page.getByRole('link', { name: '発注状況' }).click();
  await page.waitForURL(/p=orders/);
  const orderRow = page.locator('tr', { hasText: 'H0000000001' });
  await expect(orderRow).toContainText('未受付');

  // 5. 受付
  await page.getByRole('link', { name: '受付' }).click();
  await page.waitForURL(/p=receive/);
  await page
    .locator('tr', { hasText: 'H0000000001' })
    .locator('input[name^="受付["][value="出力"]')
    .check();
  await page.getByRole('button', { name: '受付を実行' }).click();
  await expect(page.locator('.alert-info')).toContainText('受付処理を完了しました');

  // 6. 入庫
  await page.getByRole('link', { name: '入庫' }).click();
  await page.waitForURL(/p=inbound/);
  await page
    .locator('tr', { hasText: 'H0000000001' })
    .locator('input[name^="入庫["][value="入庫"]')
    .check();
  await page.getByRole('button', { name: '入庫 / 入庫取消を実行' }).click();
  await expect(page.locator('.alert-info')).toContainText('入庫処理を完了しました');

  // 7. 出庫（3個）
  await page.getByRole('link', { name: '出庫', exact: true }).click();
  await page.waitForURL(/p=outbound/);
  await page.selectOption('#ob_code', CODE);
  await page.fill('#ob_ship', '3');
  await page.getByRole('button', { name: '出庫を登録' }).click();
  await expect(page.locator('.alert-info')).toContainText('出庫を登録しました');
  const shipRow = page.locator('tr', { hasText: 'S0000000001' });
  await expect(shipRow).toContainText('3');

  // 8. 月次集計の数値確認（入10 / 出3 / 残7）と締め
  await page.getByRole('link', { name: '月次集計' }).click();
  await page.waitForURL(/p=monthly/);
  const monthlyRow = page.locator('tr', { hasText: CODE }).first();
  await expect(monthlyRow.locator('td').nth(6)).toHaveText('10'); // 当月入庫
  await expect(monthlyRow.locator('td').nth(8)).toHaveText('3');  // 当月出庫
  await expect(monthlyRow.locator('td').nth(10)).toHaveText('7'); // 当月残
  await page.getByRole('button', { name: /を締める/ }).click();
  await expect(page.locator('.alert-info')).toContainText('月次締めを実行しました');
  await expect(page.locator('h2', { hasText: '締め履歴' })).toBeVisible();

  // 9. 操作ログに一連の操作が記録されている
  await page.getByRole('link', { name: '操作ログ' }).click();
  await page.waitForURL(/p=logs/);
  for (const logType of ['在庫', '発注依頼確定', '受付確定', '入庫', '出庫登録', '月次締め']) {
    await expect(page.locator('table.grid')).toContainText(logType, { timeout: 10000 });
  }
});

test('発注先・出庫先マスタの登録と選択式への連動', async ({ page }) => {
  await login(page);

  // 発注先登録
  await page.getByRole('link', { name: '発注先', exact: true }).click();
  await page.waitForURL(/p=suppliers/);
  await page.fill('input[name="発注先コード"]', 'SP900');
  await page.fill('input[name="発注先名"]', 'E2Eメーカー');
  await page.getByRole('button', { name: '登録' }).click();
  await expect(page.locator('table.grid')).toContainText('E2Eメーカー');

  // 出庫先登録
  await page.getByRole('link', { name: '出庫先', exact: true }).click();
  await page.waitForURL(/p=customers/);
  await page.fill('input[name="出庫先コード"]', 'ST900');
  await page.fill('input[name="出庫先名"]', 'E2E書店');
  await page.getByRole('button', { name: '登録' }).click();
  await expect(page.locator('table.grid')).toContainText('E2E書店');

  // 在庫マスタに取引先付きで登録
  await page.getByRole('link', { name: '在庫マスタ' }).click();
  await page.waitForURL(/p=items/);
  await page.fill('input[name="コード"]', 'E2E003');
  await page.fill('input[name="品名"]', 'E2E連動品目');
  await page.fill('input[name="基本数量"]', '1');
  await page.fill('input[name="単位"]', '個');
  await page.fill('input[name="単価"]', '100');
  await page.fill('input[name="残数量"]', '5');
  await page.selectOption('select[name="取引先"]', 'E2Eメーカー');
  await page.getByRole('button', { name: '登録' }).click();
  await expect(page.locator('table.grid')).toContainText('E2E003');

  // 発注依頼でコード選択時に発注先が自動セットされる
  await page.getByRole('link', { name: '発注依頼' }).click();
  await page.waitForURL(/p=draft/);
  await page.selectOption('#draft_code', 'E2E003');
  await expect(page.locator('#draft_supplier')).toHaveValue('E2Eメーカー');

  // 出庫で出庫先を選択して登録し、一覧に出庫先が表示される
  await page.getByRole('link', { name: '出庫', exact: true }).click();
  await page.waitForURL(/p=outbound/);
  await page.selectOption('#ob_code', 'E2E003');
  await page.fill('#ob_ship', '1');
  await page.selectOption('select[name="出庫先"]', 'E2E書店');
  await page.getByRole('button', { name: '出庫を登録' }).click();
  await expect(page.locator('.alert-info')).toContainText('出庫を登録しました');
  await expect(page.locator('table.grid')).toContainText('E2E書店');
});

test('未ログインならログイン画面へリダイレクトされる', async ({ page }) => {
  await page.goto('/index.php?p=dashboard');
  await page.waitForURL(/p=login/);
  await expect(page.locator('h1')).toHaveText('担当者選択');
});

test('発注依頼: 最小発注数の自動セットと推奨数量ヘルパー', async ({ page }) => {
  await login(page);

  // 在庫マスタに最小発注数・適正在庫数付きで登録
  await page.getByRole('link', { name: '在庫マスタ' }).click();
  await page.waitForURL(/p=items/);
  await page.fill('input[name="コード"]', 'E2E002');
  await page.fill('input[name="品名"]', 'E2E推奨品目');
  await page.fill('input[name="基本数量"]', '1');
  await page.fill('input[name="単位"]', '個');
  await page.fill('input[name="単価"]', '100');
  await page.fill('input[name="残数量"]', '6');
  await page.fill('input[name="最小発注数量"]', '5');
  await page.fill('input[name="適正在庫数"]', '20');
  await page.getByRole('button', { name: '登録' }).click();
  await expect(page.locator('table.grid')).toContainText('E2E002');

  // 発注依頼ページへ
  await page.getByRole('link', { name: '発注依頼' }).click();
  await page.waitForURL(/p=draft/);

  // 未選択時: 品名は空（undefined が出ない）
  await expect(page.locator('#draft_name')).toHaveValue('');
  await expect(page.locator('#draft_help')).toBeHidden();

  // コード選択で最小発注数が自動セットされ、ヘルパーに参考値が表示される
  await page.selectOption('#draft_code', 'E2E002');
  await expect(page.locator('#draft_name')).toHaveValue('E2E推奨品目');
  await expect(page.locator('#draft_qty')).toHaveValue('5');
  await expect(page.locator('#help_stock')).toHaveText('6 個');
  await expect(page.locator('#help_min')).toHaveText('5 個');
  await expect(page.locator('#help_target')).toHaveText('20 個');
  await expect(page.locator('#help_rec')).toHaveText('14 個');

  // 推奨数量を数量へ反映できる
  await page.getByRole('button', { name: '推奨数量を数量にセット' }).click();
  await expect(page.locator('#draft_qty')).toHaveValue('14');

  // 未選択に戻すと品名は空
  await page.selectOption('#draft_code', '');
  await expect(page.locator('#draft_name')).toHaveValue('');
  await expect(page.locator('#draft_help')).toBeHidden();
});

