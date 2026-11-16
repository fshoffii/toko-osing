<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$user = current_user();
if ($user) {
    redirect(home_for($user['role']));
}

$form = ['nama' => '', 'email' => '', 'whatsapp' => '', 'password' => '', 'password_confirm' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $form['nama'] = post_string('nama');
    $form['email'] = strtolower(post_string('email'));
    $form['whatsapp'] = post_string('whatsapp');
    $password = post_string('password');
    $confirm = post_string('password_confirm');

    if (mb_strlen($form['nama']) < 2 || mb_strlen($form['nama']) > 100) {
        $errors['nama'] = 'Nama wajib diisi 2–100 karakter.';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Masukkan alamat email yang valid.';
    }
    $phone = normalize_phone($form['whatsapp']);
    if ($form['whatsapp'] !== '' && $phone === '') {
        $errors['whatsapp'] = 'Nomor WhatsApp tidak valid (9–15 digit).';
    }
    if (strlen($password) < 6) {
        $errors['password'] = 'Kata sandi minimal 6 karakter.';
    }
    if ($password !== $confirm) {
        $errors['password_confirm'] = 'Konfirmasi kata sandi tidak sama.';
    }

    if (!$errors) {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$form['email']]);

        if ($stmt->fetch()) {
            $errors['email'] = 'Email sudah terdaftar.';
        }
    }

    if (!$errors) {
        $stmt = db()->prepare(
            "INSERT INTO users (nama, email, password_hash, role, whatsapp)
             VALUES (?, ?, ?, 'buyer', ?)"
        );
        $stmt->execute([
            $form['nama'],
            $form['email'],
            password_hash($password, PASSWORD_DEFAULT),
            $phone !== '' ? $phone : null,
        ]);

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) db()->lastInsertId();
        flash('Pendaftaran berhasil. Selamat datang!');
        redirect(url('buyer/catalog.php'));
    }
}

$pageTitle = 'Daftar Pembeli';
require __DIR__ . '/includes/header.php';
?>
<section class="auth-wrap">
  <div class="auth-card">
    <h1>Daftar Akun Pembeli</h1>
    <p class="lead">Pembeli dapat melihat katalog dan menghubungi seller lewat WhatsApp.</p>

    <form method="post" class="form" novalidate>
      <?= csrf_field() ?>
      <div class="field full">
        <label for="nama">Nama lengkap</label>
        <input type="text" id="nama" name="nama" value="<?= e($form['nama']) ?>" maxlength="100" required>
        <?php if (isset($errors['nama'])): ?><span class="error"><?= e($errors['nama']) ?></span><?php endif; ?>
      </div>
      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($form['email']) ?>" autocomplete="email" required>
        <?php if (isset($errors['email'])): ?><span class="error"><?= e($errors['email']) ?></span><?php endif; ?>
      </div>
      <div class="field">
        <label for="whatsapp">WhatsApp (opsional)</label>
        <input type="text" id="whatsapp" name="whatsapp" value="<?= e($form['whatsapp']) ?>" placeholder="08xxxxxxxxxx" inputmode="numeric">
        <?php if (isset($errors['whatsapp'])): ?><span class="error"><?= e($errors['whatsapp']) ?></span><?php endif; ?>
      </div>
      <div class="field">
        <label for="password">Kata sandi</label>
        <input type="password" id="password" name="password" minlength="6" autocomplete="new-password" required>
        <?php if (isset($errors['password'])): ?><span class="error"><?= e($errors['password']) ?></span><?php endif; ?>
      </div>
      <div class="field">
        <label for="password_confirm">Ulangi kata sandi</label>
        <input type="password" id="password_confirm" name="password_confirm" minlength="6" autocomplete="new-password" required>
        <?php if (isset($errors['password_confirm'])): ?><span class="error"><?= e($errors['password_confirm']) ?></span><?php endif; ?>
      </div>
      <div class="field full">
        <button type="submit" class="btn btn-block">Daftar sebagai pembeli</button>
      </div>
    </form>

    <p class="muted center">Sudah punya akun? <a href="<?= e(url('login.php')) ?>">Masuk</a>.</p>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
