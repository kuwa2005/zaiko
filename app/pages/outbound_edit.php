<?php
declare(strict_types=1);
$title = '出庫変更/削除';

$id = (int)($_GET['id'] ?? 0);
$s = outbound_find($id);
if ($s === null) {
    http_response_code(404);
    require 'app/layouts/header.php';
    echo '<p>対象データが見つかりません。</p>';
    echo '<p><a class="btn" href="' . url('outbound') . '">出庫へ戻る</a></p>';
    require 'app/layouts/footer.php';
    exit;
}

if (is_post()) {
    csrf_check($_POST['csrf'] ?? null);
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'delete') {
            outbound_delete($id, current_担当者());
            flash('info', '出庫データを削除しました。在庫残数量へ戻しました。');
            redirect(url('outbound'));
        } else {
            outbound_update($id, $_POST, current_担当者());
            flash('info', '出庫データを変更しました。在庫残数量を調整しました。');
            redirect(url('outbound_edit', ['id' => $id]));
        }
    } catch (BizException $e) {
        flash('error', $e->getMessage());
    }
}

$item = item_find_by_code((string)$s['コード']);
$current = $item ? (float)$item['残数量'] + (float)$s['出庫数'] : 0.0;

require 'app/layouts/header.php';
?>
<?php $customers = customer_list(); $出庫先名 = (string)$s['出庫先']; ?>
<div class="form-card">
  <p>
    管理NO: <strong><?= h($s['管理NO']) ?></strong> /
    コード: <?= h($s['コード']) ?> / 品名: <?= h($s['品名']) ?> /
    現在の残数量（出庫前）: <?= fmt_num($current) ?>
  </p>
</div>

<form method="post" class="form-card">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="update">
  <div class="form-grid">
    <div class="form-item"><label>出庫数量 *</label><input type="number" name="出庫数" step="any" min="0.000001" value="<?= h($s['出庫数']) ?>" required></div>
    <div class="form-item"><label>出庫先</label>
      <select name="出庫先">
        <option value="">（未設定）</option>
        <?php if ($出庫先名 !== '' && !in_array($出庫先名, array_column($customers, '出庫先名'), true)): ?>
          <option value="<?= h($出庫先名) ?>" selected><?= h($出庫先名) ?>（マスタ外）</option>
        <?php endif; ?>
        <?php foreach ($customers as $cu): ?>
          <option value="<?= h($cu['出庫先名']) ?>" <?= $出庫先名 === $cu['出庫先名'] ? 'selected' : '' ?>><?= h($cu['出庫先コード']) ?>：<?= h($cu['出庫先名']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-item"><label>備考</label><input name="備考" value="<?= h($s['備考']) ?>"></div>
  </div>
  <div class="form-actions">
    <button class="btn btn-primary" type="submit">変更を保存</button>
    <a class="btn" href="<?= url('outbound') ?>">戻る</a>
  </div>
</form>

<form method="post" class="form-card" onsubmit="return confirm('この出庫データを削除しますか？（在庫残数量へ戻します）');">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="delete">
  <button class="btn btn-danger" type="submit">この出庫を削除</button>
</form>
<?php require 'app/layouts/footer.php'; ?>
