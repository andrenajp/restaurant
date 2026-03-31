<?php
require_once dirname(__DIR__) . '/middleware/Auth.php';

preg_match('#/(register|login)$#', $uri, $m);
$action = $m[1] ?? '';

if ($action === 'register') {
    validate_required($body, ['phone', 'password']);

    if (!validate_phone($body['phone'])) {
        json_error('Numéro de téléphone invalide', 422);
    }

    $stmt = db()->prepare('SELECT id FROM users WHERE phone = ?');
    $stmt->execute([$body['phone']]);
    if ($stmt->fetch()) json_error('Ce numéro est déjà utilisé', 409);

    $hash = password_hash($body['password'], PASSWORD_BCRYPT);
    $stmt = db()->prepare(
        'INSERT INTO users (name, phone, password_hash, role) VALUES (?, ?, ?, "client")'
    );
    $stmt->execute([$body['name'] ?? null, $body['phone'], $hash]);
    $user_id = (int) db()->lastInsertId();

    json_success([
        'token' => auth_make_token($user_id, 'client'),
        'user'  => ['id' => $user_id, 'phone' => $body['phone'], 'role' => 'client'],
    ], 201);
}

if ($action === 'login') {
    validate_required($body, ['phone', 'password']);

    $stmt = db()->prepare('SELECT * FROM users WHERE phone = ?');
    $stmt->execute([$body['phone']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($body['password'], $user['password_hash'])) {
        json_error('Identifiants invalides', 401);
    }

    json_success([
        'token' => auth_make_token($user['id'], $user['role']),
        'user'  => ['id' => $user['id'], 'phone' => $user['phone'], 'role' => $user['role']],
    ]);
}

json_error('Action auth invalide', 400);
