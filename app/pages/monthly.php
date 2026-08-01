<?php
declare(strict_types=1);
$title = '月次集計・締め';

$月 = (string)($_GET['月'] ?? current_month());

if (is_post()) {
    csrf_check($_POST['csrf'] ?? null);
    $target = (string)($_POST['対象月'] ?? $月);
    try {
        $n = close_month(current_担当者(), $target);
        flash('info', "{$target} の月次締めを実行しました。（{$n}品目）");
        redirect(url('monthly', ['月' => $target]));
    } catch (BizException $e) {
        flash('error', $e->getMessage());
    }
}

$data = monthly_rows($月);
$history = close_history($月);

require 'app/layouts/header.php';
?>
<form method="get" class="form-card">
  <input type="hidden" name="p" value="monthly">
  <div class="form-grid">
    <div class="form-item"><label>対象月</label><input type="month" name="月" value="<?= h($月) ?>"></div>
    <div class="form-item"><label>&nbsp;</label><button class="btn" type="submit">表示</button></div>
  </div>
</form>

<div class="table-scroll">
<table class="grid">
  <tr>
    <th class="col-code">コード</th><th class="col-name">品名</th><th>基本数量</th><th>単位</th>
    <th class="num">前月残</th><th class="num">前月残金額</th>
    <th class="num">当月入庫</th><th class="num">当入金額</th>
    <th class="num">当月出庫</th><th class="num">当出金額</th>
    <th class="num">当月残</th><th class="num">当月残金額</th>
    <th>警告</th>
  </tr>
  <?php if (!$data['rows']): ?>
    <tr><td colspan="13" class="hint">在庫マスタがありません。</td></tr>
  <?php endif; ?>
  <?php foreach ($data['rows'] as $r): ?>
  <tr>
    <td class="col-code"><?= h($r['コード']) ?></td>
    <td class="col-name"><?= h($r['品名']) ?></td>
    <td class="num"><?= fmt_num($r['基本数量']) ?></td>
    <td><?= h($r['単位']) ?></td>
    <td class="num"><?= fmt_num($r['前入']) ?></td>
    <td class="num"><?= fmt_num($r['前入金額'], 2) ?></td>
    <td class="num"><?= fmt_num($r['当入']) ?></td>
    <td class="num"><?= fmt_num($r['当入金額'], 2) ?></td>
    <td class="num"><?= fmt_num($r['当出']) ?></td>
    <td class="num"><?= fmt_num($r['当出金額'], 2) ?></td>
    <td class="num"><?= fmt_num($r['当残']) ?></td>
    <td class="num"><?= fmt_num($r['当残金額'], 2) ?></td>
    <td><?= h($r['警告']) ?></td>
  </tr>
  <?php endforeach; ?>
  <tr style="font-weight:bold; background:#eef2f7;">
    <td colspan="4">合計</td>
    <td class="num"><?= fmt_num($data['totals']['前入']) ?></td>
    <td class="num"><?= fmt_num($data['totals']['前入金額'], 2) ?></td>
    <td class="num"><?= fmt_num($data['totals']['当入']) ?></td>
    <td class="num"><?= fmt_num($data['totals']['当入金額'], 2) ?></td>
    <td class="num"><?= fmt_num($data['totals']['当出']) ?></td>
    <td class="num"><?= fmt_num($data['totals']['当出金額'], 2) ?></td>
    <td class="num"><?= fmt_num($data['totals']['当残']) ?></td>
    <td class="num"><?= fmt_num($data['totals']['当残金額'], 2) ?></td>
    <td></td>
  </tr>
</table>
</div>

<form method="post" class="form-card" onsubmit="return confirm('<?= h($月) ?> の月次締めを実行しますか？（締め履歴に記録されます）');">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="対象月" value="<?= h($月) ?>">
  <button class="btn btn-primary" type="submit"><?= h($月) ?> を締める</button>
</form>

<?php if ($history): ?>
<h2 style="font-size:16px;margin:8px 0;">締め履歴（<?= h($月) ?>）</h2>
<div class="table-scroll">
<table class="grid">
  <tr>
    <th class="col-code">コード</th><th class="col-name">品名</th><th class="num">前月残数量</th><th class="num">当入数量</th>
    <th class="num">当出数量</th><th class="num">当残数量</th><th>締め日</th><th>締め者</th>
  </tr>
  <?php foreach ($history as $hk): ?>
  <tr>
    <td class="col-code"><?= h($hk['コード']) ?></td>
    <td class="col-name"><?= h($hk['品名']) ?></td>
    <td class="num"><?= fmt_num($hk['前月残数量']) ?></td>
    <td class="num"><?= fmt_num($hk['当入数量']) ?></td>
    <td class="num"><?= fmt_num($hk['当出数量']) ?></td>
    <td class="num"><?= fmt_num($hk['当残数量']) ?></td>
    <td><?= h(jdate($hk['締め日'])) ?></td>
    <td><?= h($hk['締め者']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<?php endif; ?>
<?php require 'app/layouts/footer.php'; ?>
