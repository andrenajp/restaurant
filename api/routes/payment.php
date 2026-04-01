<?php
// POST /api/payment/create-intent
// Valide le panier côté serveur, crée une commande pending et un PaymentIntent Stripe
// Retourne {client_secret, order_token, total, delivery_fee}

validate_required($body, ['items', 'phone', 'type']);

if (!is_array($body['items']) || count($body['items']) === 0) {
    json_error('Panier vide', 422);
}

$valid_types = ['pickup', 'delivery'];
if (!in_array($body['type'], $valid_types)) {
    json_error('Type invalide', 422);
}
if ($body['type'] === 'delivery' && empty(trim($body['delivery_address'] ?? ''))) {
    json_error('Adresse de livraison requise', 422);
}

// Calculer le total côté serveur
$product_ids = array_unique(array_map(fn($i) => (int)($i['id'] ?? 0), $body['items']));
$product_ids = array_filter($product_ids);
if (empty($product_ids)) json_error('Produits invalides', 422);

$placeholders = implode(',', array_fill(0, count($product_ids), '?'));
$stmt = db()->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND is_available=1");
$stmt->execute(array_values($product_ids));
$db_products = [];
foreach ($stmt->fetchAll() as $p) $db_products[$p['id']] = $p;

$total = 0;
$line_items = [];
foreach ($body['items'] as $item) {
    $pid = (int)($item['id'] ?? 0);
    if (!isset($db_products[$pid])) json_error("Produit $pid introuvable ou indisponible", 422);
    $qty = max(1, (int)($item['qty'] ?? 1));
    $unit_price = (float)$db_products[$pid]['price'];

    $options_validated = [];
    if (!empty($item['options']) && is_array($item['options'])) {
        $opt_ids = array_filter(array_unique(array_map(fn($o) => (int)($o['id'] ?? 0), $item['options'])));
        if ($opt_ids) {
            $op = implode(',', array_fill(0, count($opt_ids), '?'));
            $ostmt = db()->prepare("SELECT * FROM product_options WHERE id IN ($op) AND product_id = ?");
            $ostmt->execute(array_merge(array_values($opt_ids), [$pid]));
            foreach ($ostmt->fetchAll() as $opt) {
                $unit_price += (float)$opt['extra_price'];
                $options_validated[] = $opt;
            }
        }
    }

    $total += $unit_price * $qty;
    $line_items[] = ['pid' => $pid, 'qty' => $qty, 'unit_price' => $unit_price, 'options' => $options_validated];
}

// Frais de livraison
$delivery_fee = 0;
if ($body['type'] === 'delivery') {
    $settings = db()->query('SELECT delivery_free_above FROM settings LIMIT 1')->fetch();
    $free_above = (float)($settings['delivery_free_above'] ?? 0);
    $fee_row = db()->query('SELECT fee FROM delivery_fees WHERE is_active=1 ORDER BY id LIMIT 1')->fetch();
    if ($fee_row) {
        $delivery_fee = ($free_above > 0 && $total >= $free_above) ? 0.0 : (float)$fee_row['fee'];
    }
    $total += $delivery_fee;
}

// Créer la commande en DB (statut 'pending' avant confirmation paiement)
$order_token = bin2hex(random_bytes(16));
$user_id = null;
require_once __DIR__ . '/../middleware/Auth.php';
$payload = auth_get_payload();
if ($payload) $user_id = $payload['uid'];

$stmt = db()->prepare(
    'INSERT INTO orders (user_id, phone, type, delivery_address, status, total, delivery_fee, tracking_token, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
);
$stmt->execute([
    $user_id,
    $body['phone'],
    $body['type'],
    $body['delivery_address'] ?? null,
    'pending',
    round($total, 2),
    round($delivery_fee, 2),
    $order_token,
]);
$order_id = (int)db()->lastInsertId();

foreach ($line_items as $li) {
    $stmt2 = db()->prepare(
        'INSERT INTO order_items (order_id, product_id, quantity, unit_price, options_json)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt2->execute([
        $order_id,
        $li['pid'],
        $li['qty'],
        $li['unit_price'],
        json_encode($li['options']),
    ]);
}

// Créer le PaymentIntent Stripe via cURL
$stripe_sk = env('STRIPE_SK');
// Mock si clé absente ou placeholder (contient '...')
if (!$stripe_sk || str_contains($stripe_sk, '...')) {
    // Pas de clé Stripe → retourner un mock pour développement
    json_success([
        'client_secret' => 'test_secret_no_stripe_configured',
        'order_token'   => $order_token,
        'total'         => round($total, 2),
        'delivery_fee'  => round($delivery_fee, 2),
    ]);
}

$amount_cents = (int)round($total * 100);

$ch = curl_init('https://api.stripe.com/v1/payment_intents');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_USERPWD        => $stripe_sk . ':',
    CURLOPT_POSTFIELDS     => http_build_query([
        'amount'                => $amount_cents,
        'currency'              => 'eur',
        'metadata[order_id]'    => $order_id,
        'metadata[order_token]' => $order_token,
    ]),
]);
$stripe_response = curl_exec($ch);
$http_code       = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$stripe_data = json_decode($stripe_response, true);

if ($http_code !== 200 || empty($stripe_data['client_secret'])) {
    // Nettoyer la commande si Stripe échoue
    db()->prepare('DELETE FROM order_items WHERE order_id=?')->execute([$order_id]);
    db()->prepare('DELETE FROM orders WHERE id=?')->execute([$order_id]);
    json_error('Erreur Stripe : ' . ($stripe_data['error']['message'] ?? 'inconnue'), 502);
}

db()->prepare('UPDATE orders SET stripe_payment_id=? WHERE id=?')
    ->execute([$stripe_data['id'], $order_id]);

json_success([
    'client_secret' => $stripe_data['client_secret'],
    'order_token'   => $order_token,
    'total'         => round($total, 2),
    'delivery_fee'  => round($delivery_fee, 2),
]);
