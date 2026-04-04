<?php
// /api/admin/options?product_id=X  — GET toutes les options d'un plat
// /api/admin/options                — POST créer une option
// /api/admin/options/{id}           — PUT modifier une option
// /api/admin/options/{id}           — DELETE supprimer une option

preg_match('#/options(?:/(\d+))?$#', $uri, $m);
$opt_id = isset($m[1]) ? (int) $m[1] : null;

if ($method === 'GET') {
    $product_id = isset($_GET['product_id']) ? (int) $_GET['product_id'] : null;
    if (!$product_id) json_error('product_id requis', 422);
    $stmt = db()->prepare('SELECT * FROM product_options WHERE product_id = ? ORDER BY group_name, id');
    $stmt->execute([$product_id]);
    json_success($stmt->fetchAll());
}

if ($method === 'POST') {
    validate_required($body, ['product_id', 'group_name', 'option_name']);
    validate_lengths($body, ['group_name' => 80, 'option_name' => 80]);
    $stmt = db()->prepare(
        'INSERT INTO product_options (product_id, group_name, option_name, extra_price, is_default) VALUES (?,?,?,?,?)'
    );
    $stmt->execute([
        (int) $body['product_id'],
        $body['group_name'],
        $body['option_name'],
        (float) ($body['extra_price'] ?? 0),
        (int) ($body['is_default'] ?? 0),
    ]);
    json_success(['id' => (int) db()->lastInsertId()], 201);
}

if ($method === 'PUT' && $opt_id) {
    validate_required($body, ['group_name', 'option_name']);
    validate_lengths($body, ['group_name' => 80, 'option_name' => 80]);
    $check = db()->prepare('SELECT id FROM product_options WHERE id=?');
    $check->execute([$opt_id]);
    if (!$check->fetch()) json_error('Option introuvable', 404);
    db()->prepare(
        'UPDATE product_options SET group_name=?, option_name=?, extra_price=?, is_default=? WHERE id=?'
    )->execute([
        $body['group_name'],
        $body['option_name'],
        (float) ($body['extra_price'] ?? 0),
        (int) ($body['is_default'] ?? 0),
        $opt_id,
    ]);
    json_success(['updated' => true]);
}

if ($method === 'DELETE' && $opt_id) {
    $stmt = db()->prepare('DELETE FROM product_options WHERE id = ?');
    $stmt->execute([$opt_id]);
    if ($stmt->rowCount() === 0) json_error('Option introuvable', 404);
    json_success(['deleted' => true]);
}

json_error('Route options invalide', 405);
