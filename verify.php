<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$token = $_GET['token'] ?? '';
$status = 'invalid';
$message = '';

if ($token !== '') {
    $stmt = db()->prepare(
        'SELECT id, nama, email_verified_at
         FROM users
         WHERE verification_token = ? LIMIT 1'
    );
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        if (!empty($user['email_verified_at'])) {
            $status = 'already';
            $message = 'Email Anda sudah terverifikasi sebelumnya.';
        } else {
            $upd = db()->prepare(
                'UPDATE users
                 SET email_verified_at = NOW(), verification_token = NULL
                 WHERE id = ?'
            );
            $upd->execute([$user['id']]);
            $status = 'success';
            $message = 'Email berhasil diverifikasi. Selamat datang, ' . $user['nama'] . '!';
        }
    } else {
        $message = 'Token verifikasi tidak valid atau sudah kedaluwarsa.';
    }
} else {
    $message = 'Token verifikasi tidak ditemukan.';
}

$pageTitle = 'Verifikasi Email';
require __DIR__ . '/includes/header.php';
?>
<section class="auth-wrap">
  <div class="auth-card" style="text-align:center;">
    <?php if ($status === 'success'): ?>
      <h1 style="color:var(--ok);">✓ Email Terverifikasi</h1>
    <?php elseif ($status === 'already'): ?>
      <h1 style="color:var(--acc);">Email Sudah Terverifikasi</h1>
    <?php else: ?>
      <h1 style="color:var(--red);">Verifikasi Gagal</h1>
    <?php endif; ?>

    <p class="lead"><?= e($message) ?></p>

    <a href="<?= e(url('login.php')) ?>" class="btn btn-primary">Masuk ke Akun</a>
    <a href="<?= e(url('index.php')) ?>" class="btn btn-ghost">Kembali ke Beranda</a>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
