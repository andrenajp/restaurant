<?php
/**
 * Router PHP pour le serveur de développement intégré
 * Usage : php -S localhost:8000 router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// ── Routes API ────────────────────────────────────────────────────────────
if (str_starts_with($uri, '/api')) {
    require __DIR__ . '/api/index.php';
    return true;
}

// ── Assets statiques depuis public/ ──────────────────────────────────────
$public_file = __DIR__ . '/public' . $uri;
if ($uri !== '/' && file_exists($public_file) && !is_dir($public_file)) {
    $ext = strtolower(pathinfo($public_file, PATHINFO_EXTENSION));
    $mime = match($ext) {
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'png'  => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'html' => 'text/html; charset=UTF-8',
        default => 'application/octet-stream',
    };
    header('Content-Type: ' . $mime);
    readfile($public_file);
    return true;
}

// ── 404 pour assets manquants (évite le fallback HTML pour les images/fonts) ─
if (str_starts_with($uri, '/assets/') || str_ends_with($uri, '.png') || str_ends_with($uri, '.jpg') || str_ends_with($uri, '.ico')) {
    http_response_code(404);
    return true;
}

// ── Pages HTML ────────────────────────────────────────────────────────────
header('Content-Type: text/html; charset=UTF-8');

if ($uri === '/track' || str_starts_with($uri, '/track?') || str_starts_with($uri, '/track/')) {
    readfile(__DIR__ . '/public/track.html');
    return true;
}

if ($uri === '/account' || str_starts_with($uri, '/account?')) {
    $f = __DIR__ . '/public/account.html';
    if (file_exists($f)) { readfile($f); return true; }
}

if ($uri === '/admin' || str_starts_with($uri, '/admin?')) {
    $f = __DIR__ . '/public/admin.html';
    if (file_exists($f)) { readfile($f); return true; }
}

// Fallback SPA → index.html
readfile(__DIR__ . '/public/index.html');
return true;
