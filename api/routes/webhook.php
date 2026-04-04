<?php
require_once __DIR__ . '/../helpers/Sms.php';

// Lire le corps brut AVANT que PHP le parse (nécessaire pour la vérif de signature)
$payload    = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$secret     = env('STRIPE_WEBHOOK_SECRET', '');

$is_live = env('APP_ENV', 'development') === 'production';

// En mode live : le secret doit être configuré, sinon erreur 500
if ($is_live && (!$secret || str_contains($secret, '...'))) {
    http_response_code(500);
    exit(json_encode(['error' => 'STRIPE_WEBHOOK_SECRET non configuré']));
}

// Vérification de signature Stripe
// - mode live  : toujours vérifiée (secret obligatoire)
// - mode test  : vérifiée seulement si le secret est configuré
if ($secret && !str_contains($secret, '...')) {
    $parts     = [];
    foreach (explode(',', $sig_header) as $part) {
        [$k, $v] = explode('=', $part, 2);
        $parts[$k][] = $v;
    }
    $timestamp     = $parts['t'][0] ?? 0;
    $signed_string = $timestamp . '.' . $payload;
    $expected      = hash_hmac('sha256', $signed_string, $secret);
    $received      = $parts['v1'][0] ?? '';

    if (!hash_equals($expected, $received)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Signature invalide']));
    }

    // Rejeter les événements de plus de 5 minutes
    if (abs(time() - (int)$timestamp) > 300) {
        http_response_code(400);
        exit(json_encode(['error' => 'Événement trop ancien']));
    }
}

$event = json_decode($payload, true);
if (!$event) {
    http_response_code(400);
    exit(json_encode(['error' => 'JSON invalide']));
}

$type   = $event['type'] ?? '';
$object = $event['data']['object'] ?? [];

if ($type === 'payment_intent.succeeded') {
    $payment_id  = $object['id'] ?? '';
    $order_token = $object['metadata']['order_token'] ?? '';

    if ($order_token) {
        // Passer la commande de pending → received
        $stmt = db()->prepare(
            "UPDATE orders SET status='received', stripe_payment_id=?
             WHERE tracking_token=? AND status='pending'"
        );
        $stmt->execute([$payment_id, $order_token]);

        // Récupérer le numéro pour envoyer un SMS de confirmation
        $order = db()->prepare(
            'SELECT id, phone FROM orders WHERE tracking_token=? LIMIT 1'
        );
        $order->execute([$order_token]);
        $row = $order->fetch();

        if ($row) {
            $url = env('APP_URL', 'http://localhost') . '/track?token=' . $order_token;
            send_sms($row['phone'],
                "Votre commande #{$row['id']} a bien été reçue ! Suivez-la ici : $url"
            );
        }
    }
}

if ($type === 'payment_intent.payment_failed') {
    $order_token = $object['metadata']['order_token'] ?? '';
    if ($order_token) {
        db()->prepare(
            "UPDATE orders SET status='cancelled' WHERE tracking_token=? AND status='pending'"
        )->execute([$order_token]);
    }
}

http_response_code(200);
echo json_encode(['received' => true]);
