<?php
preg_match('#/users(?:/(\d+))?$#', $admin_uri, $m);
$user_id = isset($m[1]) ? (int)$m[1] : null;

// GET /api/admin/users — liste tous les utilisateurs
if ($method === 'GET' && !$user_id) {
    $stmt = db()->query(
        'SELECT id, name, phone, role, created_at FROM users ORDER BY created_at DESC LIMIT 200'
    );
    json_success($stmt->fetchAll());
}

// POST /api/admin/users — créer un utilisateur
if ($method === 'POST') {
    validate_required($body, ['phone', 'password']);

    if (!validate_phone($body['phone'])) json_error('Numéro de téléphone invalide', 422);
    if (strlen($body['password']) < 6)   json_error('Mot de passe trop court (min 6 caractères)', 422);

    $allowed_roles = ['client', 'kitchen', 'delivery', 'admin'];
    $role = $body['role'] ?? 'client';
    if (!in_array($role, $allowed_roles)) json_error('Rôle invalide', 422);

    $check = db()->prepare('SELECT id FROM users WHERE phone=?');
    $check->execute([$body['phone']]);
    if ($check->fetch()) json_error('Ce numéro est déjà utilisé', 409);

    $hash = password_hash($body['password'], PASSWORD_BCRYPT);
    $stmt = db()->prepare(
        'INSERT INTO users (name, phone, password_hash, role) VALUES (?,?,?,?)'
    );
    $stmt->execute([$body['name'] ?? null, $body['phone'], $hash, $role]);
    $id = (int) db()->lastInsertId();

    json_success([
        'id'         => $id,
        'name'       => $body['name'] ?? null,
        'phone'      => $body['phone'],
        'role'       => $role,
        'created_at' => date('Y-m-d H:i:s'),
    ], 201);
}

// PATCH /api/admin/users/{id} — modifier le rôle
if ($method === 'PATCH' && $user_id) {
    $allowed_roles = ['client', 'kitchen', 'delivery', 'admin'];
    if (!isset($body['role']) || !in_array($body['role'], $allowed_roles)) {
        json_error('Rôle invalide', 422);
    }
    $stmt = db()->prepare('UPDATE users SET role=? WHERE id=?');
    $stmt->execute([$body['role'], $user_id]);
    if ($stmt->rowCount() === 0) json_error('Utilisateur introuvable', 404);
    json_success(['updated' => true]);
}

// DELETE /api/admin/users/{id} — supprimer un utilisateur
if ($method === 'DELETE' && $user_id) {
    // Ne pas supprimer le dernier admin
    $admin_count = (int)db()->query(
        "SELECT COUNT(*) FROM users WHERE role='admin'"
    )->fetchColumn();
    $target_role = db()->prepare('SELECT role FROM users WHERE id=?');
    $target_role->execute([$user_id]);
    $row = $target_role->fetch();
    if (!$row) json_error('Utilisateur introuvable', 404);
    if ($row['role'] === 'admin' && $admin_count <= 1) {
        json_error('Impossible de supprimer le dernier administrateur', 422);
    }
    db()->prepare('DELETE FROM users WHERE id=?')->execute([$user_id]);
    json_success(['deleted' => true]);
}

json_error('Route users invalide', 405);
