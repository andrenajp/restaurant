<?php
// POST /api/payment/create-intent
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../middleware/Auth.php';

// Lire le body
$raw_input = file_get_contents('php://input');
$body = json_decode($raw_input, true) ?? [];

// Validation
if (empty($body['items']) || empty($body['phone'])) {
    json_error('Données manquantes: items et phone requis', 422);
}
if (!in_array($body['type'] ?? '', ['pickup', 'delivery'])) {
    json_error('Type invalide : pickup ou delivery', 422);
}
if (($body['type'] === 'delivery') && empty($body['delivery_address'])) {
    json_error('Adresse requise pour une livraison', 422);
}

// customer_name : facultatif, nettoyé et tronqué
$customer_name = isset($body['customer_name']) ? trim($body['customer_name']) : null;
if ($customer_name !== null) {
    $customer_name = mb_substr($customer_name, 0, 150);
    if ($customer_name === '') $customer_name = null;
}

// Calcul du total depuis la DB (prix réels, pas ceux du client)
$total = 0.0;
$items_validated = [];

foreach ($body['items'] as $item) {
    if (empty($item['id'])) json_error('Item invalide dans le panier', 422);

    $stmt = db()->prepare('SELECT id, price FROM products WHERE id = ? AND is_available = 1');
    $stmt->execute([(int) $item['id']]);
    $product = $stmt->fetch();
    if (!$product) json_error('Produit introuvable : ' . $item['id'], 422);

    $unit_price = (float) $product['price'];

    if (!empty($item['options'])) {
        foreach ($item['options'] as $opt) {
            $opt_id = is_array($opt) ? (int)($opt['id'] ?? 0) : (int)$opt;
            if (!$opt_id) continue;
            $stmt2 = db()->prepare('SELECT extra_price FROM product_options WHERE id = ? AND product_id = ?');
            $stmt2->execute([$opt_id, (int)$item['id']]);
            $opt_row = $stmt2->fetch();
            if ($opt_row) $unit_price += (float) $opt_row['extra_price'];
        }
    }

    $qty = min(99, max(1, (int)($item['qty'] ?? 1)));
    $total += $unit_price * $qty;
    $items_validated[] = [
        'product_id' => (int) $product['id'],
        'quantity'   => $qty,
        'unit_price' => $unit_price,
        'options'    => $item['options'] ?? [],
    ];
}

// Frais de livraison
$delivery_fee = 0.0;
if ($body['type'] === 'delivery') {
    $settings   = db()->query('SELECT delivery_free_above FROM settings LIMIT 1')->fetch();
    $free_above = (float) ($settings['delivery_free_above'] ?? 25);
    if ($total < $free_above) {
        $fee_row = db()->query('SELECT fee FROM delivery_fees WHERE is_active = 1 LIMIT 1')->fetch();
        $delivery_fee = $fee_row ? (float) $fee_row['fee'] : 3.50;
    }
}
$total += $delivery_fee;

// Utilisateur connecté (facultatif — la commande fonctionne sans compte)
$user_id = null;
$payload = auth_get_payload();
if ($payload && isset($payload['sub'])) {
    $user_id = (int) $payload['sub'];

    // Récupérer le nom depuis la DB — priorité sur ce que le client envoie
    $user_row = db()->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
    $user_row->execute([$user_id]);
    $user_data = $user_row->fetch();
    if ($user_data && !empty($user_data['name'])) {
        $customer_name = $user_data['name'];
    }
}

// Token de suivi (utilisé après confirmation par le webhook)
$order_token = bin2hex(random_bytes(16));

// Clé Stripe
$stripe_sk = env('STRIPE_SK', '');

// ── Helper : INSERT dans pending_intents ─────────────────────────────────────
$insert_pending = function (string $pi_id) use (
    $order_token,
    $user_id,
    $customer_name,
    $body,
    $total,
    $delivery_fee,
    $items_validated
) {
    db()->prepare('
        INSERT INTO pending_intents
            (payment_intent_id, order_token, user_id, customer_name,
             phone, type, delivery_address, total, delivery_fee, items_json, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ')->execute([
        $pi_id,
        $order_token,
        $user_id,
        $customer_name,
        $body['phone'],
        $body['type'],
        $body['delivery_address'] ?? null,
        round($total, 2),
        round($delivery_fee, 2),
        json_encode($items_validated),
    ]);
};

// ── Mode mock (pas de clé Stripe configurée) ─────────────────────────────────
if (empty($stripe_sk) || (!str_starts_with($stripe_sk, 'sk_test_') && !str_starts_with($stripe_sk, 'sk_live_'))) {
    $mock_pi_id = 'mock_' . bin2hex(random_bytes(16));
    $insert_pending($mock_pi_id);

    json_success([
        'client_secret' => 'mock_secret_' . $mock_pi_id,
        'order_token'   => $order_token,
        'total'         => round($total, 2),
        'delivery_fee'  => round($delivery_fee, 2),
        'mock_mode'     => true,
    ]);
}

// ── Mode réel Stripe : créer UNIQUEMENT le PaymentIntent ─────────────────────
$amount_cents = (int) round($total * 100);

$ch = curl_init('https://api.stripe.com/v1/payment_intents');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_USERPWD        => $stripe_sk . ':',
    CURLOPT_POSTFIELDS     => http_build_query([
        'amount'                => $amount_cents,
        'currency'              => 'eur',
        'metadata[order_token]' => $order_token,
    ]),
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 2,
]);

$response   = curl_exec($ch);
$http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) json_error('Erreur cURL: ' . $curl_error, 502);

if ($http_code !== 200) {
    $stripe_data = json_decode($response, true);
    json_error('Stripe: ' . ($stripe_data['error']['message'] ?? 'Erreur inconnue'), 502);
}

$stripe_data       = json_decode($response, true);
$payment_intent_id = $stripe_data['id'];

// Stocker le panier — la commande sera créée par le webhook après confirmation
try {
    $insert_pending($payment_intent_id);
} catch (Exception $e) {
    json_error('Erreur base de données: ' . $e->getMessage(), 500);
}

json_success([
    'client_secret' => $stripe_data['client_secret'],
    'order_token'   => $order_token,
    'total'         => round($total, 2),
    'delivery_fee'  => round($delivery_fee, 2),
]);
