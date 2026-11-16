<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$user = current_user();
if ($user) {
    redirect(home_for($user['role']));
}

$form = [
    'nama'             => '',
    'email'            => '',
    'whatsapp'         => '',
    'password'         => '',
    'password_confirm' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // --- Ambil input ---
    $form['nama']     = post_string('nama');
    $form['email']    = strtolower(post_string('email'));
    $form['whatsapp'] = post_string('whatsapp');
    $password         = post_string('password');
    $confirm          = post_string('password_confirm');

    // --- Validasi nama ---
    if (mb_strlen($form['nama']) < 2 || mb_strlen($form['nama']) > 100) {
        $errors['nama'] = 'Nama wajib diisi 2–100 karakter.';
    }

    // --- Validasi email ---
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Masukkan email yang benar';
    }

    // --- Validasi WhatsApp (opsional, tapi kalau diisi harus valid) ---
    $phone = '';
    if ($form['whatsapp'] !== '') {
        $phone = normalize_phone($form['whatsapp']);
        if ($phone === '') {
            $errors['whatsapp'] = 'Masukkan nomer yang benar';
        }
    }

    // --- Validasi password ---
    if (strlen($password) < 6) {
        $errors['password'] = 'Kata sandi minimal 6 karakter.';
    }
    if ($password !== $confirm) {
        $errors['password_confirm'] = 'Konfirmasi kata sandi tidak sama.';
    }

    // --- Cek email duplikat ---
    if (!$errors) {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$form['email']]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Email sudah terdaftar.';
        }
    }

    // --- Simpan user ---
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

    <form method="post" class="form" novalidate id="register-form">
      <?= csrf_field() ?>

      <div class="field full">
        <label for="nama">Nama lengkap</label>
        <input type="text" id="nama" name="nama"
               value="<?= e($form['nama']) ?>" maxlength="100" required>
        <span class="error" id="err-nama"><?= e($errors['nama'] ?? '') ?></span>
      </div>

      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email"
               value="<?= e($form['email']) ?>" autocomplete="email" required>
        <span class="error" id="err-email"><?= e($errors['email'] ?? '') ?></span>
      </div>

      <div class="field">
        <label for="whatsapp">WhatsApp (opsional)</label>
        <input type="tel" id="whatsapp" name="whatsapp"
               value="<?= e($form['whatsapp']) ?>"
               placeholder="08xxxxxxxxxx" inputmode="numeric">
        <span class="error" id="err-whatsapp"><?= e($errors['whatsapp'] ?? '') ?></span>
      </div>

      <div class="field">
        <label for="password">Kata sandi</label>
        <div class="pw-wrap">
          <input type="password" id="password" name="password"
                 minlength="6" autocomplete="new-password" required>
          <button type="button" class="pw-toggle"
                  data-target="password" aria-label="Tampilkan sandi">👁</button>
        </div>
        <span class="error" id="err-password"><?= e($errors['password'] ?? '') ?></span>
      </div>

      <div class="field">
        <label for="password_confirm">Ulangi kata sandi</label>
        <div class="pw-wrap">
          <input type="password" id="password_confirm" name="password_confirm"
                 minlength="6" autocomplete="new-password" required>
          <button type="button" class="pw-toggle"
                  data-target="password_confirm" aria-label="Tampilkan sandi">👁</button>
        </div>
        <span class="error" id="err-password_confirm"><?= e($errors['password_confirm'] ?? '') ?></span>
      </div>

      <div class="field full">
        <button type="submit" class="btn btn-primary btn-block">Daftar sebagai pembeli</button>
      </div>
    </form>

    <p class="muted center">Sudah punya akun?
      <a href="<?= e(url('login.php')) ?>">Masuk</a>.
    </p>
  </div>
</section>

<script>
(function () {
  'use strict';

  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  var PHONE_RE = /^(\+?62|0)8[1-9][0-9]{6,11}$/;

  // --- Toggle password visibility ---
  document.querySelectorAll('.pw-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.dataset.target);
      if (!input) return;
      var showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      btn.textContent = showing ? '👁' : '🙈';
      btn.setAttribute('aria-label', showing ? 'Tampilkan sandi' : 'Sembunyikan sandi');
      input.focus();
    });
  });

  function setErr(id, msg) {
    var el = document.getElementById('err-' + id);
    if (el) el.textContent = msg || '';
  }

  // --- Validasi email saat blur ---
  var email = document.getElementById('email');
  email.addEventListener('blur', function () {
    var v = email.value.trim();
    if (!v) { setErr('email', ''); return; }
    setErr('email', EMAIL_RE.test(v) ? '' : 'Masukkan email yang benar');
  });

  // --- Validasi WhatsApp saat blur ---
  var phone = document.getElementById('whatsapp');
  phone.addEventListener('blur', function () {
    var v = phone.value.trim();
    if (!v) { setErr('whatsapp', ''); return; }
    var d = v.replace(/[\s\-().]/g, '');
    setErr('whatsapp', PHONE_RE.test(d) ? '' : 'Masukkan nomer yang benar');
  });

  // --- Validasi password saat blur ---
  var pass = document.getElementById('password');
  var pass2 = document.getElementById('password_confirm');

  pass.addEventListener('blur', function () {
    setErr('password', pass.value.length < 6 ? 'Kata sandi minimal 6 karakter.' : '');
    if (pass2.value) {
      setErr('password_confirm', pass.value === pass2.value ? '' : 'Konfirmasi kata sandi tidak sama.');
    }
  });

  pass2.addEventListener('blur', function () {
    if (!pass2.value) { setErr('password_confirm', ''); return; }
    setErr('password_confirm', pass.value === pass2.value ? '' : 'Konfirmasi kata sandi tidak sama.');
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>