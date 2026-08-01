<?php
declare(strict_types=1);
$title = '在庫マスタ';

if (is_post()) {
    csrf_check($_POST['csrf'] ?? null);
    try {
        item_register($_POST, current_担当者());
        flash('info', '在庫マスタに登録しました。');
        redirect(url('items'));
    } catch (BizException $e) {
        flash('error', $e->getMessage());
    }
}

$items = items_list();
$suppliers = supplier_list();

require 'app/layouts/header.php';
?>
<form method="post" class="form-card" onsubmit="return confirm('この内容で在庫マスタに登録しますか？');">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <div class="form-grid">
    <div class="form-item"><label>コード *</label><input name="コード" required></div>
    <div class="form-item"><label>品名 *</label><input name="品名" required></div>
    <div class="form-item"><label>基本数量 *</label><input type="number" name="基本数量" step="any" value="1" required></div>
    <div class="form-item"><label>単位 *</label><input name="単位" required></div>
    <div class="form-item"><label>単価 *</label><input type="number" name="単価" step="any" value="0" required></div>
    <div class="form-item"><label>残数量</label><input type="number" name="残数量" step="any" value="0"></div>
    <div class="form-item"><label>安全在庫数</label><input type="number" name="安全在庫数" step="any" value="0"></div>
    <div class="form-item"><label>最小発注数量</label><input type="number" name="最小発注数量" step="any" value="0"></div>
    <div class="form-item"><label>適正在庫数</label><input type="number" name="適正在庫数" step="any" value="0"></div>
    <div class="form-item"><label>標準納入日数</label><input type="number" name="標準納入日数"></div>
    <div class="form-item"><label>棚番</label><input name="棚番"></div>
    <div class="form-item"><label>取引先</label>
      <select name="取引先">
        <option value="">（未設定）</option>
        <?php foreach ($suppliers as $sp): ?>
          <option value="<?= h($sp['発注先名']) ?>"><?= h($sp['発注先コード']) ?>：<?= h($sp['発注先名']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-item"><label>備考</label><input name="備考"></div>
  </div>
  <div class="form-actions"><button class="btn btn-primary" type="submit">登録</button></div>
</form>

<h2 style="font-size:16px;margin:8px 0;">登録済みの品目（<?= count($items) ?>件）</h2>
<div class="table-scroll">
<table class="grid">
  <tr>
    <th class="col-code">コード</th><th class="col-name">品名</th><th>基本数量</th><th>単位</th><th class="num">単価</th>
    <th class="num">残数量</th><th class="num">安全在庫数</th><th class="num">最小発注数量</th><th class="num">適正在庫数</th><th>棚番</th><th></th>
  </tr>
  <?php if (!$items): ?>
    <tr><td colspan="11" class="hint">在庫マスタが空です。上のフォームから登録して下さい。</td></tr>
  <?php endif; ?>
  <?php foreach ($items as $it): ?>
  <tr>
    <td class="col-code"><?= h($it['コード']) ?></td>
    <td class="col-name"><?= h($it['品名']) ?></td>
    <td class="num"><?= fmt_num($it['基本数量']) ?></td>
    <td><?= h($it['単位']) ?></td>
    <td class="num"><?= fmt_num($it['単価'], 2) ?></td>
    <td class="num"><?= fmt_num($it['残数量']) ?></td>
    <td class="num"><?= fmt_num($it['安全在庫数']) ?></td>
    <td class="num"><?= fmt_num($it['最小発注数量']) ?></td>
    <td class="num"><?= fmt_num($it['適正在庫数']) ?></td>
    <td><?= h($it['棚番']) ?></td>
    <td><a class="btn btn-sm" href="<?= url('item_edit', ['id' => $it['id']]) ?>">変更/削除</a></td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<?php require 'app/layouts/footer.php'; ?>
