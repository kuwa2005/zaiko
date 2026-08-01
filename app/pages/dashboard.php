<?php
declare(strict_types=1);
$title = 'ダッシュボード';

$items = db_all("SELECT * FROM 在庫マスタ WHERE 削除フラグ = 0");
$itemCount = count($items);

$warnings = warning_list();
$orderCount = (int)db_val("SELECT COUNT(*) FROM 発注データ WHERE 削除フラグ=0 AND 入庫日 IS NULL");
$shipCount = (int)db_val("SELECT COUNT(*) FROM 出庫データ WHERE 削除フラグ=0");
$today = today();
$todayIn  = (int)db_val("SELECT COUNT(*) FROM 発注データ WHERE 入庫日 = ?", [$today]);
$todayOut = (int)db_val("SELECT COUNT(*) FROM 出庫データ WHERE 出庫日 = ?", [$today]);

require 'app/layouts/header.php';
?>
<div class="summary-cards">
  <div class="summary-card"><div class="label">在庫品目</div><div class="value"><?= $itemCount ?></div></div>
  <div class="summary-card"><div class="label">未入庫の発注</div><div class="value"><?= $orderCount ?></div></div>
  <div class="summary-card"><div class="label">本日の入庫</div><div class="value"><?= $todayIn ?></div></div>
  <div class="summary-card"><div class="label">本日の出庫</div><div class="value"><?= $todayOut ?></div></div>
  <div class="summary-card"><div class="label">出庫実績</div><div class="value"><?= $shipCount ?></div></div>
</div>

<?php if ($warnings): ?>
<h2 style="font-size:16px;margin:8px 0;">発注・確認が必要な在庫</h2>
<div class="table-scroll">
<table class="grid">
  <tr><th class="col-code">コード</th><th class="col-name">品名</th><th class="num">残数量</th><th class="num">安全在庫数</th><th>警告</th><th></th></tr>
  <?php foreach ($warnings as $w):
      $msg = (float)$w['残数量'] <= 0 ? '在庫が0です。確認して下さい。' : '安全在庫以下です。発注して下さい。'; ?>
  <tr>
    <td class="col-code"><?= h($w['コード']) ?></td>
    <td class="col-name"><?= h($w['品名']) ?></td>
    <td class="num"><?= fmt_num($w['残数量']) ?></td>
    <td class="num"><?= fmt_num($w['安全在庫数']) ?></td>
    <td><?= h($msg) ?></td>
    <td><a class="btn btn-sm" href="<?= url('draft', ['コード' => $w['コード']]) ?>">発注依頼</a></td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<?php endif; ?>
<?php require 'app/layouts/footer.php'; ?>
