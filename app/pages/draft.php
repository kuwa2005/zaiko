<?php
declare(strict_types=1);
$title = '発注依頼';

if (is_post()) {
    csrf_check($_POST['csrf'] ?? null);
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'add') {
            draft_add($_POST, current_担当者());
            flash('info', '発注依頼の下書きを追加しました。');
        } elseif ($action === 'delete') {
            draft_delete(array_keys($_POST['del'] ?? []), current_担当者());
            flash('info', '選択した下書きを削除しました。');
        } elseif ($action === 'commit') {
            $n = drafts_commit(current_担当者());
            flash('info', "発注依頼を確定しました。（{$n}件）");
        }
        redirect(url('draft'));
    } catch (BizException $e) {
        flash('error', $e->getMessage());
    }
}

$items = items_list();
$suppliers = supplier_list();
$drafts = drafts_list();

require 'app/layouts/header.php';
?>
<p class="hint">発注依頼を入力し、下の「発注依頼を確定」で発注データへ登録します。</p>

<form method="post" class="form-card">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="add">
  <div class="form-grid">
    <div class="form-item">
      <label>コード *</label>
      <select name="コード" id="draft_code" required>
        <option value="">選択してください</option>
        <?php foreach ($items as $it): ?>
          <option value="<?= h($it['コード']) ?>"
                  data-name="<?= h($it['品名']) ?>"
                  data-min="<?= h(fmt_num($it['最小発注数量'])) ?>"
                  data-target="<?= h(fmt_num($it['適正在庫数'])) ?>"
                  data-stock="<?= h(fmt_num($it['残数量'])) ?>"
                  data-unit="<?= h($it['単位']) ?>"
                  data-supplier="<?= h($it['取引先']) ?>"
                  <?= ($_GET['コード'] ?? '') === $it['コード'] ? 'selected' : '' ?>>
            <?= h($it['コード']) ?>（<?= h($it['品名']) ?>）
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-item"><label>品名</label><input id="draft_name" readonly></div>
    <div class="form-item"><label>数量 *</label><input type="number" id="draft_qty" name="数量" step="any" required></div>
    <div class="form-item"><label>納期 *</label><input type="date" name="納期" value="<?= h(today()) ?>" required></div>
    <div class="form-item"><label>型式</label><input name="型式"></div>
    <div class="form-item"><label>発注先</label>
      <select name="発注先" id="draft_supplier">
        <option value="">（マスタの取引先）</option>
        <?php foreach ($suppliers as $sp): ?>
          <option value="<?= h($sp['発注先名']) ?>"><?= h($sp['発注先コード']) ?>：<?= h($sp['発注先名']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-item"><label>備考</label><input name="備考"></div>
  </div>
  <div id="draft_help" class="draft-help" hidden>
    <table>
      <tr><th>現在庫</th><td id="help_stock"></td></tr>
      <tr><th>最小発注数量</th><td id="help_min"></td></tr>
      <tr><th>適正在庫数</th><td id="help_target"></td></tr>
      <tr><th>推奨発注数量</th><td id="help_rec"></td></tr>
    </table>
    <button type="button" id="btn_apply_rec" class="btn btn-sm" style="margin-top:8px;">推奨数量を数量にセット</button>
  </div>
  <div class="form-actions"><button class="btn btn-primary" type="submit">下書きに追加</button></div>
</form>

<?php if ($drafts): ?>
<form method="post" class="form-card">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="delete">
  <h2 style="font-size:16px;margin-bottom:8px;">発注依頼の下書き</h2>
  <div class="table-scroll">
  <table class="grid">
    <tr>
      <th>選択</th><th class="col-code">コード</th><th class="col-name">品名</th><th class="num">数量</th><th>納期</th><th>型式</th><th>発注先</th><th>依頼者</th><th>依頼日</th>
    </tr>
    <?php foreach ($drafts as $d): ?>
    <tr>
      <td><input type="checkbox" name="del[<?= (int)$d['id'] ?>]"></td>
      <td class="col-code"><?= h($d['コード']) ?></td>
      <td class="col-name"><?= h($d['品名']) ?></td>
      <td class="num"><?= fmt_num($d['数量']) ?></td>
      <td><?= h(jdate($d['納期'])) ?></td>
      <td><?= h($d['型式']) ?></td>
      <td><?= h($d['発注先']) ?></td>
      <td><?= h($d['依頼者']) ?></td>
      <td><?= h(jdate($d['依頼日'])) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <div class="form-actions">
    <button class="btn btn-danger" type="submit" onclick="return confirm('選択した下書きを削除しますか？');">選択行を削除</button>
  </div>
</form>

<form method="post" class="form-card" onsubmit="return confirm('下書きを発注データへ確定しますか？（確定後は編集できません）');">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="commit">
  <button class="btn btn-primary" type="submit">発注依頼を確定（<?= count($drafts) ?>件）</button>
</form>
<?php else: ?>
<p class="hint">発注依頼の下書きはありません。</p>
<?php endif; ?>

<script>
const sel = document.getElementById('draft_code');
const name = document.getElementById('draft_name');
const qty = document.getElementById('draft_qty');
const sup = document.getElementById('draft_supplier');
const help = document.getElementById('draft_help');

function num(v) {
  const n = parseFloat(String(v).replace(/,/g, ''));
  return isNaN(n) ? 0 : n;
}

function fmt(v) {
  const n = num(v);
  return (n === 0 && String(v).trim() === '') ? '' : n.toLocaleString('ja-JP');
}

function unit(opt) {
  return (opt.dataset.unit || '') ? ' ' + opt.dataset.unit : '';
}

function recommend(opt) {
  const min = num(opt.dataset.min);
  const target = num(opt.dataset.target);
  const stock = num(opt.dataset.stock);
  if (target > 0) {
    return Math.max(target - stock, min > 0 ? min : 1);
  }
  return min > 0 ? min : 0;
}

function sync() {
  const opt = sel.selectedOptions[0];
  const ok = opt && opt.value;
  name.value = (ok && opt.dataset.name) ? opt.dataset.name : '';
  if (ok && opt.dataset.supplier) {
    const target = Array.from(sup.options).find(o => o.value === opt.dataset.supplier);
    if (target) { sup.value = opt.dataset.supplier; }
  }
  if (!ok) {
    help.hidden = true;
    return;
  }
  const min = num(opt.dataset.min);
  if (min > 0) { qty.value = min; }          // コード選択時は最小発注数を自動セット
  const rec = recommend(opt);
  document.getElementById('help_stock').textContent = fmt(opt.dataset.stock) + unit(opt);
  document.getElementById('help_min').textContent = fmt(opt.dataset.min) + unit(opt);
  document.getElementById('help_target').textContent = fmt(opt.dataset.target) + unit(opt);
  document.getElementById('help_rec').textContent = rec > 0 ? fmt(String(rec)) + unit(opt) : '—';
  document.getElementById('btn_apply_rec').disabled = rec <= 0;
  help.hidden = false;
}

sel.addEventListener('change', sync);
document.getElementById('btn_apply_rec').addEventListener('click', () => {
  const opt = sel.selectedOptions[0];
  if (!opt) { return; }
  const rec = recommend(opt);
  if (rec > 0) { qty.value = rec; }
});
sync();
</script>
<?php require 'app/layouts/footer.php'; ?>
