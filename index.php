<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$p = (string)($_GET['p'] ?? 'dashboard');
if ($p === '') { $p = 'dashboard'; }

// ログインページ以外は担当者チェック
if ($p !== 'login' && $p !== 'logout') {
    require_担当者();
}

$pages = [
    'login'       => 'app/pages/login.php',
    'logout'      => 'app/pages/logout.php',
    'dashboard'   => 'app/pages/dashboard.php',
    'items'       => 'app/pages/items.php',
    'item_edit'   => 'app/pages/item_edit.php',
    'draft'       => 'app/pages/draft.php',
    'orders'      => 'app/pages/orders.php',
    'order_detail' => 'app/pages/order_detail.php',
    'order_split' => 'app/pages/order_split.php',
    'receive'     => 'app/pages/receive.php',
    'inbound'     => 'app/pages/inbound.php',
    'outbound'    => 'app/pages/outbound.php',
    'outbound_edit' => 'app/pages/outbound_edit.php',
    'suppliers'   => 'app/pages/suppliers.php',
    'supplier_edit' => 'app/pages/supplier_edit.php',
    'customers'   => 'app/pages/customers.php',
    'customer_edit' => 'app/pages/customer_edit.php',
    'monthly'     => 'app/pages/monthly.php',
    'logs'        => 'app/pages/logs.php',
];

$page_file = $pages[$p] ?? null;
if ($page_file === null || !is_file($page_file)) {
    http_response_code(404);
    $title = 'ページが見つかりません';
    require 'app/layouts/header.php';
    echo '<p>指定されたページは存在しません。</p>';
    echo '<p><a class="btn" href="' . url('dashboard') . '">ダッシュボードへ</a></p>';
    require 'app/layouts/footer.php';
    exit;
}

require $page_file;
