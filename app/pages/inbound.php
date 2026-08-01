<?php
declare(strict_types=1);
$title = '入庫';

if (is_post()) {
    csrf_check($_POST['csrf'] ?? null);
    $action = (string)($_POST['action'] ?? '');
    $marks = $_POST['入庫'] ?? [];
    try {
        if ($action === 'delete') {
            $n = inbound_delete($marks, current_担当者());
            flash('info', "発注を{$n}件削除しました。（論理削除）");
        } else {
            $r = inbound_save($marks, current_担当者());
            $msg = [];
            if ($r['入庫'] > 0) { $msg[] = "入庫{$r['入庫']}件"; }
            if ($r['取消'] > 0) { $msg[] = "入庫取消{$r['取消']}件"; }
            flash('info', '入庫処理を完了しました。' . ($msg ? '（' . implode(' / ', $msg) . '）' : ''));
        }
        redirect(url('inbound'));
    } catch (BizException $e) {
        flash('error', $e->getMessage());
    }
}

$rows = inbound_list();

require 'app/layouts/header.php';
?>
<p class="hint">内容欄で「入庫」「入庫取消」「削除」を選択して実行して下さい。入庫で在庫残数量へ自動加算されます。</p>

<form method="post" class="form-card">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <div class="table-scroll">
  <table class="grid">
    <tr>
      <th>管理NO</th><th>区分</th><th class="col-code">コード</th><th class="col-name">品名</th><th class="num">数量</th><th>納期</th>
      <th>依頼日</th><th>受付日</th><th>入庫日</th><th>ステータス</th><th>内容</th>
    </tr>
    <?php if (!$rows): ?>
      <tr><td colspan="11" class="hint">発注データがありません。</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $o): $in = $o['入庫日'] !== null; ?>
    <tr>
      <td><?= h($o['管理NO']) ?></td>
      <td><?= h(order_区分($o['管理NO'])) ?></td>
      <td class="col-code"><?= h($o['コード']) ?></td>
      <td class="col-name"><?= h($o['品名']) ?></td>
      <td class="num"><?= fmt_num($o['数量']) ?></td>
      <td><?= h(jdate($o['納期'])) ?></td>
      <td><?= h(jdate($o['依頼日'])) ?></td>
      <td><?= h(jdate($o['受付日'])) ?></td>
      <td><?= h(jdate($o['入庫日'])) ?></td>
      <td><span class="badge badge-<?= h($o['ステータス']) ?>"><?= h(order_status_label($o['ステータス'])) ?></span></td>
      <td class="radio-stack">
        <?php if (!$in): ?>
        <label class="radio-line"><input type="radio" name="入庫[<?= (int)$o['id'] ?>]" value="入庫"> 入庫</label>
        <?php endif; ?>
        <?php if ($in): ?>
        <label class="radio-line"><input type="radio" name="入庫[<?= (int)$o['id'] ?>]" value="入庫取消"> 入庫取消</label>
        <?php endif; ?>
        <label class="radio-line"><input type="radio" name="入庫[<?= (int)$o['id'] ?>]" value="削除"> 削除</label>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <div class="form-actions">
    <button class="btn btn-primary" type="submit" name="action" value="save">入庫 / 入庫取消を実行</button>
    <button class="btn btn-danger" type="submit" name="action" value="delete"
            onclick="return confirm('選択した発注を削除しますか？（入庫済みは在庫から減算されます）')">削除を実行</button>
  </div>
</form>
<?php require 'app/layouts/footer.php'; ?>
