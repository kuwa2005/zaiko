<?php
declare(strict_types=1);
$title = '手配分割';

$id = (int)($_GET['id'] ?? 0);
$o = order_find($id);

if ($o === null) {
    http_response_code(404);
    require 'app/layouts/header.php';
    echo '<p>対象データが見つかりません。</p>';
    echo '<p><a class="btn" href="' . url('orders') . '">発注状況へ戻る</a></p>';
    require 'app/layouts/footer.php';
    exit;
}
if ($o['入庫日'] !== null) {
    flash('error', '入庫済みデータは分割できません。');
    redirect(url('orders'));
}

if (is_post()) {
    csrf_check($_POST['csrf'] ?? null);
    try {
        $n = order_split($id, $_POST, current_担当者());
        flash('info', "手配を{$n}分割しました。");
        redirect(url('orders'));
    } catch (BizException $e) {
        flash('error', $e->getMessage());
    }
}

require 'app/layouts/header.php';
?>
<div class="form-card">
  <div class="table-scroll">
  <table class="grid">
    <tr><th>管理NO</th><th class="col-code">コード</th><th class="col-name">品名</th><th class="num">手配数量</th><th>納期</th></tr>
    <tr>
      <td><?= h($o['管理NO']) ?></td>
      <td class="col-code"><?= h($o['コード']) ?></td>
      <td class="col-name"><?= h($o['品名']) ?></td>
      <td class="num"><?= fmt_num($o['数量']) ?></td>
      <td><?= h(jdate($o['納期'])) ?></td>
    </tr>
  </table>
  </div>
  <p class="hint">分割後の合計 = 手配数量（<?= fmt_num($o['数量']) ?>）になるように入力して下さい。</p>
</div>

<form method="post" class="form-card" onsubmit="return confirm('この内容で手配を分割しますか？');">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <table class="grid">
    <tr><th>分割</th><th>数量</th><th>納期</th></tr>
    <?php foreach (['1', '2', '3'] as $n): ?>
    <tr>
      <td>分割<?= h($n) ?></td>
      <td><input type="number" name="数量<?= h($n) ?>" step="any"></td>
      <td><input type="date" name="納期<?= h($n) ?>"></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <div class="form-actions">
    <button class="btn btn-primary" type="submit">分割を確定</button>
    <a class="btn" href="<?= url('orders') ?>">戻る</a>
  </div>
</form>
<?php require 'app/layouts/footer.php'; ?>
