<?php
preg_match('#/categories(?:/(\d+))?$#', $uri, $m);
$cat_id = isset($m[1]) ? (int) $m[1] : null;

if ($method === 'GET') {
    json_success(db()->query('SELECT * FROM categories ORDER BY sort_order')->fetchAll());
}

if ($method === 'POST') {
    validate_required($body, ['name']);
    $stmt = db()->prepare(
        'INSERT INTO categories (name, emoji, color, sort_order, is_active) VALUES (?, ?, ?, ?, 1)'
    );
    $stmt->execute([$body['name'], $body['emoji'] ?? null, $body['color'] ?? '#CC0000', (int)($body['sort_order'] ?? 0)]);
    json_success(['id' => (int) db()->lastInsertId()], 201);
}

if ($method === 'PUT' && $cat_id) {
    validate_required($body, ['name']);
    $stmt = db()->prepare('UPDATE categories SET name=?, emoji=?, color=?, sort_order=?, is_active=? WHERE id=?');
    $stmt->execute([$body['name'], $body['emoji'] ?? null, $body['color'] ?? '#CC0000', (int)($body['sort_order'] ?? 0), (int)($body['is_active'] ?? 1), $cat_id]);
    json_success(['updated' => true]);
}

if ($method === 'DELETE' && $cat_id) {
    db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$cat_id]);
    json_success(['deleted' => true]);
}

json_error('Route catégorie invalide', 405);
