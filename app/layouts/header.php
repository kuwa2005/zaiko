<?php
declare(strict_types=1);
$title = $title ?? APP_NAME;
$user  = current_担当者();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> | <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="site-header">
  <div class="wrap">
    <div class="brand"><a href="<?= url('dashboard') ?>"><?= h(APP_NAME) ?>(DEMO)</a></div>
    <nav class="site-nav">
      <div class="nav-row">
        <a href="<?= url('dashboard') ?>">ダッシュボード</a>
        <a href="<?= url('draft') ?>">発注依頼</a>
        <a href="<?= url('orders') ?>">発注状況</a>
        <a href="<?= url('receive') ?>">受付</a>
        <a href="<?= url('inbound') ?>">入庫</a>
        <a href="<?= url('outbound') ?>">出庫</a>
        <a href="<?= url('monthly') ?>">月次集計</a>
        <a href="<?= url('logs') ?>">操作ログ</a>
      </div>
      <div class="nav-row">
        <span class="nav-label">マスタメンテ</span>
        <a href="<?= url('items') ?>">在庫マスタ</a>
        <a href="<?= url('suppliers') ?>">発注先</a>
        <a href="<?= url('customers') ?>">出庫先</a>
      </div>
    </nav>
    <div class="user-info">
      担当者: <strong><?= h($user) ?></strong>
      <a class="btn btn-sm" href="<?= url('logout') ?>">ログアウト</a>
    </div>
  </div>
</header>
<main class="wrap">
<?php
$flashes = flash_take();
foreach ($flashes as $f):
    $cls = ($f['type'] ?? 'info') === 'error' ? 'alert-error' : 'alert-info';
?>
  <div class="alert <?= $cls ?>"><?= h($f['message']) ?></div>
<?php endforeach; ?>
<h1 class="page-title"><?= h($title) ?></h1>
