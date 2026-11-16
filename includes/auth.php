<?php
declare(strict_types=1);

function current_user(): ?array
{
    static $user = false;

    if ($user !== false) {
        return $user;
    }

    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id <= 0) {
        $user = null;
        return null;
    }

    $stmt = db()->prepare(
        'SELECT u.*, s.nama_usaha, s.desa, s.profil, s.whatsapp AS seller_whatsapp
         FROM users u
         LEFT JOIN sellers s ON s.id = u.seller_id
         WHERE u.id = ?
         LIMIT 1'
    );
    $stmt->execute([$id]);
    $found = $stmt->fetch();

    if (!$found || !(int) $found['is_active']) {
        unset($_SESSION['user_id']);
        $user = null;
        return null;
    }

    $user = $found;

    return $user;
}

function require_login(): array
{
    $user = current_user();

    if (!$user) {
        flash('Silakan masuk terlebih dahulu.', 'error');
        redirect(url('login.php'));
    }

    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_login();

    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Akses ditolak. Peran akun Anda tidak diizinkan membuka halaman ini.');
    }

    return $user;
}

function home_for(string $role): string
{
    return match ($role) {
        'admin' => url('admin/users.php'),
        'seller' => url('seller/products.php'),
        default => url('buyer/catalog.php'),
    };
}

function role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Admin',
        'seller' => 'Seller',
        default => 'Pembeli',
    };
}
