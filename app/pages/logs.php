<?php
declare(strict_types=1);
$title = '操作ログ';

$操作者 = (string)($_GET['操作者'] ?? '');
$logs = db_all(
    "SELECT * FROM 操作ログ
     WHERE (? = '' OR 操作者 = ?)
     ORDER BY id DESC LIMIT 500",
    [$操作者, $操作者]
);

require 'app/layouts/header.php';
?>
<form method="get" class="form-card">
  <input type="hidden" name="p" value="logs">
  <div class="form-grid">
    <div class="form-item"><label>操作者</label><input name="操作者" value="<?= h($操作者) ?>"></div>
    <div class="form-item"><label>&nbsp;</label><button class="btn" type="submit">絞り込み</button></div>
  </div>
</form>

<div class="table-scroll">
<table class="grid">
  <tr>
    <th>操作日時</th><th>操作者</th><th>操作種別</th><th>対象</th><th>対象KEY</th><th>変更内容</th>
  </tr>
  <?php if (!$logs): ?>
    <tr><td colspan="6" class="hint">操作ログがありません。</td></tr>
  <?php endif; ?>
  <?php foreach ($logs as $l): ?>
  <tr>
    <td><?= h($l['操作日時']) ?></td>
    <td><?= h($l['操作者']) ?></td>
    <td><?= h($l['操作種別']) ?></td>
    <td><?= h($l['対象テーブル']) ?></td>
    <td><?= h($l['対象KEY']) ?></td>
    <td><?= h($l['変更内容']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<?php require 'app/layouts/footer.php'; ?>
