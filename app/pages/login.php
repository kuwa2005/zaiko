<?php
declare(strict_types=1);
$title = '担当者選択';

if (is_post()) {
    csrf_check($_POST['csrf'] ?? null);
    $name = trim((string)($_POST['担当者'] ?? ''));
    $found = false;
    foreach (担当者一覧() as $t) {
        if ($t['名前'] === $name) { $found = true; break; }
    }
    if ($name === '' || !$found) {
        flash('error', '担当者を選択して下さい。');
    } else {
        set_担当者($name);
        redirect(url('dashboard'));
    }
}

require 'app/layouts/header.php';
?>
<p>利用する担当者を選択して下さい。</p>
<form method="post" class="form-card" style="max-width:360px;">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <div class="form-item">
    <label>担当者</label>
    <select name="担当者" required>
      <option value="">選択してください</option>
      <?php foreach (担当者一覧() as $t): ?>
        <option value="<?= h($t['名前']) ?>"><?= h($t['名前']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-actions">
    <button class="btn btn-primary" type="submit">ログイン</button>
  </div>
</form>
<?php require 'app/layouts/footer.php'; ?>
