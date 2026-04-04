<?php
// PATCH /api/auth/profile — mettre à jour le profil de l'utilisateur connecté
require_once __DIR__ . '/../middleware/Auth.php';
auth_require_role(['client', 'kitchen', 'delivery', 'admin']);

$payload = auth_get_payload();
$user_id = (int) $payload['sub'];

if ($method !== 'PATCH') json_error('Méthode non supportée', 405);

$updates = [];
$params  = [];

if (isset($body['name'])) {
    $updates[] = 'name = ?';
    $params[]  = trim($body['name']) ?: null;
}

if (isset($body['phone'])) {
    $new_phone = normalize_phone(trim($body['phone']));
    if (!validate_phone($new_phone)) json_error('Numéro de téléphone invalide', 422);

    // Vérifier que le numéro n'est pas déjà pris par quelqu'un d'autre
    $check = db()->prepare('SELECT id FROM users WHERE phone=? AND id != ?');
    $check->execute([$new_phone, $user_id]);
    if ($check->fetch()) json_error('Ce numéro est déjà utilisé par un autre compte', 409);

    $updates[] = 'phone = ?';
    $params[]  = $new_phone;
}

if (empty($updates)) json_error('Aucune donnée à mettre à jour', 422);

$params[] = $user_id;
db()->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?')
    ->execute($params);

// Retourner le profil à jour
$stmt = db()->prepare('SELECT id, name, phone, role, default_address FROM users WHERE id=?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

json_success([
    'id'              => (int) $user['id'],
    'name'            => $user['name'],
    'phone'           => $user['phone'],
    'role'            => $user['role'],
    'default_address' => $user['default_address'],
]);
