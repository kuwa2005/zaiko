<?php
declare(strict_types=1);
$title = '発注状況詳細';

$コード = (string)($_GET['コード'] ?? '');
$rows = $コード !== '' ? orders_by_code($コード) : [];

require 'app/layouts/header.php';
?>
<form method="get" class="form-card">
  <input type="hidden" name="p" value="order_detail">
  <div class="form-grid">
    <div class="form-item"><label>コード</label><input name="コード" value="<?= h($コード) ?>" placeholder="例: A001"></div>
    <div class="form-item"><label>&nbsp;</label><button class="btn" type="submit">検索</button></div>
  </div>
</form>

<?php if ($コード !== ''): ?>
<h2 style="font-size:16px;margin:8px 0;"><?= h($コード) ?> の発注履歴（<?= count($rows) ?>件）</h2>
<div class="table-scroll">
<table class="grid">
  <tr>
    <th>管理NO</th><th>区分</th><th class="col-name">品名</th><th class="num">数量</th><th>納期</th>
    <th>依頼日</th><th>受付日</th><th>入庫日</th><th>ステータス</th>
  </tr>
  <?php if (!$rows): ?>
    <tr><td colspan="9" class="hint">該当する発注履歴がありません。</td></tr>
  <?php endif; ?>
  <?php foreach ($rows as $o): ?>
  <tr>
    <td><?= h($o['管理NO']) ?></td>
    <td><?= h(order_区分($o['管理NO'])) ?></td>
    <td class="col-name"><?= h($o['品名']) ?></td>
    <td class="num"><?= fmt_num($o['数量']) ?></td>
    <td><?= h(jdate($o['納期'])) ?></td>
    <td><?= h(jdate($o['依頼日'])) ?></td>
    <td><?= h(jdate($o['受付日'])) ?></td>
    <td><?= h(jdate($o['入庫日'])) ?></td>
    <td><span class="badge badge-<?= h($o['ステータス']) ?>"><?= h(order_status_label($o['ステータス'])) ?></span></td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<?php endif; ?>
<?php require 'app/layouts/footer.php'; ?>
