<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$user = current_user();

if ($user) {
    redirect(home_for($user['role']));
}

redirect(url('login.php'));
