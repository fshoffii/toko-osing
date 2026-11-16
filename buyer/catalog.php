<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$buyer = require_role('buyer');
$pdo = db();

$desaOptions = ['Kemiren', 'Tamansari', 'Osing'];
$categoryRows = $pdo->query('SELECT nama FROM categories ORDER BY nama')->fetchAll();
$categoryOptions = array_map(static fn (array $row): string => (string) $row['nama'], $categoryRows);

$q = trim((string) ($_GET['q'] ?? ''));
$desa = (string) ($_GET['desa'] ?? '');
$kategori = (string) ($_GET['kategori'] ?? '');
$sort = (string) ($_GET['sort'] ?? 'unggulan');

if (!in_array($desa, $desaOptions, true)) {
    $desa = '';
}
if (!in_array($kategori, $categoryOptions, true)) {
    $kategori = '';
}

$sorts = [
    'unggulan' => 'p.unggulan DESC, p.nama ASC',
    'murah' => 'p.harga ASC, p.nama ASC',
    'mahal' => 'p.harga DESC, p.nama ASC',
    'nama' => 'p.nama ASC',
    'stok' => 'p.stok DESC, p.nama ASC',
];
if (!isset($sorts[$sort])) {
    $sort = 'unggulan';
}

$sql = 'SELECT p.*, s.nama_usaha, s.desa, s.whatsapp AS seller_whatsapp, c.nama AS kategori
        FROM products p
        JOIN sellers s ON s.id = p.seller_id
        JOIN categories c ON c.id = p.category_id
        WHERE 1 = 1';
$params = [];

if ($q !== '') {
    $sql .= ' AND (p.nama LIKE ? OR c.nama LIKE ? OR p.deskripsi LIKE ? OR s.nama_usaha LIKE ? OR s.desa LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($desa !== '') {
    $sql .= ' AND s.desa = ?';
    $params[] = $desa;
}
if ($kategori !== '') {
    $sql .= ' AND c.nama = ?';
    $params[] = $kategori;
}

$sql .= ' ORDER BY ' . $sorts[$sort];

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$totalProducts = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$totalSellers = (int) $pdo->query('SELECT COUNT(*) FROM sellers')->fetchColumn();
$activeFilters = $q !== '' || $desa !== '' || $kategori !== '' || $sort !== 'unggulan';

$pageTitle = 'Katalog Produk';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div>
    <h1>Katalog Produk Desa</h1>
    <p class="lead">Halo <?= e($buyer['nama']) ?>. Jelajahi produk perajin, lalu hubungi seller lewat WhatsApp.</p>
  </div>
</div>

<div class="stats">
  <div class="stat"><strong><?= $totalProducts ?></strong><span>Produk tersedia</span></div>
  <div class="stat"><strong><?= $totalSellers ?></strong><span>Usaha perajin</span></div>
  <div class="stat"><strong><?= count($desaOptions) ?></strong><span>Desa wisata</span></div>
  <div class="stat"><strong><?= count($categoryOptions) ?></strong><span>Kategori</span></div>
</div>

<div class="flash flash-info">
  Sebagai pembeli, Anda hanya dapat melihat katalog dan menghubungi seller melalui WhatsApp. Tidak ada transaksi di dalam aplikasi.
</div>

<form method="get" class="filters">
  <div class="field filter-search">
    <label for="q">Cari</label>
    <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Produk, usaha, atau desa">
  </div>
  <div class="field">
    <label for="desa">Desa</label>
    <select id="desa" name="desa">
      <option value="">Semua desa</option>
      <?php foreach ($desaOptions as $option): ?>
        <option value="<?= e($option) ?>"<?= $desa === $option ? ' selected' : '' ?>><?= e($option) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="kategori">Kategori</label>
    <select id="kategori" name="kategori">
      <option value="">Semua kategori</option>
      <?php foreach ($categoryOptions as $option): ?>
        <option value="<?= e($option) ?>"<?= $kategori === $option ? ' selected' : '' ?>><?= e($option) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="sort">Urutkan</label>
    <select id="sort" name="sort">
      <option value="unggulan"<?= $sort === 'unggulan' ? ' selected' : '' ?>>Unggulan dulu</option>
      <option value="murah"<?= $sort === 'murah' ? ' selected' : '' ?>>Harga termurah</option>
      <option value="mahal"<?= $sort === 'mahal' ? ' selected' : '' ?>>Harga termahal</option>
      <option value="nama"<?= $sort === 'nama' ? ' selected' : '' ?>>Nama A–Z</option>
      <option value="stok"<?= $sort === 'stok' ? ' selected' : '' ?>>Stok terbanyak</option>
    </select>
  </div>
  <div class="filter-actions">
    <button type="submit" class="btn">Terapkan</button>
    <?php if ($activeFilters): ?>
      <a class="btn btn-ghost" href="<?= e(url('buyer/catalog.php')) ?>">Reset filter</a>
    <?php endif; ?>
  </div>
</form>

<p class="result-count">Menampilkan <?= count($products) ?> dari <?= $totalProducts ?> produk</p>

<?php if (!$products): ?>
  <div class="empty">
    <h2>Tidak ada produk yang cocok</h2>
    <p>Coba kata kunci lain atau reset filter.</p>
    <a class="btn" href="<?= e(url('buyer/catalog.php')) ?>">Reset filter</a>
  </div>
<?php else: ?>
  <div class="product-grid">
    <?php foreach ($products as $product): ?>
      <?php
      $stock = (int) $product['stok'];
      $message = 'Halo Toko Osing, saya ingin memesan ' . $product['nama']
          . ' dari ' . $product['nama_usaha']
          . ' (Desa ' . $product['desa'] . '), harga ' . rupiah($product['harga'])
          . '. Jumlah: 1. Mohon info ketersediaan dan cara pembayaran.';
      ?>
      <article class="product-card">
        <img src="<?= e(product_image($product)) ?>" alt="<?= e($product['nama']) ?>" loading="lazy">
        <div class="product-body">
          <div class="badges">
            <?php if ($product['unggulan']): ?><span class="badge badge-accent">Unggulan</span><?php endif; ?>
            <?php if ($product['oleh_oleh']): ?><span class="badge">Oleh-oleh khas Banyuwangi</span><?php endif; ?>
          </div>
          <h2><?= e($product['nama']) ?></h2>
          <p class="muted"><?= e($product['kategori']) ?> · <?= e($product['nama_usaha']) ?>, Desa <?= e($product['desa']) ?></p>
          <?php if ($product['deskripsi']): ?>
            <p class="product-desc"><?= e($product['deskripsi']) ?></p>
          <?php endif; ?>
          <div class="product-foot">
            <strong class="price"><?= e(rupiah($product['harga'])) ?></strong>
            <span class="stock<?= $stock > 0 && $stock <= 5 ? ' low' : '' ?>">
              <?= $stock === 0 ? 'Habis' : ($stock <= 5 ? 'Sisa ' . $stock : 'Stok ' . $stock) ?>
            </span>
          </div>
          <?php if ($stock === 0): ?>
            <button type="button" class="btn btn-block" disabled>Habis</button>
          <?php else: ?>
            <a class="btn btn-wa btn-block"
               href="<?= e(wa_url((string) $product['seller_whatsapp'], $message)) ?>"
               target="_blank" rel="noopener noreferrer">Hubungi Seller via WhatsApp</a>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
