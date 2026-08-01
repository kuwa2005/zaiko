<?php
declare(strict_types=1);
$title = '出庫';

if (is_post()) {
    csrf_check($_POST['csrf'] ?? null);
    try {
        $id = outbound_register($_POST, current_担当者());
        flash('info', "出庫を登録しました。在庫残数量を自動更新しました。");
        redirect(url('outbound'));
    } catch (BizException $e) {
        flash('error', $e->getMessage());
    }
}

$items = items_list();
$customers = customer_list();
$rows = outbound_list();

require 'app/layouts/header.php';
?>
<form method="post" class="form-card">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <div class="form-grid">
    <div class="form-item">
      <label>コード *</label>
      <select name="コード" id="ob_code" required>
        <option value="">選択してください</option>
        <?php foreach ($items as $it): ?>
          <option value="<?= h($it['コード']) ?>" data-name="<?= h($it['品名']) ?>" data-qty="<?= h($it['残数量']) ?>">
            <?= h($it['コード']) ?>（<?= h($it['品名']) ?> / 残 <?= fmt_num($it['残数量']) ?><?= h($it['単位']) ?>）
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-item"><label>品名</label><input id="ob_name" readonly></div>
    <div class="form-item"><label>残数量</label><input id="ob_qty" readonly></div>
    <div class="form-item"><label>出庫数量 *</label><input type="number" id="ob_ship" name="出庫数" step="any" min="0.000001" required></div>
    <div class="form-item"><label>出庫先</label>
      <select name="出庫先">
        <option value="">（未設定）</option>
        <?php foreach ($customers as $cu): ?>
          <option value="<?= h($cu['出庫先名']) ?>"><?= h($cu['出庫先コード']) ?>：<?= h($cu['出庫先名']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-item"><label>備考</label><input name="備考"></div>
  </div>
  <div class="form-actions"><button class="btn btn-primary" type="submit">出庫を登録</button></div>
</form>

<h2 style="font-size:16px;margin:8px 0;">出庫一覧</h2>
<div class="table-scroll">
<table class="grid">
  <tr>
    <th>管理NO</th><th class="col-code">コード</th><th class="col-name">品名</th><th class="num">出庫数</th><th>出庫先</th><th>出庫者</th><th>出庫日</th><th>備考</th><th></th>
  </tr>
  <?php if (!$rows): ?>
    <tr><td colspan="9" class="hint">出庫実績がありません。</td></tr>
  <?php endif; ?>
  <?php foreach ($rows as $s): ?>
  <tr>
    <td><?= h($s['管理NO']) ?></td>
    <td class="col-code"><?= h($s['コード']) ?></td>
    <td class="col-name"><?= h($s['品名']) ?></td>
    <td class="num"><?= fmt_num($s['出庫数']) ?></td>
    <td><?= h($s['出庫先']) ?></td>
    <td><?= h($s['出庫者']) ?></td>
    <td><?= h(jdate($s['出庫日'])) ?></td>
    <td><?= h($s['備考']) ?></td>
    <td><a class="btn btn-sm" href="<?= url('outbound_edit', ['id' => $s['id']]) ?>">変更/削除</a></td>
  </tr>
  <?php endforeach; ?>
</table>
</div>

<script>
const sel = document.getElementById('ob_code');
const name = document.getElementById('ob_name');
const qty = document.getElementById('ob_qty');
const ship = document.getElementById('ob_ship');
function sync() {
  const opt = sel.selectedOptions[0];
  name.value = (opt && opt.dataset.name) ? opt.dataset.name : '';
  qty.value = (opt && opt.dataset.qty) ? opt.dataset.qty : '';
}
sel.addEventListener('change', sync);
sync();
</script>
<?php require 'app/layouts/footer.php'; ?>
