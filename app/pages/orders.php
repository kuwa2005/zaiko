<?php
declare(strict_types=1);
$title = '発注状況確認';

$orderBy = (string)($_GET['sort'] ?? '依頼日_desc');
$status = (string)($_GET['status'] ?? '');

$orders = orders_list($orderBy);
if ($status !== '') {
    $orders = array_values(array_filter($orders, fn($o) => ($o['ステータス'] ?? '') === $status));
}

require 'app/layouts/header.php';
?>
<form method="get" class="form-card">
  <input type="hidden" name="p" value="orders">
  <div class="form-grid">
    <div class="form-item">
      <label>並び順</label>
      <select name="sort">
        <option value="依頼日_desc" <?= $orderBy === '依頼日_desc' ? 'selected' : '' ?>>依頼日 降順</option>
        <option value="管理NO_desc" <?= $orderBy === '管理NO_desc' ? 'selected' : '' ?>>管理NO 降順</option>
        <option value="管理NO" <?= $orderBy === '管理NO' ? 'selected' : '' ?>>管理NO 昇順</option>
        <option value="コード" <?= $orderBy === 'コード' ? 'selected' : '' ?>>コード</option>
      </select>
    </div>
    <div class="form-item">
      <label>ステータス</label>
      <select name="status">
        <option value="">すべて</option>
        <?php foreach (['未受付', '受付済', '入庫済', '分割済'] as $s): ?>
          <option value="<?= h($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= h($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-item"><label>&nbsp;</label><button class="btn" type="submit">絞り込み</button></div>
  </div>
</form>

<div class="table-scroll">
<table class="grid">
  <tr>
    <th>管理NO</th><th>区分</th><th class="col-code">コード</th><th class="col-name">品名</th><th class="num">数量</th><th>納期</th><th>発注先</th>
    <th>依頼者</th><th>依頼日</th><th>受付日</th><th>入庫日</th><th>ステータス</th><th></th>
  </tr>
  <?php if (!$orders): ?>
    <tr><td colspan="13" class="hint">発注データがありません。</td></tr>
  <?php endif; ?>
  <?php foreach ($orders as $o): ?>
  <tr>
    <td><?= h($o['管理NO']) ?></td>
    <td><?= h(order_区分($o['管理NO'])) ?></td>
    <td class="col-code"><?= h($o['コード']) ?></td>
    <td class="col-name"><?= h($o['品名']) ?></td>
    <td class="num"><?= fmt_num($o['数量']) ?></td>
    <td><?= h(jdate($o['納期'])) ?></td>
    <td><?= h($o['発注先']) ?></td>
    <td><?= h($o['依頼者']) ?></td>
    <td><?= h(jdate($o['依頼日'])) ?></td>
    <td><?= h(jdate($o['受付日'])) ?></td>
    <td><?= h(jdate($o['入庫日'])) ?></td>
    <td><span class="badge badge-<?= h($o['ステータス']) ?>"><?= h(order_status_label($o['ステータス'])) ?></span></td>
    <td>
      <a class="btn btn-sm" href="<?= url('order_detail', ['コード' => $o['コード']]) ?>">詳細</a>
      <?php if ($o['入庫日'] === null && $o['ステータス'] !== '分割済'): ?>
      <a class="btn btn-sm" href="<?= url('order_split', ['id' => $o['id']]) ?>">分割</a>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<?php require 'app/layouts/footer.php'; ?>
