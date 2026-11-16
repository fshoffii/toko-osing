<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$user = current_user();
if ($user) {
    redirect(home_for($user['role']));
}

$email = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $email = strtolower(post_string('email'));
    $password = post_string('password');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Masukkan alamat email yang valid.';
    }
    if ($password === '') {
        $errors['password'] = 'Kata sandi wajib diisi.';
    }

    if (!$errors) {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $found = $stmt->fetch();

        if (!$found || !password_verify($password, $found['password_hash'])) {
            $errors['general'] = 'Email atau kata sandi salah.';
        } elseif (!(int) $found['is_active']) {
            $errors['general'] = 'Akun ini sedang dinonaktifkan. Hubungi admin.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $found['id'];
            flash('Selamat datang, ' . $found['nama'] . '.');
            redirect(home_for($found['role']));
        }
    }
}

$pageTitle = 'Masuk';
require __DIR__ . '/includes/header.php';
?>

<section class="auth-wrap">
  <div class="auth-card">
    <h1>Masuk ke Toko Osing</h1>
    <p class="lead">Gunakan akun sesuai peran Anda: admin, seller, atau pembeli.</p>

    <?php if (isset($errors['general'])): ?>
      <div class="flash flash-error"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <form method="post" class="form" novalidate>
      <?= csrf_field() ?>
      <div class="field full">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="email" required autofocus>
        <?php if (isset($errors['email'])): ?><span class="error"><?= e($errors['email']) ?></span><?php endif; ?>
      </div>
      <div class="field full">
        <label for="password">Kata sandi</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
        <?php if (isset($errors['password'])): ?><span class="error"><?= e($errors['password']) ?></span><?php endif; ?>
      </div>
      <div class="field full">
        <button type="submit" class="btn btn-block">Masuk</button>
      </div>
    </form>

    <p class="muted center">Belum punya akun pembeli? <a href="<?= e(url('register.php')) ?>">Daftar di sini</a>.</p>

    <div class="demo-box">
      <h2>Akun demo</h2>
      <ul>
        <li><strong>Admin</strong> — admin@tokoosing.test / admin123</li>
        <li><strong>Seller</strong> — batik.kemiren@tokoosing.test / seller123</li>
        <li><strong>Pembeli</strong> — buyer@tokoosing.test / buyer123</li>
      </ul>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
