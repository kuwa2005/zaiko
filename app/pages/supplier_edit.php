<?php
declare(strict_types=1);
$title = '発注先マスタ変更/削除';

$id = (int)($_GET['id'] ?? 0);
$s = supplier_find($id);
if ($s === null) {
    http_response_code(404);
    require 'app/layouts/header.php';
    echo '<p>対象データが見つかりません。</p>';
    echo '<p><a class="btn" href="' . url('suppliers') . '">発注先マスタへ戻る</a></p>';
    require 'app/layouts/footer.php';
    exit;
}

if (is_post()) {
    csrf_check($_POST['csrf'] ?? null);
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'delete') {
            supplier_delete($id, current_担当者());
            flash('info', '発注先マスタを削除しました。（論理削除）');
            redirect(url('suppliers'));
        } else {
            supplier_update($id, $_POST, current_担当者());
            flash('info', '発注先マスタを変更しました。');
            redirect(url('supplier_edit', ['id' => $id]));
        }
    } catch (BizException $e) {
        flash('error', $e->getMessage());
    }
}

require 'app/layouts/header.php';
?>
<form method="post" class="form-card">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="update">
  <div class="form-grid">
    <div class="form-item"><label>発注先コード *</label><input name="発注先コード" value="<?= h($s['発注先コード']) ?>" required></div>
    <div class="form-item"><label>発注先名 *</label><input name="発注先名" value="<?= h($s['発注先名']) ?>" required></div>
    <div class="form-item"><label>住所</label><input name="住所" value="<?= h($s['住所']) ?>"></div>
    <div class="form-item"><label>電話番号</label><input name="電話番号" value="<?= h($s['電話番号']) ?>"></div>
    <div class="form-item"><label>担当者</label><input name="担当者" value="<?= h($s['担当者']) ?>"></div>
    <div class="form-item"><label>備考</label><input name="備考" value="<?= h($s['備考']) ?>"></div>
  </div>
  <div class="form-actions">
    <button class="btn btn-primary" type="submit">変更を保存</button>
    <a class="btn" href="<?= url('suppliers') ?>">戻る</a>
  </div>
</form>

<form method="post" class="form-card" onsubmit="return confirm('この発注先マスタを削除しますか？（論理削除）');">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="delete">
  <button class="btn btn-danger" type="submit">この発注先を削除</button>
</form>

<p class="hint">登録日: <?= h(jdate($s['登録日'])) ?> / 更新日: <?= h(jdate($s['更新日'])) ?></p>
<?php require 'app/layouts/footer.php'; ?>
