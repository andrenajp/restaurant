<?php
require_once __DIR__ . '/../middleware/Auth.php';

// GET /api/orders/mine — historique commandes (auth requise)
if ($method === 'GET' && $uri === '/orders/mine') {
    $payload = auth_get_payload();
    if (!$payload) json_error('Non authentifié', 401);
    $stmt = db()->prepare(
        'SELECT id, type, status, total, tracking_token, created_at
         FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 20'
    );
    $stmt->execute([$payload['sub']]);
    json_success($stmt->fetchAll());
}

// PATCH /api/orders/{token}/confirm — passer de pending à received
if ($method === 'PATCH' && preg_match('#^/orders/([a-f0-9\-]{32,64})/confirm$#', $uri, $m)) {
    $token = $m[1];
    $stmt = db()->prepare(
        "UPDATE orders SET status='received' WHERE tracking_token=? AND status='pending'"
    );
    $stmt->execute([$token]);
    json_success(['confirmed' => true]);
}

// PATCH /api/orders/{token}/name — enregistrer le prénom (facultatif)
if ($method === 'PATCH' && preg_match('#^/orders/([a-f0-9\-]{32,64})/name$#', $uri, $m)) {
    $token = $m[1];
    $name  = trim($body['customer_name'] ?? '');
    if ($name) {
        db()->prepare('UPDATE orders SET customer_name=? WHERE tracking_token=?')
            ->execute([$name, $token]);
    }
    json_success(['updated' => true]);
}

// GET /api/orders/{tracking_token} — suivi public (sans auth)
if ($method === 'GET' && preg_match('#^/orders/([a-f0-9\-]{32,64})$#', $uri, $m)) {
    $token = $m[1];

    $stmt = db()->prepare(
        'SELECT o.id, o.status, o.type, o.total, o.delivery_fee, o.customer_name, o.created_at
         FROM orders o WHERE o.tracking_token = ?'
    );
    $stmt->execute([$token]);
    $order = $stmt->fetch();

    if (!$order) json_error('Commande introuvable', 404);

    $stmt2 = db()->prepare(
        'SELECT oi.id as item_id, oi.quantity, oi.unit_price, oi.options_json, p.name as product_name
         FROM order_items oi
         JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = ?'
    );
    $stmt2->execute([$order['id']]);
    $items = $stmt2->fetchAll();

    // Résoudre les IDs d'options en noms
    $items_out = [];
    foreach ($items as $r) {
        $raw_opts = json_decode($r['options_json'] ?? '[]', true) ?? [];
        // Normaliser : accepte soit des IDs entiers, soit des objets {id:...}
        $opt_ids = array_values(array_filter(array_map(
            fn($o) => is_array($o) ? (int)($o['id'] ?? 0) : (int)$o,
            $raw_opts
        )));
        $opt_labels = [];
        if (!empty($opt_ids)) {
            $placeholders = implode(',', array_fill(0, count($opt_ids), '?'));
            $opt_stmt = db()->prepare(
                "SELECT option_name, group_name FROM product_options WHERE id IN ($placeholders)"
            );
            $opt_stmt->execute($opt_ids);
            $opt_labels = $opt_stmt->fetchAll();
        }
        $items_out[] = [
            'product_name' => $r['product_name'],
            'quantity'     => (int) $r['quantity'],
            'unit_price'   => (float) $r['unit_price'],
            'options'      => $opt_labels,
        ];
    }

    json_success([
        'id'            => (int) $order['id'],
        'status'        => $order['status'],
        'type'          => $order['type'],
        'total'         => (float) $order['total'],
        'delivery_fee'  => (float) $order['delivery_fee'],
        'customer_name' => $order['customer_name'],
        'created_at'    => $order['created_at'],
        'items'         => $items_out,
    ]);
}

// POST /api/orders — création directe de commande (sans paiement Stripe)
if ($method === 'POST' && $uri === '/orders') {
    validate_required($body, ['phone', 'type', 'items']);

    if (!in_array($body['type'], ['pickup', 'delivery'])) {
        json_error('Type invalide : pickup ou delivery', 422);
    }
    if ($body['type'] === 'delivery' && empty($body['delivery_address'])) {
        json_error('Adresse requise pour une livraison', 422);
    }
    if (!is_array($body['items']) || count($body['items']) === 0) {
        json_error('Panier vide', 422);
    }
    if (!validate_phone($body['phone'])) {
        json_error('Numéro de téléphone invalide', 422);
    }

    $total = 0.0;
    $items_validated = [];
    foreach ($body['items'] as $item) {
        if (empty($item['product_id']) || empty($item['quantity'])) {
            json_error('Item invalide dans le panier', 422);
        }
        $stmt = db()->prepare('SELECT id, price FROM products WHERE id = ? AND is_available = 1');
        $stmt->execute([(int) $item['product_id']]);
        $product = $stmt->fetch();
        if (!$product) json_error("Produit introuvable : {$item['product_id']}", 422);

        $unit_price = (float) $product['price'];
        if (!empty($item['options'])) {
            foreach ($item['options'] as $opt_id) {
                $stmt2 = db()->prepare('SELECT extra_price FROM product_options WHERE id = ? AND product_id = ?');
                $stmt2->execute([(int) $opt_id, (int) $item['product_id']]);
                $opt = $stmt2->fetch();
                if ($opt) $unit_price += (float) $opt['extra_price'];
            }
        }
        $qty = max(1, (int) $item['quantity']);
        $total += $unit_price * $qty;
        $items_validated[] = ['product_id' => (int) $item['product_id'], 'quantity' => $qty, 'unit_price' => $unit_price, 'options' => $item['options'] ?? []];
    }

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

    $tracking_token = bin2hex(random_bytes(16));
    $user_id = null;
    $payload = auth_get_payload();
    if ($payload) $user_id = $payload['sub'];

    db()->beginTransaction();
    try {
        $stmt = db()->prepare(
            'INSERT INTO orders (user_id, phone, customer_name, type, delivery_address, total, delivery_fee, tracking_token)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $user_id,
            $body['phone'],
            $body['customer_name'] ?? null,
            $body['type'],
            $body['delivery_address'] ?? null,
            $total,
            $delivery_fee,
            $tracking_token,
        ]);
        $order_id = (int) db()->lastInsertId();

        $stmt_item = db()->prepare(
            'INSERT INTO order_items (order_id, product_id, quantity, unit_price, options_json) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($items_validated as $item) {
            $stmt_item->execute([$order_id, $item['product_id'], $item['quantity'], $item['unit_price'], json_encode($item['options'])]);
        }
        db()->commit();
    } catch (\Exception $e) {
        db()->rollBack();
        json_error('Erreur création commande', 500);
    }

    json_success(['order_id' => $order_id, 'tracking_token' => $tracking_token, 'total' => $total, 'delivery_fee' => $delivery_fee], 201);
}

json_error('Route commande non trouvée', 404);
