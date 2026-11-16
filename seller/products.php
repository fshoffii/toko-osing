<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$seller = require_role('seller');

if (empty($seller['seller_id'])) {
    http_response_code(403);
    exit('Akun seller ini belum terhubung ke profil usaha. Hubungi admin.');
}

$pdo = db();
$sellerId = (int) $seller['seller_id'];
$errors = [];
$editing = null;
$form = [
    'nama' => '',
    'category_id' => '',
    'harga' => '',
    'stok' => '',
    'deskripsi' => '',
    'unggulan' => 0,
    'oleh_oleh' => 0,
];

$categories = $pdo->query('SELECT id, nama FROM categories ORDER BY nama')->fetchAll();
$categoryIds = array_map(static fn (array $row): int => (int) $row['id'], $categories);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_string('action');

    if ($action === 'delete') {
        $id = (int) post_string('id');
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = ? AND seller_id = ?');
        $stmt->execute([$id, $sellerId]);
        flash($stmt->rowCount() ? 'Produk dihapus.' : 'Produk tidak ditemukan.', $stmt->rowCount() ? 'success' : 'error');
        redirect(url('seller/products.php'));
    }

    $isCreate = $action === 'create';
    $id = (int) post_string('id');

    $form = [
        'nama' => post_string('nama'),
        'category_id' => post_string('category_id'),
        'harga' => post_string('harga'),
        'stok' => post_string('stok'),
        'deskripsi' => post_string('deskripsi'),
        'unggulan' => isset($_POST['unggulan']) ? 1 : 0,
        'oleh_oleh' => isset($_POST['oleh_oleh']) ? 1 : 0,
    ];

    $existing = null;
    if (!$isCreate) {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND seller_id = ? LIMIT 1');
        $stmt->execute([$id, $sellerId]);
        $existing = $stmt->fetch();

        if (!$existing) {
            flash('Produk tidak ditemukan atau bukan milik usaha Anda.', 'error');
            redirect(url('seller/products.php'));
        }
    }

    if (mb_strlen($form['nama']) < 2 || mb_strlen($form['nama']) > 120) {
        $errors['nama'] = 'Nama produk wajib 2–120 karakter.';
    }
    if (!in_array((int) $form['category_id'], $categoryIds, true)) {
        $errors['category_id'] = 'Pilih kategori yang tersedia.';
    }
    if (!preg_match('/^\d+$/', $form['harga']) || (int) $form['harga'] < 0) {
        $errors['harga'] = 'Harga wajib bilangan bulat Rupiah, minimal 0.';
    }
    if (!preg_match('/^\d+$/', $form['stok'])) {
        $errors['stok'] = 'Stok wajib bilangan bulat, minimal 0.';
    }
    if (mb_strlen($form['deskripsi']) > 200) {
        $errors['deskripsi'] = 'Deskripsi maksimal 200 karakter.';
    }

    if (!$errors) {
        try {
            if ($isCreate) {
                $pdo->prepare(
                    'INSERT INTO products (seller_id, category_id, nama, harga, stok, deskripsi, unggulan, oleh_oleh)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $sellerId,
                    (int) $form['category_id'],
                    $form['nama'],
                    (int) $form['harga'],
                    (int) $form['stok'],
                    $form['deskripsi'],
                    $form['unggulan'],
                    $form['oleh_oleh'],
                ]);
                flash('Produk baru ditambahkan.');
            } else {
                $pdo->prepare(
                    'UPDATE products
                     SET category_id = ?, nama = ?, harga = ?, stok = ?, deskripsi = ?, unggulan = ?, oleh_oleh = ?
                     WHERE id = ? AND seller_id = ?'
                )->execute([
                    (int) $form['category_id'],
                    $form['nama'],
                    (int) $form['harga'],
                    (int) $form['stok'],
                    $form['deskripsi'],
                    $form['unggulan'],
                    $form['oleh_oleh'],
                    $id,
                    $sellerId,
                ]);
                flash('Produk dan harga diperbarui.');
            }

            redirect(url('seller/products.php'));
        } catch (PDOException $exception) {
            $errors['general'] = 'Gagal menyimpan produk. Coba lagi.';
        }
    }

    if (!$isCreate) {
        $form['id'] = $id;
        $editing = $form;
    }
} elseif (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND seller_id = ? LIMIT 1');
    $stmt->execute([(int) $_GET['edit'], $sellerId]);
    $editing = $stmt->fetch() ?: null;

    if ($editing) {
        $form = [
            'nama' => (string) $editing['nama'],
            'category_id' => (string) $editing['category_id'],
            'harga' => (string) $editing['harga'],
            'stok' => (string) $editing['stok'],
            'deskripsi' => (string) $editing['deskripsi'],
            'unggulan' => (int) $editing['unggulan'],
            'oleh_oleh' => (int) $editing['oleh_oleh'],
        ];
    }
}

$search = trim((string) ($_GET['q'] ?? ''));

$sql = 'SELECT p.*, c.nama AS kategori
        FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE p.seller_id = ?';
$params = [$sellerId];

if ($search !== '') {
    $sql .= ' AND (p.nama LIKE ? OR c.nama LIKE ? OR p.deskripsi LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$sql .= ' ORDER BY p.unggulan DESC, p.nama';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$summary = $pdo->prepare(
    'SELECT COUNT(*) AS total,
            COALESCE(SUM(stok), 0) AS total_stok,
            COALESCE(SUM(stok = 0), 0) AS habis,
            COALESCE(SUM(stok > 0 AND stok <= 5), 0) AS stok_rendah,
            COALESCE(SUM(unggulan = 1), 0) AS unggulan
     FROM products WHERE seller_id = ?'
);
$summary->execute([$sellerId]);
$stats = $summary->fetch();

$pageTitle = 'Produk & Harga';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div>
    <h1>Produk & Harga</h1>
    <p class="lead"><?= e($seller['nama_usaha']) ?> — Desa <?= e($seller['desa']) ?>. Anda hanya dapat mengelola produk milik usaha ini.</p>
  </div>
</div>

<div class="stats">
  <div class="stat"><strong><?= (int) $stats['total'] ?></strong><span>Total produk</span></div>
  <div class="stat"><strong><?= (int) $stats['total_stok'] ?></strong><span>Total stok</span></div>
  <div class="stat"><strong><?= (int) $stats['stok_rendah'] ?></strong><span>Stok rendah</span></div>
  <div class="stat"><strong><?= (int) $stats['habis'] ?></strong><span>Habis</span></div>
  <div class="stat"><strong><?= (int) $stats['unggulan'] ?></strong><span>Unggulan</span></div>
</div>

<?php if (isset($errors['general'])): ?>
  <div class="flash flash-error"><?= e($errors['general']) ?></div>
<?php endif; ?>

<section class="panel">
  <div class="panel-head">
    <h2><?= $editing ? 'Ubah produk' : 'Tambah produk' ?></h2>
    <?php if ($editing): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('seller/products.php')) ?>">Batal edit</a>
    <?php endif; ?>
  </div>

  <?php if (!$categories): ?>
    <p class="muted">Belum ada kategori. <a href="<?= e(url('seller/categories.php')) ?>">Buat kategori dulu</a>.</p>
  <?php endif; ?>

  <form method="post" class="form" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?>
      <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
    <?php endif; ?>

    <div class="field full">
      <label for="nama">Nama produk</label>
      <input type="text" id="nama" name="nama" value="<?= e($form['nama']) ?>" maxlength="120" required>
      <?php if (isset($errors['nama'])): ?><span class="error"><?= e($errors['nama']) ?></span><?php endif; ?>
    </div>
    <div class="field">
      <label for="category_id">Kategori</label>
      <select id="category_id" name="category_id" required>
        <option value="">Pilih kategori</option>
        <?php foreach ($categories as $category): ?>
          <option value="<?= (int) $category['id'] ?>"<?= (string) $category['id'] === (string) $form['category_id'] ? ' selected' : '' ?>><?= e($category['nama']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['category_id'])): ?><span class="error"><?= e($errors['category_id']) ?></span><?php endif; ?>
    </div>
    <div class="field">
      <label for="harga">Harga (Rp)</label>
      <input type="number" id="harga" name="harga" value="<?= e($form['harga']) ?>" min="0" step="1" required>
      <?php if (isset($errors['harga'])): ?><span class="error"><?= e($errors['harga']) ?></span><?php endif; ?>
    </div>
    <div class="field">
      <label for="stok">Stok</label>
      <input type="number" id="stok" name="stok" value="<?= e($form['stok']) ?>" min="0" step="1" required>
      <?php if (isset($errors['stok'])): ?><span class="error"><?= e($errors['stok']) ?></span><?php endif; ?>
    </div>
    <div class="field full">
      <label for="deskripsi">Deskripsi (maks. 200 karakter)</label>
      <textarea id="deskripsi" name="deskripsi" rows="3" maxlength="200"><?= e($form['deskripsi']) ?></textarea>
      <?php if (isset($errors['deskripsi'])): ?><span class="error"><?= e($errors['deskripsi']) ?></span><?php endif; ?>
    </div>
    <div class="field full checks">
      <label class="check"><input type="checkbox" name="unggulan" value="1"<?= $form['unggulan'] ? ' checked' : '' ?>> Produk unggulan</label>
      <label class="check"><input type="checkbox" name="oleh_oleh" value="1"<?= $form['oleh_oleh'] ? ' checked' : '' ?>> Oleh-oleh khas Banyuwangi</label>
    </div>
    <div class="field full">
      <button type="submit" class="btn"<?= !$categories ? ' disabled' : '' ?>><?= $editing ? 'Simpan perubahan' : 'Tambah produk' ?></button>
    </div>
  </form>
</section>

<section class="panel">
  <div class="panel-head">
    <h2>Daftar produk</h2>
    <form method="get" class="inline-search">
      <label for="q" class="sr-only">Cari produk</label>
      <input type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Cari produk...">
      <button type="submit" class="btn btn-sm">Cari</button>
    </form>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Produk</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Label</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php if (!$products): ?>
          <tr><td colspan="6" class="muted center">Belum ada produk yang cocok.</td></tr>
        <?php endif; ?>
        <?php foreach ($products as $product): ?>
          <tr>
            <td>
              <strong><?= e($product['nama']) ?></strong>
              <?php if ($product['deskripsi']): ?><br><span class="muted"><?= e($product['deskripsi']) ?></span><?php endif; ?>
            </td>
            <td><?= e($product['kategori']) ?></td>
            <td><?= e(rupiah($product['harga'])) ?></td>
            <td>
              <?php if ((int) $product['stok'] === 0): ?>
                <span class="badge badge-off">Habis</span>
              <?php elseif ((int) $product['stok'] <= 5): ?>
                <span class="badge badge-warn">Sisa <?= (int) $product['stok'] ?></span>
              <?php else: ?>
                <?= (int) $product['stok'] ?>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($product['unggulan']): ?><span class="badge badge-accent">Unggulan</span><?php endif; ?>
              <?php if ($product['oleh_oleh']): ?><span class="badge">Oleh-oleh</span><?php endif; ?>
            </td>
            <td class="actions">
              <a class="btn btn-ghost btn-sm" href="<?= e(url('seller/products.php?edit=' . (int) $product['id'])) ?>">Ubah</a>
              <form method="post" onsubmit="return confirm('Hapus produk ini?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
