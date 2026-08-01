<?php
declare(strict_types=1);
$title = '在庫マスタ変更/削除';

$id = (int)($_GET['id'] ?? 0);
$item = item_find($id);
if ($item === null) {
    http_response_code(404);
    require 'app/layouts/header.php';
    echo '<p>対象データが見つかりません。</p>';
    echo '<p><a class="btn" href="' . url('items') . '">在庫マスタへ戻る</a></p>';
    require 'app/layouts/footer.php';
    exit;
}

if (is_post()) {
    csrf_check($_POST['csrf'] ?? null);
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'delete') {
            item_delete($id, current_担当者());
            flash('info', '在庫マスタを削除しました。（論理削除）');
            redirect(url('items'));
        } else {
            item_update($id, $_POST, current_担当者());
            flash('info', '在庫マスタを変更しました。');
            redirect(url('item_edit', ['id' => $id]));
        }
    } catch (BizException $e) {
        flash('error', $e->getMessage());
    }
}

require 'app/layouts/header.php';
?>
<?php $suppliers = supplier_list(); $取引先名 = (string)$item['取引先']; ?>
<form method="post" class="form-card">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="update">
  <div class="form-grid">
    <div class="form-item"><label>コード *</label><input name="コード" value="<?= h($item['コード']) ?>" required></div>
    <div class="form-item"><label>品名 *</label><input name="品名" value="<?= h($item['品名']) ?>" required></div>
    <div class="form-item"><label>基本数量 *</label><input type="number" name="基本数量" step="any" value="<?= h($item['基本数量']) ?>" required></div>
    <div class="form-item"><label>単位 *</label><input name="単位" value="<?= h($item['単位']) ?>" required></div>
    <div class="form-item"><label>単価 *</label><input type="number" name="単価" step="any" value="<?= h($item['単価']) ?>" required></div>
    <div class="form-item"><label>残数量</label><input type="number" name="残数量" step="any" value="<?= h($item['残数量']) ?>"></div>
    <div class="form-item"><label>安全在庫数</label><input type="number" name="安全在庫数" step="any" value="<?= h($item['安全在庫数']) ?>"></div>
    <div class="form-item"><label>最小発注数量</label><input type="number" name="最小発注数量" step="any" value="<?= h($item['最小発注数量']) ?>"></div>
    <div class="form-item"><label>適正在庫数</label><input type="number" name="適正在庫数" step="any" value="<?= h($item['適正在庫数']) ?>"></div>
    <div class="form-item"><label>標準納入日数</label><input type="number" name="標準納入日数" value="<?= h($item['標準納入日数']) ?>"></div>
    <div class="form-item"><label>棚番</label><input name="棚番" value="<?= h($item['棚番']) ?>"></div>
    <div class="form-item"><label>取引先</label>
      <select name="取引先">
        <option value="">（未設定）</option>
        <?php if ($取引先名 !== '' && !in_array($取引先名, array_column($suppliers, '発注先名'), true)): ?>
          <option value="<?= h($取引先名) ?>" selected><?= h($取引先名) ?>（マスタ外）</option>
        <?php endif; ?>
        <?php foreach ($suppliers as $sp): ?>
          <option value="<?= h($sp['発注先名']) ?>" <?= $取引先名 === $sp['発注先名'] ? 'selected' : '' ?>><?= h($sp['発注先コード']) ?>：<?= h($sp['発注先名']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-item"><label>備考</label><input name="備考" value="<?= h($item['備考']) ?>"></div>
  </div>
  <div class="form-actions">
    <button class="btn btn-primary" type="submit">変更を保存</button>
    <a class="btn" href="<?= url('items') ?>">戻る</a>
  </div>
</form>

<form method="post" class="form-card" onsubmit="return confirm('この在庫マスタを削除しますか？（論理削除）');">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="delete">
  <button class="btn btn-danger" type="submit">この在庫マスタを削除</button>
</form>

<p class="hint">登録日: <?= h(jdate($item['登録日'])) ?> / 更新日: <?= h(jdate($item['更新日'])) ?></p>
<?php require 'app/layouts/footer.php'; ?>
