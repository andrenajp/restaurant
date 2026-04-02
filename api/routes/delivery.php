<?php
require_once __DIR__ . '/../middleware/Auth.php';
auth_require_role(['delivery', 'admin']);

$payload   = auth_get_payload();
$driver_id = (int) $payload['sub'];

preg_match('#^/delivery/orders(?:/(\d+))?(?:/(\w+))?$#', $uri, $m);
$order_id = isset($m[1]) ? (int) $m[1] : null;
$action   = $m[2] ?? null;

// GET /api/delivery/orders — commandes assignées à ce livreur
if ($method === 'GET' && !$order_id) {
    $stmt = db()->prepare(
        "SELECT o.id, o.phone, o.customer_name, o.delivery_address,
                o.status, o.total, o.delivery_fee, o.tracking_token, o.created_at, o.type
         FROM orders o
         WHERE o.delivery_driver_id = ?
           AND o.status IN ('ready','en_route')
         ORDER BY o.created_at DESC"
    );
    $stmt->execute([$driver_id]);
    $orders = $stmt->fetchAll();

    foreach ($orders as &$order) {
        $items = db()->prepare(
            'SELECT oi.quantity, p.name AS product_name
             FROM order_items oi JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = ?'
        );
        $items->execute([$order['id']]);
        $order['items'] = $items->fetchAll();
    }

    json_success($orders);
}

// PATCH /api/delivery/orders/{id} — changer statut (en_route ou delivered)
if ($method === 'PATCH' && $order_id) {
    $allowed = ['en_route', 'delivered'];
    if (!isset($body['status']) || !in_array($body['status'], $allowed)) {
        json_error('Statut invalide pour un livreur', 422);
    }

    // Vérifier que la commande appartient bien à ce livreur
    $check = db()->prepare('SELECT id, phone, tracking_token FROM orders WHERE id=? AND delivery_driver_id=?');
    $check->execute([$order_id, $driver_id]);
    $row = $check->fetch();
    if (!$row) json_error('Commande introuvable ou non assignée', 404);

    db()->prepare('UPDATE orders SET status=? WHERE id=?')
        ->execute([$body['status'], $order_id]);

    // SMS client
    require_once __DIR__ . '/../helpers/Sms.php';
    $url = env('APP_URL', 'http://localhost') . '/track?token=' . $row['tracking_token'];
    if ($body['status'] === 'en_route') {
        send_sms($row['phone'], "Votre commande est en route ! Elle arrive bientôt. Suivi : $url");
    }

    json_success(['updated' => true, 'status' => $body['status']]);
}

json_error('Route livreur non trouvée', 404);
