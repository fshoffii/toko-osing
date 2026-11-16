<?php
declare(strict_types=1);

$__user = current_user();
$__flash = take_flash();
$__pageTitle = $__pageTitle ?? 'Toko Osing';
$__current = basename($_SERVER['SCRIPT_NAME'] ?? '');
$__nav = [];

if ($__user) {
    $__nav = match ($__user['role']) {
        'admin' => [
            ['label' => 'Kelola Pengguna', 'href' => url('admin/users.php'), 'file' => 'users.php'],
        ],
        'seller' => [
            ['label' => 'Produk & Harga', 'href' => url('seller/products.php'), 'file' => 'products.php'],
            ['label' => 'Kategori', 'href' => url('seller/categories.php'), 'file' => 'categories.php'],
        ],
        default => [
            ['label' => 'Katalog', 'href' => url('buyer/catalog.php'), 'file' => 'catalog.php'],
        ],
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($__pageTitle) ?> — Toko Osing</title>
<link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>">
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="<?= e($__user ? home_for($__user['role']) : url('login.php')) ?>">Toko Osing</a>

    <?php if ($__nav): ?>
      <nav class="mainnav" aria-label="Navigasi utama">
        <?php foreach ($__nav as $item): ?>
          <a href="<?= e($item['href']) ?>"<?= $__current === $item['file'] ? ' aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>

    <?php if ($__user): ?>
      <div class="account">
        <span class="role role-<?= e($__user['role']) ?>"><?= e(role_label($__user['role'])) ?></span>
        <span class="account-name"><?= e($__user['nama']) ?></span>
        <form method="post" action="<?= e(url('logout.php')) ?>">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-ghost btn-sm">Keluar</button>
        </form>
      </div>
    <?php else: ?>
      <div class="account">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('login.php')) ?>">Masuk</a>
        <a class="btn btn-sm" href="<?= e(url('register.php')) ?>">Daftar Pembeli</a>
      </div>
    <?php endif; ?>
  </div>
</header>

<main class="container">
  <?php if ($__flash): ?>
    <div class="flash flash-<?= e($__flash['type']) ?>" role="status"><?= e($__flash['message']) ?></div>
  <?php endif; ?>
