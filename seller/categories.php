<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('seller');

$pdo = db();
$errors = [];
$editing = null;
$form = ['nama' => '', 'deskripsi' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_string('action');

    if ($action === 'delete') {
        $id = (int) post_string('id');
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $stmt->execute([$id]);
        $used = (int) $stmt->fetchColumn();

        if ($used > 0) {
            flash('Kategori tidak dapat dihapus karena masih dipakai ' . $used . ' produk.', 'error');
        } else {
            $delete = $pdo->prepare('DELETE FROM categories WHERE id = ?');
            $delete->execute([$id]);
            flash($delete->rowCount() ? 'Kategori dihapus.' : 'Kategori tidak ditemukan.', $delete->rowCount() ? 'success' : 'error');
        }

        redirect(url('seller/categories.php'));
    }

    $isCreate = $action === 'create';
    $id = (int) post_string('id');
    $form = [
        'nama' => post_string('nama'),
        'deskripsi' => post_string('deskripsi'),
    ];

    if (!$isCreate) {
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            flash('Kategori tidak ditemukan.', 'error');
            redirect(url('seller/categories.php'));
        }
    }

    if (mb_strlen($form['nama']) < 2 || mb_strlen($form['nama']) > 60) {
        $errors['nama'] = 'Nama kategori wajib 2–60 karakter.';
    }
    if (mb_strlen($form['deskripsi']) > 200) {
        $errors['deskripsi'] = 'Deskripsi maksimal 200 karakter.';
    }

    $slug = slugify($form['nama']);
    if (!$errors && $slug === '') {
        $errors['nama'] = 'Nama kategori harus memuat huruf atau angka.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE (nama = ? OR slug = ?) AND id <> ? LIMIT 1');
        $stmt->execute([$form['nama'], $slug, $isCreate ? 0 : $id]);
        if ($stmt->fetch()) {
            $errors['nama'] = 'Kategori dengan nama serupa sudah ada.';
        }
    }

    if (!$errors) {
        try {
            if ($isCreate) {
                $pdo->prepare('INSERT INTO categories (nama, slug, deskripsi) VALUES (?, ?, ?)')
                    ->execute([$form['nama'], $slug, $form['deskripsi']]);
                flash('Kategori baru ditambahkan.');
            } else {
                $pdo->prepare('UPDATE categories SET nama = ?, slug = ?, deskripsi = ? WHERE id = ?')
                    ->execute([$form['nama'], $slug, $form['deskripsi'], $id]);
                flash('Kategori diperbarui.');
            }
            redirect(url('seller/categories.php'));
        } catch (PDOException $exception) {
            $errors['general'] = 'Gagal menyimpan kategori. Coba nama lain.';
        }
    }

    if (!$isCreate) {
        $form['id'] = $id;
        $editing = $form;
    }
} elseif (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_GET['edit']]);
    $editing = $stmt->fetch() ?: null;

    if ($editing) {
        $form = ['nama' => (string) $editing['nama'], 'deskripsi' => (string) $editing['deskripsi']];
    }
}

$categories = $pdo->query(
    'SELECT c.*, COUNT(p.id) AS jumlah_produk, COALESCE(SUM(p.stok), 0) AS total_stok
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id
     ORDER BY c.nama'
)->fetchAll();

$pageTitle = 'Kelola Kategori';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div>
    <h1>Kelola Kategori</h1>
    <p class="lead">Seller dapat menambah, mengubah, dan menghapus kategori yang dipakai produk.</p>
  </div>
</div>

<?php if (isset($errors['general'])): ?>
  <div class="flash flash-error"><?= e($errors['general']) ?></div>
<?php endif; ?>

<section class="panel">
  <div class="panel-head">
    <h2><?= $editing ? 'Ubah kategori' : 'Tambah kategori' ?></h2>
    <?php if ($editing): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('seller/categories.php')) ?>">Batal edit</a>
    <?php endif; ?>
  </div>

  <form method="post" class="form" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?>
      <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
    <?php endif; ?>

    <div class="field">
      <label for="nama">Nama kategori</label>
      <input type="text" id="nama" name="nama" value="<?= e($form['nama']) ?>" maxlength="60" required>
      <?php if (isset($errors['nama'])): ?><span class="error"><?= e($errors['nama']) ?></span><?php endif; ?>
    </div>
    <div class="field">
      <label for="deskripsi">Deskripsi (opsional, maks. 200)</label>
      <input type="text" id="deskripsi" name="deskripsi" value="<?= e($form['deskripsi']) ?>" maxlength="200">
      <?php if (isset($errors['deskripsi'])): ?><span class="error"><?= e($errors['deskripsi']) ?></span><?php endif; ?>
    </div>
    <div class="field full">
      <button type="submit" class="btn"><?= $editing ? 'Simpan perubahan' : 'Tambah kategori' ?></button>
    </div>
  </form>
</section>

<section class="panel">
  <div class="panel-head">
    <h2>Daftar kategori</h2>
    <span class="muted"><?= count($categories) ?> kategori</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Kategori</th><th>Deskripsi</th><th>Produk</th><th>Total stok</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php foreach ($categories as $category): ?>
          <tr>
            <td><strong><?= e($category['nama']) ?></strong><br><span class="muted"><?= e($category['slug']) ?></span></td>
            <td><?= e($category['deskripsi']) ?: '<span class="muted">—</span>' ?></td>
            <td><?= (int) $category['jumlah_produk'] ?></td>
            <td><?= (int) $category['total_stok'] ?></td>
            <td class="actions">
              <a class="btn btn-ghost btn-sm" href="<?= e(url('seller/categories.php?edit=' . (int) $category['id'])) ?>">Ubah</a>
              <form method="post" onsubmit="return confirm('Hapus kategori ini?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
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
