<?php
$admin_uri = preg_replace('#^/api/admin#', '', $uri);

// GET /api/admin/orders/drivers — liste des livreurs
if ($method === 'GET' && $admin_uri === '/orders/drivers') {
    $stmt = db()->query("SELECT id, name, phone FROM users WHERE role='delivery' ORDER BY name");
    json_success($stmt->fetchAll());
}

// GET /api/admin/orders — liste avec filtres
if ($method === 'GET') {
    $where = [];
    $params = [];

    if (!empty($_GET['status'])) {
        $where[] = 'o.status = ?';
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['type'])) {
        $where[] = 'o.type = ?';
        $params[] = $_GET['type'];
    }

    $sql = 'SELECT o.*, COUNT(oi.id) as item_count,
                   u.name AS driver_name
            FROM orders o
            LEFT JOIN order_items oi ON oi.order_id = o.id
            LEFT JOIN users u ON u.id = o.delivery_driver_id'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' GROUP BY o.id ORDER BY o.created_at DESC LIMIT 100';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    json_success($stmt->fetchAll());
}

// PATCH /api/admin/orders/{id} — changer le statut
if ($method === 'PATCH') {
    preg_match('#/orders/(\d+)$#', $admin_uri, $m);
    $order_id = (int) ($m[1] ?? 0);
    if (!$order_id) json_error('ID commande requis', 422);

    validate_required($body, ['status']);
    $valid_statuses = ['received', 'in_preparation', 'ready', 'en_route', 'delivered', 'cancelled'];
    if (!in_array($body['status'], $valid_statuses)) {
        json_error('Statut invalide', 422);
    }

    // Récupérer la commande pour le SMS
    $order = db()->prepare('SELECT id, phone, type, tracking_token FROM orders WHERE id=?');
    $order->execute([$order_id]);
    $row = $order->fetch();
    if (!$row) json_error('Commande introuvable', 404);

    $stmt = db()->prepare('UPDATE orders SET status = ? WHERE id = ?');
    $stmt->execute([$body['status'], $order_id]);

    // Envoyer un SMS selon le nouveau statut
    require_once __DIR__ . '/../../helpers/Sms.php';
    $url = env('APP_URL', 'http://localhost') . '/track?token=' . $row['tracking_token'];

    if ($body['status'] === 'ready') {
        if ($row['type'] === 'pickup') {
            send_sms($row['phone'],
                "Votre commande #{$row['id']} est prête ! Venez la récupérer au restaurant. Suivi : $url"
            );
        } else {
            send_sms($row['phone'],
                "Votre commande #{$row['id']} est prête et sera bientôt prise en charge par notre livreur. Suivi : $url"
            );
        }
    }

    if ($body['status'] === 'en_route') {
        send_sms($row['phone'],
            "Votre commande #{$row['id']} est en route ! Elle arrive bientôt. Suivi : $url"
        );
    }

    json_success(['updated' => true, 'status' => $body['status']]);
}

// PATCH /api/admin/orders/{id}/assign — assigner un livreur
if ($method === 'PATCH' && preg_match('#/orders/(\d+)/assign$#', $admin_uri, $m)) {
    $order_id  = (int) $m[1];
    $driver_id = isset($body['driver_id']) ? (int) $body['driver_id'] : null;

    $check = db()->prepare('SELECT id FROM orders WHERE id=?');
    $check->execute([$order_id]);
    if (!$check->fetch()) json_error('Commande introuvable', 404);

    if ($driver_id) {
        $dcheck = db()->prepare("SELECT id FROM users WHERE id=? AND role='delivery'");
        $dcheck->execute([$driver_id]);
        if (!$dcheck->fetch()) json_error('Livreur introuvable', 404);
    }

    db()->prepare('UPDATE orders SET delivery_driver_id=? WHERE id=?')
        ->execute([$driver_id ?: null, $order_id]);

    json_success(['assigned' => true, 'driver_id' => $driver_id]);
}

json_error('Méthode non supportée pour admin/orders', 405);
