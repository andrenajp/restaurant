<?php
// POST /api/admin/users/{id}/reset-password

if ($method !== 'POST') json_error('Méthode non supportée', 405);

preg_match('#/users/(\d+)/reset-password$#', $admin_uri, $m);
$user_id = (int) ($m[1] ?? 0);

if (!$user_id) json_error('ID utilisateur requis', 422);

$check = db()->prepare('SELECT id, role, name, phone FROM users WHERE id=?');
$check->execute([$user_id]);
$user = $check->fetch();
if (!$user) json_error('Utilisateur introuvable', 404);

// Ne pas réinitialiser le dernier admin
if ($user['role'] === 'admin') {
    $admin_count = (int) db()->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
    if ($admin_count <= 1) {
        json_error('Impossible de réinitialiser le mot de passe du dernier administrateur', 422);
    }
}

// Générer mot de passe aléatoire (8 caractères alphanumériques)
$new_password = bin2hex(random_bytes(4)); // 8 caractères hex
$hash = password_hash($new_password, PASSWORD_BCRYPT);

db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash, $user_id]);

// Log l'action
$actor = auth_get_payload()['sub'] ?? null;
if ($actor) {
    $stmt = db()->prepare('INSERT INTO admin_logs (admin_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$actor, 'reset_password', 'user', $user_id, json_encode([
        'target_name' => $user['name'],
        'target_phone' => $user['phone']
    ])]);
}

json_success([
    'new_password' => $new_password,
    'user_name' => $user['name'],
    'user_phone' => $user['phone']
]);