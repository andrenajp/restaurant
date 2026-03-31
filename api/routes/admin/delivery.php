<?php
preg_match('#/delivery-fees(?:/(\d+))?$#', $uri, $m);
$fee_id = isset($m[1]) ? (int) $m[1] : null;

if ($method === 'GET') {
    json_success(db()->query('SELECT * FROM delivery_fees ORDER BY id')->fetchAll());
}
if ($method === 'POST') {
    validate_required($body, ['zone_name', 'fee']);
    $stmt = db()->prepare('INSERT INTO delivery_fees (zone_name, fee, is_active) VALUES (?, ?, 1)');
    $stmt->execute([$body['zone_name'], (float) $body['fee']]);
    json_success(['id' => (int) db()->lastInsertId()], 201);
}
if ($method === 'PUT' && $fee_id) {
    validate_required($body, ['zone_name', 'fee']);
    $stmt = db()->prepare('UPDATE delivery_fees SET zone_name=?, fee=?, is_active=? WHERE id=?');
    $stmt->execute([$body['zone_name'], (float) $body['fee'], (int)($body['is_active'] ?? 1), $fee_id]);
    json_success(['updated' => true]);
}
if ($method === 'DELETE' && $fee_id) {
    db()->prepare('DELETE FROM delivery_fees WHERE id = ?')->execute([$fee_id]);
    json_success(['deleted' => true]);
}

json_error('Route delivery-fees invalide', 405);
