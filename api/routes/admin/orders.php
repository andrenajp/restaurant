<?php
// $admin_uri est déjà défini par index.php

// GET /api/admin/orders/drivers
if ($method === 'GET' && $admin_uri === '/orders/drivers') {
    $stmt = db()->query("SELECT id, name, phone FROM users WHERE role='delivery' ORDER BY name");
    json_success($stmt->fetchAll());
}

// GET /api/admin/orders avec filtres avancés
if ($method === 'GET') {
    $where = [];
    $params = [];

    $where[] = "o.status != 'awaiting_payment'";

    if (!empty($_GET['status'])) {
        $where[] = 'o.status = ?';
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['type'])) {
        $where[] = 'o.type = ?';
        $params[] = $_GET['type'];
    }
    if (!empty($_GET['date_from'])) {
        $where[] = 'DATE(o.created_at) >= ?';
        $params[] = $_GET['date_from'];
    }
    if (!empty($_GET['date_to'])) {
        $where[] = 'DATE(o.created_at) <= ?';
        $params[] = $_GET['date_to'];
    }
    if (!empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $where[] = '(o.phone LIKE ? OR CAST(o.id AS CHAR) LIKE ?)';
        $params[] = $search;
        $params[] = $search;
    }

    $sql = 'SELECT o.*, COUNT(oi.id) as item_count,
                   u.name AS driver_name
            FROM orders o
            LEFT JOIN order_items oi ON oi.order_id = o.id
            LEFT JOIN users u ON u.id = o.delivery_driver_id'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' GROUP BY o.id ORDER BY o.created_at DESC LIMIT 200';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    json_success($stmt->fetchAll());
}

// PATCH /api/admin/orders/{id}/assign
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

    // Log
    $actor = auth_get_payload()['sub'] ?? null;
    if ($actor) {
        $stmt = db()->prepare('INSERT INTO admin_logs (admin_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$actor, 'assign_driver', 'order', $order_id, json_encode(['driver_id' => $driver_id])]);
    }

    json_success(['assigned' => true, 'driver_id' => $driver_id]);
}

// PATCH /api/admin/orders/{id} avec motif d'annulation
if ($method === 'PATCH') {
    preg_match('#/orders/(\d+)$#', $admin_uri, $m);
    $order_id = (int) ($m[1] ?? 0);
    if (!$order_id) json_error('ID commande requis', 422);

    validate_required($body, ['status']);
    $valid_statuses = ['received', 'in_preparation', 'ready', 'en_route', 'delivered', 'cancelled'];
    if (!in_array($body['status'], $valid_statuses)) {
        json_error('Statut invalide', 422);
    }

    // Récupérer la commande
    $order = db()->prepare('SELECT id, phone, type, tracking_token, status FROM orders WHERE id=?');
    $order->execute([$order_id]);
    $row = $order->fetch();
    if (!$row) json_error('Commande introuvable', 404);

    // Si annulation, enregistrer le motif
    if ($body['status'] === 'cancelled' && !empty($body['cancellation_reason'])) {
        $actor = auth_get_payload()['sub'] ?? null;
        $stmt = db()->prepare('INSERT INTO order_cancellation_reasons (order_id, reason, cancelled_by) VALUES (?, ?, ?)');
        $stmt->execute([$order_id, $body['cancellation_reason'], $actor]);
    }

    $stmt = db()->prepare('UPDATE orders SET status = ? WHERE id = ?');
    $stmt->execute([$body['status'], $order_id]);

    // Envoyer un SMS selon le nouveau statut
    require_once __DIR__ . '/../../helpers/Sms.php';
    $url = env('APP_URL', 'http://localhost') . '/track?token=' . $row['tracking_token'];

    if ($body['status'] === 'ready') {
        if ($row['type'] === 'pickup') {
            send_sms(
                $row['phone'],
                "Votre commande #{$row['id']} est prête ! Venez la récupérer au restaurant. Suivi : $url"
            );
        } else {
            send_sms(
                $row['phone'],
                "Votre commande #{$row['id']} est prête et sera bientôt prise en charge par notre livreur. Suivi : $url"
            );
        }
    }

    if ($body['status'] === 'en_route') {
        send_sms(
            $row['phone'],
            "Votre commande #{$row['id']} est en route ! Elle arrive bientôt. Suivi : $url"
        );
    }

    if ($body['status'] === 'cancelled') {
        send_sms(
            $row['phone'],
            "Votre commande #{$row['id']} a été annulée. Contactez-nous pour plus d'informations."
        );
    }

    // Log
    $actor = auth_get_payload()['sub'] ?? null;
    if ($actor) {
        $stmt = db()->prepare('INSERT INTO admin_logs (admin_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$actor, 'update_order_status', 'order', $order_id, json_encode([
            'old_status' => $row['status'],
            'new_status' => $body['status'],
            'reason' => $body['cancellation_reason'] ?? null
        ])]);
    }

    json_success(['updated' => true, 'status' => $body['status']]);
}

json_error('Méthode non supportée pour admin/orders', 405);
