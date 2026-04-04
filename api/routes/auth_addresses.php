<?php
// /api/auth/addresses — gestion des adresses sauvegardées
require_once __DIR__ . '/../middleware/Auth.php';
auth_require_role(['client', 'kitchen', 'delivery', 'admin']);

$payload = auth_get_payload();
$user_id = (int) $payload['sub'];

preg_match('#/auth/addresses(?:/(\d+))?(?:/(default))?$#', $uri, $m);
$addr_id = isset($m[1]) ? (int)$m[1] : null;
$sub     = $m[2] ?? null;

// GET /api/auth/addresses
if ($method === 'GET' && !$addr_id) {
    $stmt = db()->prepare(
        'SELECT id, label, address, is_default, created_at
         FROM user_addresses WHERE user_id=? ORDER BY is_default DESC, id DESC'
    );
    $stmt->execute([$user_id]);
    json_success($stmt->fetchAll());
}

// POST /api/auth/addresses
if ($method === 'POST') {
    validate_required($body, ['label', 'address']);
    validate_lengths($body, ['label' => 50, 'address' => 300]);
    $label   = trim($body['label']);
    $address = trim($body['address']);
    if (!$label || !$address) json_error('Label et adresse requis', 422);

    $is_default = !empty($body['is_default']) ? 1 : 0;

    // Si on marque comme défaut, retirer l'ancien défaut
    if ($is_default) {
        db()->prepare('UPDATE user_addresses SET is_default=0 WHERE user_id=?')
            ->execute([$user_id]);
    }

    $stmt = db()->prepare(
        'INSERT INTO user_addresses (user_id, label, address, is_default) VALUES (?,?,?,?)'
    );
    $stmt->execute([$user_id, $label, $address, $is_default]);
    $id = (int) db()->lastInsertId();

    json_success(['id' => $id, 'label' => $label, 'address' => $address, 'is_default' => $is_default], 201);
}

// DELETE /api/auth/addresses/{id}
if ($method === 'DELETE' && $addr_id) {
    $stmt = db()->prepare('DELETE FROM user_addresses WHERE id=? AND user_id=?');
    $stmt->execute([$addr_id, $user_id]);
    if ($stmt->rowCount() === 0) json_error('Adresse introuvable', 404);
    json_success(['deleted' => true]);
}

// PATCH /api/auth/addresses/{id}/default
if ($method === 'PATCH' && $addr_id && $sub === 'default') {
    $check = db()->prepare('SELECT id FROM user_addresses WHERE id=? AND user_id=?');
    $check->execute([$addr_id, $user_id]);
    if (!$check->fetch()) json_error('Adresse introuvable', 404);

    db()->prepare('UPDATE user_addresses SET is_default=0 WHERE user_id=?')->execute([$user_id]);
    db()->prepare('UPDATE user_addresses SET is_default=1 WHERE id=?')->execute([$addr_id]);
    json_success(['updated' => true]);
}

json_error('Route adresses invalide', 405);
