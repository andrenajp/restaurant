<?php

// GET /api/orders/{tracking_token} — suivi public
if ($method === 'GET') {
    preg_match('#/orders/([a-f0-9\-]{36})$#', $uri, $m);
    $token = $m[1] ?? '';

    $stmt = db()->prepare(
        'SELECT o.id, o.status, o.type, o.customer_name, o.created_at,
                oi.quantity, oi.unit_price, oi.options_json,
                p.name as product_name
         FROM orders o
         JOIN order_items oi ON oi.order_id = o.id
         JOIN products p ON p.id = oi.product_id
         WHERE o.tracking_token = ?'
    );
    $stmt->execute([$token]);
    $rows = $stmt->fetchAll();

    if (!$rows) json_error('Commande introuvable', 404);

    $order = [
        'status'        => $rows[0]['status'],
        'type'          => $rows[0]['type'],
        'customer_name' => $rows[0]['customer_name'],
        'created_at'    => $rows[0]['created_at'],
        'items'         => array_map(fn($r) => [
            'name'        => $r['product_name'],
            'quantity'    => (int) $r['quantity'],
            'unit_price'  => (float) $r['unit_price'],
            'options'     => json_decode($r['options_json'] ?? 'null', true),
        ], $rows),
    ];

    json_success($order);
}

// POST /api/orders — création de commande
if ($method === 'POST') {
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

    // Calculer le total côté serveur
    $total = 0.0;
    $items_validated = [];
    foreach ($body['items'] as $item) {
        if (empty($item['product_id']) || empty($item['quantity'])) {
            json_error('Item invalide dans le panier', 422);
        }
        $stmt = db()->prepare('SELECT id, price, name FROM products WHERE id = ? AND is_available = 1');
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
        $items_validated[] = [
            'product_id'  => (int) $item['product_id'],
            'quantity'    => $qty,
            'unit_price'  => $unit_price,
            'options'     => $item['options'] ?? [],
        ];
    }

    // Frais de livraison
    $delivery_fee = 0.0;
    if ($body['type'] === 'delivery') {
        $settings = db()->query('SELECT delivery_free_above FROM settings LIMIT 1')->fetch();
        $free_above = (float) ($settings['delivery_free_above'] ?? 25);
        if ($total < $free_above) {
            $fee_row = db()->query('SELECT fee FROM delivery_fees WHERE is_active = 1 LIMIT 1')->fetch();
            $delivery_fee = $fee_row ? (float) $fee_row['fee'] : 3.50;
        }
    }
    $total += $delivery_fee;

    $tracking_token = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff)
    );

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
            $stmt_item->execute([
                $order_id,
                $item['product_id'],
                $item['quantity'],
                $item['unit_price'],
                json_encode($item['options']),
            ]);
        }
        db()->commit();
    } catch (\Exception $e) {
        db()->rollBack();
        json_error('Erreur création commande', 500);
    }

    json_success([
        'order_id'       => $order_id,
        'tracking_token' => $tracking_token,
        'total'          => $total,
        'delivery_fee'   => $delivery_fee,
    ], 201);
}

json_error('Méthode non supportée', 405);
