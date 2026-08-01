<?php
declare(strict_types=1);
$title = '出庫先マスタ';

if (is_post()) {
    csrf_check($_POST['csrf'] ?? null);
    try {
        customer_register($_POST, current_担当者());
        flash('info', '出庫先マスタに登録しました。');
        redirect(url('customers'));
    } catch (BizException $e) {
        flash('error', $e->getMessage());
    }
}

$customers = customer_list();

require 'app/layouts/header.php';
?>
<p class="hint">出庫先（販売先・小売店）を管理します。出庫登録の選択肢として使われます。</p>

<form method="post" class="form-card" onsubmit="return confirm('この内容で出庫先マスタに登録しますか？');">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <div class="form-grid">
    <div class="form-item"><label>出庫先コード *</label><input name="出庫先コード" required></div>
    <div class="form-item"><label>出庫先名 *</label><input name="出庫先名" required></div>
    <div class="form-item"><label>住所</label><input name="住所"></div>
    <div class="form-item"><label>電話番号</label><input name="電話番号"></div>
    <div class="form-item"><label>担当者</label><input name="担当者"></div>
    <div class="form-item"><label>備考</label><input name="備考"></div>
  </div>
  <div class="form-actions"><button class="btn btn-primary" type="submit">登録</button></div>
</form>

<h2 style="font-size:16px;margin:8px 0;">登録済みの出庫先（<?= count($customers) ?>件）</h2>
<div class="table-scroll">
<table class="grid">
  <tr>
    <th>出庫先コード</th><th>出庫先名</th><th>住所</th><th>電話番号</th><th>担当者</th><th>備考</th><th></th>
  </tr>
  <?php if (!$customers): ?>
    <tr><td colspan="7" class="hint">出庫先が未登録です。上のフォームから登録して下さい。</td></tr>
  <?php endif; ?>
  <?php foreach ($customers as $c): ?>
  <tr>
    <td><?= h($c['出庫先コード']) ?></td>
    <td><?= h($c['出庫先名']) ?></td>
    <td><?= h($c['住所']) ?></td>
    <td><?= h($c['電話番号']) ?></td>
    <td><?= h($c['担当者']) ?></td>
    <td><?= h($c['備考']) ?></td>
    <td><a class="btn btn-sm" href="<?= url('customer_edit', ['id' => $c['id']]) ?>">変更/削除</a></td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<?php require 'app/layouts/footer.php'; ?>
