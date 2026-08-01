<?php
declare(strict_types=1);
$title = '受付';

if (is_post()) {
    csrf_check($_POST['csrf'] ?? null);
    $marks = $_POST['受付'] ?? [];
    try {
        $r = receive_process($marks, current_担当者());
        $msg = [];
        if ($r['出力'] > 0) { $msg[] = "受付{$r['出力']}件"; }
        if ($r['削除'] > 0) { $msg[] = "削除{$r['削除']}件"; }
        flash('info', '受付処理を完了しました。' . ($msg ? '（' . implode(' / ', $msg) . '）' : ''));
        redirect(url('receive'));
    } catch (BizException $e) {
        flash('error', $e->getMessage());
    }
}

$rows = receive_list();

require 'app/layouts/header.php';
?>
<p class="hint">未受付の受注に対して「受付」または「削除」を選択して下さい。</p>

<form method="post" class="form-card">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <div class="table-scroll">
  <table class="grid">
    <tr>
      <th>管理NO</th><th class="col-code">コード</th><th class="col-name">品名</th><th class="num">数量</th><th>納期</th><th>依頼者</th><th>依頼日</th><th>内容</th>
    </tr>
    <?php if (!$rows): ?>
      <tr><td colspan="8" class="hint">受付対象のデータがありません。</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $o): ?>
    <tr>
      <td><?= h($o['管理NO']) ?></td>
      <td class="col-code"><?= h($o['コード']) ?></td>
      <td class="col-name"><?= h($o['品名']) ?></td>
      <td class="num"><?= fmt_num($o['数量']) ?></td>
      <td><?= h(jdate($o['納期'])) ?></td>
      <td><?= h($o['依頼者']) ?></td>
      <td><?= h(jdate($o['依頼日'])) ?></td>
      <td class="radio-stack">
        <label class="radio-line"><input type="radio" name="受付[<?= (int)$o['id'] ?>]" value="出力"> 受付</label>
        <label class="radio-line"><input type="radio" name="受付[<?= (int)$o['id'] ?>]" value="削除"> 削除</label>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <div class="form-actions">
    <button class="btn btn-primary" type="submit">受付を実行</button>
  </div>
</form>
<?php require 'app/layouts/footer.php'; ?>
