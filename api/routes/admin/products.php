<?php
preg_match('#/products(?:/(\d+))?$#', $uri, $m);
$product_id = isset($m[1]) ? (int) $m[1] : null;

if ($method === 'GET' && !$product_id) {
    $stmt = db()->query('SELECT * FROM products ORDER BY category_id, sort_order');
    json_success($stmt->fetchAll());
}

if ($method === 'POST') {
    validate_required($body, ['category_id', 'name', 'price']);
    $stmt = db()->prepare(
        'INSERT INTO products (category_id, name, description, price, image_url, is_available, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        (int) $body['category_id'],
        $body['name'],
        $body['description'] ?? null,
        (float) $body['price'],
        $body['image_url'] ?? null,
        isset($body['is_available']) ? (int) $body['is_available'] : 1,
        (int) ($body['sort_order'] ?? 0),
    ]);
    json_success(['id' => (int) db()->lastInsertId()], 201);
}

if ($method === 'PUT' && $product_id) {
    validate_required($body, ['name', 'price']);
    $stmt = db()->prepare(
        'UPDATE products SET category_id=?, name=?, description=?, price=?, image_url=?, is_available=?, sort_order=? WHERE id=?'
    );
    $stmt->execute([
        (int) $body['category_id'],
        $body['name'],
        $body['description'] ?? null,
        (float) $body['price'],
        $body['image_url'] ?? null,
        (int) ($body['is_available'] ?? 1),
        (int) ($body['sort_order'] ?? 0),
        $product_id,
    ]);
    json_success(['updated' => true]);
}

if ($method === 'DELETE' && $product_id) {
    db()->prepare('DELETE FROM products WHERE id = ?')->execute([$product_id]);
    json_success(['deleted' => true]);
}

json_error('Route produit invalide', 405);
