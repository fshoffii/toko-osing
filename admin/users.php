<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_role('admin');
$pdo = db();
$desaOptions = ['Kemiren', 'Tamansari', 'Osing'];
$roleOptions = ['buyer' => 'Pembeli', 'seller' => 'Seller', 'admin' => 'Admin'];

$errors = [];
$editing = null;
$form = [
    'nama' => '',
    'email' => '',
    'whatsapp' => '',
    'role' => 'buyer',
    'is_active' => 1,
    'nama_usaha' => '',
    'desa' => 'Kemiren',
    'seller_whatsapp' => '',
    'profil' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_string('action');

    if ($action === 'delete') {
        $id = (int) post_string('id');
        $stmt = $pdo->prepare('SELECT id, seller_id FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $target = $stmt->fetch();

        if (!$target) {
            flash('Pengguna tidak ditemukan.', 'error');
        } elseif ((int) $target['id'] === (int) $admin['id']) {
            flash('Anda tidak dapat menghapus akun sendiri.', 'error');
        } else {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            if ($target['seller_id'] !== null) {
                $pdo->prepare('DELETE FROM sellers WHERE id = ?')->execute([(int) $target['seller_id']]);
            }
            $pdo->commit();
            flash('Pengguna dihapus.' . ($target['seller_id'] !== null ? ' Produk milik seller tersebut juga dihapus.' : ''));
        }

        redirect(url('admin/users.php'));
    }

    $isCreate = $action === 'create';
    $id = (int) post_string('id');

    $form = [
        'nama' => post_string('nama'),
        'email' => strtolower(post_string('email')),
        'whatsapp' => post_string('whatsapp'),
        'role' => post_string('role', 'buyer'),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'nama_usaha' => post_string('nama_usaha'),
        'desa' => post_string('desa', 'Kemiren'),
        'seller_whatsapp' => post_string('seller_whatsapp'),
        'profil' => post_string('profil'),
    ];

    $existing = null;
    if (!$isCreate) {
        $stmt = $pdo->prepare(
            'SELECT u.*, s.nama_usaha, s.desa, s.profil, s.whatsapp AS seller_whatsapp
             FROM users u LEFT JOIN sellers s ON s.id = u.seller_id WHERE u.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            flash('Pengguna tidak ditemukan.', 'error');
            redirect(url('admin/users.php'));
        }
    }

    if (mb_strlen($form['nama']) < 2 || mb_strlen($form['nama']) > 100) {
        $errors['nama'] = 'Nama wajib 2–100 karakter.';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email tidak valid.';
    }
    if (!array_key_exists($form['role'], $roleOptions)) {
        $errors['role'] = 'Pilih peran yang valid.';
    }

    $userPhone = '';
    if ($form['whatsapp'] !== '') {
        $userPhone = normalize_phone($form['whatsapp']);
        if ($userPhone === '') {
            $errors['whatsapp'] = 'Nomor WhatsApp tidak valid.';
        }
    }

    $password = post_string('password');
    if ($isCreate && strlen($password) < 6) {
        $errors['password'] = 'Kata sandi minimal 6 karakter.';
    }
    if (!$isCreate && $password !== '' && strlen($password) < 6) {
        $errors['password'] = 'Kata sandi baru minimal 6 karakter.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $stmt->execute([$form['email'], $isCreate ? 0 : $id]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Email sudah dipakai pengguna lain.';
        }
    }

    $sellerPhone = '';
    if ($form['role'] === 'seller') {
        if (mb_strlen($form['nama_usaha']) < 2 || mb_strlen($form['nama_usaha']) > 120) {
            $errors['nama_usaha'] = 'Nama usaha wajib 2–120 karakter.';
        }
        if (!in_array($form['desa'], $desaOptions, true)) {
            $errors['desa'] = 'Pilih desa yang valid.';
        }
        $sellerPhone = normalize_phone($form['seller_whatsapp']);
        if ($sellerPhone === '') {
            $errors['seller_whatsapp'] = 'Nomor WhatsApp seller wajib valid (9–15 digit).';
        }
        if (mb_strlen($form['profil']) > 255) {
            $errors['profil'] = 'Profil maksimal 255 karakter.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $sellerId = $existing['seller_id'] ?? null;

            if ($form['role'] === 'seller') {
                if ($sellerId !== null) {
                    $pdo->prepare(
                        'UPDATE sellers SET nama_usaha = ?, desa = ?, profil = ?, whatsapp = ? WHERE id = ?'
                    )->execute([
                        $form['nama_usaha'],
                        $form['desa'],
                        $form['profil'],
                        $sellerPhone,
                        (int) $sellerId,
                    ]);
                } else {
                    $pdo->prepare(
                        'INSERT INTO sellers (nama_usaha, desa, profil, whatsapp) VALUES (?, ?, ?, ?)'
                    )->execute([
                        $form['nama_usaha'],
                        $form['desa'],
                        $form['profil'],
                        $sellerPhone,
                    ]);
                    $sellerId = (int) $pdo->lastInsertId();
                }
            }

            if ($isCreate) {
                $pdo->prepare(
                    'INSERT INTO users (nama, email, password_hash, role, whatsapp, seller_id, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $form['nama'],
                    $form['email'],
                    password_hash($password, PASSWORD_DEFAULT),
                    $form['role'],
                    $userPhone !== '' ? $userPhone : null,
                    $sellerId !== null ? (int) $sellerId : null,
                    $form['is_active'],
                ]);
            } else {
                $sql = 'UPDATE users SET nama = ?, email = ?, role = ?, whatsapp = ?, seller_id = ?, is_active = ?';
                $params = [
                    $form['nama'],
                    $form['email'],
                    $form['role'],
                    $userPhone !== '' ? $userPhone : null,
                    $sellerId !== null ? (int) $sellerId : null,
                    $form['is_active'],
                ];
                if ($password !== '') {
                    $sql .= ', password_hash = ?';
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }
                $sql .= ' WHERE id = ?';
                $params[] = $id;
                $pdo->prepare($sql)->execute($params);
            }

            $pdo->commit();
            flash($isCreate ? 'Pengguna baru ditambahkan.' : 'Data pengguna diperbarui.');
            redirect(url('admin/users.php'));
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['general'] = 'Gagal menyimpan pengguna. Periksa kembali data Anda.';
        }
    }

    if (!$isCreate) {
        $form['id'] = $id;
        $editing = $form;
    }
} elseif (isset($_GET['edit'])) {
    $stmt = $pdo->prepare(
        'SELECT u.*, s.nama_usaha, s.desa, s.profil, s.whatsapp AS seller_whatsapp
         FROM users u LEFT JOIN sellers s ON s.id = u.seller_id WHERE u.id = ? LIMIT 1'
    );
    $stmt->execute([(int) $_GET['edit']]);
    $editing = $stmt->fetch() ?: null;

    if ($editing) {
        $form = [
            'nama' => (string) $editing['nama'],
            'email' => (string) $editing['email'],
            'whatsapp' => (string) ($editing['whatsapp'] ?? ''),
            'role' => (string) $editing['role'],
            'is_active' => (int) $editing['is_active'],
            'nama_usaha' => (string) ($editing['nama_usaha'] ?? ''),
            'desa' => (string) ($editing['desa'] ?? 'Kemiren'),
            'seller_whatsapp' => (string) ($editing['seller_whatsapp'] ?? ''),
            'profil' => (string) ($editing['profil'] ?? ''),
        ];
    }
}

$users = $pdo->query(
    'SELECT u.*, s.nama_usaha, s.desa
     FROM users u LEFT JOIN sellers s ON s.id = u.seller_id
     ORDER BY FIELD(u.role, "admin", "seller", "buyer"), u.nama'
)->fetchAll();

$stats = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        SUM(role = 'admin') AS admin_count,
        SUM(role = 'seller') AS seller_count,
        SUM(role = 'buyer') AS buyer_count,
        SUM(is_active = 0) AS inactive_count
     FROM users"
)->fetch();

$pageTitle = 'Kelola Pengguna';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div>
    <h1>Kelola Pengguna</h1>
    <p class="lead">Admin dapat menambah, mengubah peran, menonaktifkan, dan menghapus akun.</p>
  </div>
</div>

<div class="stats">
  <div class="stat"><strong><?= (int) $stats['total'] ?></strong><span>Total pengguna</span></div>
  <div class="stat"><strong><?= (int) $stats['admin_count'] ?></strong><span>Admin</span></div>
  <div class="stat"><strong><?= (int) $stats['seller_count'] ?></strong><span>Seller</span></div>
  <div class="stat"><strong><?= (int) $stats['buyer_count'] ?></strong><span>Pembeli</span></div>
  <div class="stat"><strong><?= (int) $stats['inactive_count'] ?></strong><span>Nonaktif</span></div>
</div>

<?php if (isset($errors['general'])): ?>
  <div class="flash flash-error"><?= e($errors['general']) ?></div>
<?php endif; ?>

<section class="panel">
  <div class="panel-head">
    <h2><?= $editing ? 'Ubah pengguna' : 'Tambah pengguna' ?></h2>
    <?php if ($editing): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/users.php')) ?>">Batal edit</a>
    <?php endif; ?>
  </div>

  <form method="post" class="form" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?>
      <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
    <?php endif; ?>

    <div class="field">
      <label for="nama">Nama</label>
      <input type="text" id="nama" name="nama" value="<?= e($form['nama']) ?>" maxlength="100" required>
      <?php if (isset($errors['nama'])): ?><span class="error"><?= e($errors['nama']) ?></span><?php endif; ?>
    </div>
    <div class="field">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= e($form['email']) ?>" maxlength="150" required>
      <?php if (isset($errors['email'])): ?><span class="error"><?= e($errors['email']) ?></span><?php endif; ?>
    </div>
    <div class="field">
      <label for="whatsapp">WhatsApp pengguna (opsional)</label>
      <input type="text" id="whatsapp" name="whatsapp" value="<?= e($form['whatsapp']) ?>" inputmode="numeric" placeholder="08xxxxxxxxxx">
      <?php if (isset($errors['whatsapp'])): ?><span class="error"><?= e($errors['whatsapp']) ?></span><?php endif; ?>
    </div>
    <div class="field">
      <label for="role">Peran</label>
      <select id="role" name="role">
        <?php foreach ($roleOptions as $value => $label): ?>
          <option value="<?= e($value) ?>"<?= $form['role'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['role'])): ?><span class="error"><?= e($errors['role']) ?></span><?php endif; ?>
    </div>
    <div class="field">
      <label for="password"><?= $editing ? 'Kata sandi baru (opsional)' : 'Kata sandi' ?></label>
      <input type="password" id="password" name="password" autocomplete="new-password" <?= $editing ? '' : 'required' ?>>
      <?php if (isset($errors['password'])): ?><span class="error"><?= e($errors['password']) ?></span><?php endif; ?>
    </div>
    <div class="field">
      <label class="check"><input type="checkbox" name="is_active" value="1"<?= $form['is_active'] ? ' checked' : '' ?>> Akun aktif</label>
    </div>

    <fieldset class="fieldset full">
      <legend>Profil seller <span class="muted">(wajib diisi jika peran Seller)</span></legend>
      <div class="form">
        <div class="field">
          <label for="nama_usaha">Nama usaha</label>
          <input type="text" id="nama_usaha" name="nama_usaha" value="<?= e($form['nama_usaha']) ?>" maxlength="120">
          <?php if (isset($errors['nama_usaha'])): ?><span class="error"><?= e($errors['nama_usaha']) ?></span><?php endif; ?>
        </div>
        <div class="field">
          <label for="desa">Desa</label>
          <select id="desa" name="desa">
            <?php foreach ($desaOptions as $desa): ?>
              <option value="<?= e($desa) ?>"<?= $form['desa'] === $desa ? ' selected' : '' ?>><?= e($desa) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['desa'])): ?><span class="error"><?= e($errors['desa']) ?></span><?php endif; ?>
        </div>
        <div class="field">
          <label for="seller_whatsapp">WhatsApp seller</label>
          <input type="text" id="seller_whatsapp" name="seller_whatsapp" value="<?= e($form['seller_whatsapp']) ?>" inputmode="numeric" placeholder="628xxxxxxxxxx">
          <?php if (isset($errors['seller_whatsapp'])): ?><span class="error"><?= e($errors['seller_whatsapp']) ?></span><?php endif; ?>
        </div>
        <div class="field">
          <label for="profil">Profil singkat</label>
          <input type="text" id="profil" name="profil" value="<?= e($form['profil']) ?>" maxlength="255">
          <?php if (isset($errors['profil'])): ?><span class="error"><?= e($errors['profil']) ?></span><?php endif; ?>
        </div>
      </div>
    </fieldset>

    <div class="field full">
      <button type="submit" class="btn"><?= $editing ? 'Simpan perubahan' : 'Tambah pengguna' ?></button>
    </div>
  </form>
</section>

<section class="panel">
  <div class="panel-head">
    <h2>Daftar pengguna</h2>
    <span class="muted"><?= count($users) ?> akun</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Nama</th><th>Email</th><th>Peran</th><th>Seller</th><th>Status</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $row): ?>
          <tr>
            <td><?= e($row['nama']) ?></td>
            <td><?= e($row['email']) ?></td>
            <td><span class="role role-<?= e($row['role']) ?>"><?= e(role_label($row['role'])) ?></span></td>
            <td>
              <?php if ($row['nama_usaha']): ?>
                <?= e($row['nama_usaha']) ?><br><span class="muted">Desa <?= e($row['desa']) ?></span>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
            <td><?= (int) $row['is_active'] ? '<span class="badge badge-ok">Aktif</span>' : '<span class="badge badge-off">Nonaktif</span>' ?></td>
            <td class="actions">
              <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/users.php?edit=' . (int) $row['id'])) ?>">Ubah</a>
              <?php if ((int) $row['id'] !== (int) $admin['id']): ?>
                <form method="post" onsubmit="return confirm('Hapus pengguna ini? Produk milik seller juga akan dihapus.');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
