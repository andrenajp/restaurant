<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Sms.php';

// Lire le corps brut AVANT que PHP le parse (nécessaire pour la vérif de signature)
$payload    = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$secret     = env('STRIPE_WEBHOOK_SECRET', '');

$is_live = env('APP_ENV', 'development') === 'production';

if ($is_live && (!$secret || str_contains($secret, '...'))) {
    http_response_code(500);
    exit(json_encode(['error' => 'STRIPE_WEBHOOK_SECRET non configuré']));
}

// ── Vérification de signature Stripe ─────────────────────────────────────────
if ($secret && !str_contains($secret, '...')) {
    $parts = [];
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

// ── Paiement réussi : créer la commande dans orders ──────────────────────────
if ($type === 'payment_intent.succeeded') {
    $payment_intent_id = $object['id'] ?? '';
    $order_token       = $object['metadata']['order_token'] ?? '';

    if (!$order_token) {
        http_response_code(200);
        exit(json_encode(['received' => true]));
    }

    // Récupérer TOUTES les données du panier depuis pending_intents
    $pi_stmt = db()->prepare('SELECT * FROM pending_intents WHERE payment_intent_id = ? LIMIT 1');
    $pi_stmt->execute([$payment_intent_id]);
    $pi = $pi_stmt->fetch();

    if (!$pi) {
        // Déjà traité ou introuvable — répondre 200 pour éviter que Stripe ne réessaie
        http_response_code(200);
        exit(json_encode(['received' => true]));
    }

    $items = json_decode($pi['items_json'], true) ?? [];

    db()->beginTransaction();
    try {
        // Créer la commande avec tous les champs du schéma orders
        $stmt = db()->prepare('
            INSERT INTO orders
                (user_id, phone, customer_name, type, delivery_address,
                 status, total, delivery_fee, tracking_token, stripe_payment_id, created_at)
            VALUES (?, ?, ?, ?, ?, "received", ?, ?, ?, ?, NOW())
        ');
        $stmt->execute([
            $pi['user_id'],          // NULL si commande sans compte, c'est normal
            $pi['phone'],
            $pi['customer_name'],    // NULL si non fourni au checkout
            $pi['type'],
            $pi['delivery_address'], // NULL si pickup
            $pi['total'],
            $pi['delivery_fee'],
            $pi['order_token'],
            $payment_intent_id,
        ]);
        $order_id = (int) db()->lastInsertId();

        // Insérer les lignes de commande
        $stmt_item = db()->prepare('
            INSERT INTO order_items (order_id, product_id, quantity, unit_price, options_json)
            VALUES (?, ?, ?, ?, ?)
        ');
        foreach ($items as $item) {
            $stmt_item->execute([
                $order_id,
                $item['product_id'],
                $item['quantity'],
                $item['unit_price'],
                json_encode($item['options'] ?? []),
            ]);
        }

        // Supprimer le pending_intent (données temporaires, plus nécessaires)
        db()->prepare('DELETE FROM pending_intents WHERE payment_intent_id = ?')
            ->execute([$payment_intent_id]);

        db()->commit();
    } catch (Exception $e) {
        db()->rollBack();
        http_response_code(500);
        exit(json_encode(['error' => 'Erreur création commande: ' . $e->getMessage()]));
    }

    // SMS de confirmation
    $url = env('APP_URL', 'http://localhost') . '/track?token=' . $order_token;
    send_sms(
        $pi['phone'],
        "Votre commande #{$order_id} a bien été reçue ! Suivez-la ici : $url"
    );
}

// ── Paiement échoué : nettoyer le pending_intent ─────────────────────────────
if ($type === 'payment_intent.payment_failed') {
    $payment_intent_id = $object['id'] ?? '';
    if ($payment_intent_id) {
        db()->prepare('DELETE FROM pending_intents WHERE payment_intent_id = ?')
            ->execute([$payment_intent_id]);
    }
}

http_response_code(200);
echo json_encode(['received' => true]);
