<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_base(): string
{
    static $base = null;

    if ($base !== null) {
        return $base;
    }

    $docRoot = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $project = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));

    if ($docRoot !== '' && $project !== '' && str_starts_with($project, $docRoot)) {
        $base = rtrim(substr($project, strlen($docRoot)), '/');
    } else {
        $base = '';
    }

    return $base;
}

function url(string $path = ''): string
{
    return app_base() . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $token = $_POST['_csrf'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}

function rupiah(int|float|string $value): string
{
    return 'Rp' . number_format((float) $value, 0, ',', '.');
}

function normalize_phone(?string $value): string
{
    $digits = preg_replace('/[\s\-().+]/', '', (string) $value) ?? '';

    if (!preg_match('/^\d+$/', $digits)) {
        return '';
    }

    if (str_starts_with($digits, '0')) {
        $digits = '62' . substr($digits, 1);
    }

    return strlen($digits) >= 9 && strlen($digits) <= 15 ? $digits : '';
}

function wa_url(string $phone, string $message): string
{
    return 'https://wa.me/' . normalize_phone($phone) . '?text=' . rawurlencode($message);
}

function category_image(string $category): string
{
    $map = [
        'Batik' => 'batik.svg',
        'Kopi' => 'kopi.svg',
        'Gula Aren' => 'gula-aren.svg',
        'Kerajinan' => 'kerajinan.svg',
    ];

    return url('assets/img/' . ($map[$category] ?? 'placeholder.svg'));
}

function product_image(array $product): string
{
    $file = basename((string) ($product['image_url'] ?? ''));

    if ($file !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $file)) {
        $path = __DIR__ . '/../assets/img/' . $file;
        if (is_file($path)) {
            return url('assets/img/' . $file);
        }
    }

    return category_image((string) ($product['kategori'] ?? ''));
}

function slugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

    return trim($slug, '-');
}

function post_string(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}
