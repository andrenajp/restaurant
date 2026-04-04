<?php
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/Response.php';
require_once __DIR__ . '/helpers/Validator.php';

// CORS : wildcard en dev, domaine APP_URL restreint en production
if (env('APP_ENV', 'development') === 'production') {
    $allowed_origin = rtrim(env('APP_URL', ''), '/');
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === $allowed_origin) {
        header('Access-Control-Allow-Origin: ' . $allowed_origin);
        header('Vary: Origin');
    }
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = preg_replace('#^/api#', '', $uri);
$method = $_SERVER['REQUEST_METHOD'];
$raw_input = $GLOBALS['_test_override_input'] ?? file_get_contents('php://input');
$body      = json_decode($raw_input, true) ?? [];

// Routes publiques
if ($uri === '/settings' && $method === 'GET') {
    require __DIR__ . '/routes/settings.php';
}
elseif (preg_match('#^/categories$#', $uri) && $method === 'GET') {
    require __DIR__ . '/routes/menu.php';
}
elseif (preg_match('#^/products$#', $uri) && $method === 'GET') {
    require __DIR__ . '/routes/menu.php';
}
elseif (preg_match('#^/auth/(register|login|forgot|reset)$#', $uri, $m) && $method === 'POST') {
    require __DIR__ . '/routes/auth.php';
}
elseif ($uri === '/auth/profile' && $method === 'PATCH') {
    require __DIR__ . '/routes/auth_profile.php';
}
elseif (str_starts_with($uri, '/auth/addresses')) {
    require __DIR__ . '/routes/auth_addresses.php';
}
elseif ($uri === '/orders' && $method === 'POST') {
    require __DIR__ . '/routes/orders.php';
}
elseif (preg_match('#^/orders/([a-f0-9\-]{32,64})$#', $uri, $m) && $method === 'GET') {
    // Suivi par tracking_token
    require __DIR__ . '/routes/orders.php';
}
elseif (preg_match('#^/orders/([a-f0-9\-]{32,64})/(confirm|name)$#', $uri) && $method === 'PATCH') {
    require __DIR__ . '/routes/orders.php';
}
elseif ($uri === '/orders/mine' && $method === 'GET') {
    require __DIR__ . '/routes/orders.php';
}
elseif ($uri === '/payment/create-intent' && $method === 'POST') {
    require __DIR__ . '/routes/payment.php';
}
elseif ($uri === '/webhook/stripe' && $method === 'POST') {
    require __DIR__ . '/routes/webhook.php';
}
// Routes admin (préfixe /admin)
elseif (str_starts_with($uri, '/admin')) {
    require_once __DIR__ . '/middleware/Auth.php';
    auth_require_role(['admin']);
    $admin_uri = preg_replace('#^/admin#', '', $uri);
    if ($admin_uri === '/orders/drivers' && $method === 'GET') require __DIR__ . '/routes/admin/orders.php';
    elseif (str_starts_with($admin_uri, '/orders'))    require __DIR__ . '/routes/admin/orders.php';
    elseif (str_starts_with($admin_uri, '/products'))  require __DIR__ . '/routes/admin/products.php';
    elseif (str_starts_with($admin_uri, '/categories')) require __DIR__ . '/routes/admin/categories.php';
    elseif (str_starts_with($admin_uri, '/delivery-fees')) require __DIR__ . '/routes/admin/delivery.php';
    elseif (str_starts_with($admin_uri, '/options'))   require __DIR__ . '/routes/admin/options.php';
    elseif ($admin_uri === '/settings') require __DIR__ . '/routes/admin/settings.php';
    elseif ($admin_uri === '/upload') require __DIR__ . '/routes/admin/upload.php';
    elseif ($admin_uri === '/stats') require __DIR__ . '/routes/admin/stats.php';
    elseif (str_starts_with($admin_uri, '/users')) require __DIR__ . '/routes/admin/users.php';
    else json_error('Route admin non trouvée', 404);
}
// Routes livreur
elseif (str_starts_with($uri, '/delivery/orders')) {
    require __DIR__ . '/routes/delivery.php';
}
// Routes cuisine
elseif (str_starts_with($uri, '/kitchen')) {
    require_once __DIR__ . '/middleware/Auth.php';
    auth_require_role(['admin', 'kitchen']);
    $admin_uri = preg_replace('#^/kitchen#', '', $uri);
    require __DIR__ . '/routes/admin/orders.php';
}
else {
    json_error('Route non trouvée', 404);
}
