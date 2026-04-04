<?php
require_once dirname(__DIR__) . '/middleware/Auth.php';
require_once dirname(__DIR__) . '/helpers/RateLimit.php';

$client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

preg_match('#/(register|login|forgot|reset)$#', $uri, $m);
$action = $m[1] ?? '';

if ($action === 'register') {
    validate_required($body, ['phone', 'password']);
    validate_lengths($body, ['name' => 100, 'password' => 128]);
    $body['phone'] = normalize_phone($body['phone']);

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
        'user'  => [
            'id'              => $user_id,
            'name'            => $body['name'] ?? null,
            'phone'           => $body['phone'],
            'role'            => 'client',
            'default_address' => null,
        ],
    ], 201);
}

if ($action === 'login') {
    validate_required($body, ['phone', 'password']);
    $body['phone'] = normalize_phone($body['phone']);

    // 5 tentatives max sur 15 min → blocage 15 min
    rate_limit_check("login:{$client_ip}", 5, 900, 900);

    $stmt = db()->prepare('SELECT * FROM users WHERE phone = ?');
    $stmt->execute([$body['phone']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($body['password'], $user['password_hash'])) {
        error_log("[AUTH] Login échoué - IP:{$client_ip} phone:{$body['phone']}");
        json_error('Identifiants invalides', 401);
    }

    rate_limit_reset("login:{$client_ip}");
    error_log("[AUTH] Login réussi - IP:{$client_ip} user_id:{$user['id']} role:{$user['role']}");

    json_success([
        'token' => auth_make_token($user['id'], $user['role']),
        'user'  => [
            'id'              => $user['id'],
            'name'            => $user['name'],
            'phone'           => $user['phone'],
            'role'            => $user['role'],
            'default_address' => $user['default_address'],
        ],
    ]);
}

if ($action === 'forgot') {
    validate_required($body, ['phone']);
    $body['phone'] = normalize_phone($body['phone']);

    // 3 tentatives max sur 1h → blocage 1h
    rate_limit_check("forgot:{$client_ip}", 3, 3600, 3600);

    // Vérifier que le numéro existe
    $stmt = db()->prepare('SELECT id FROM users WHERE phone=?');
    $stmt->execute([$body['phone']]);
    if (!$stmt->fetch()) {
        // Réponse identique pour éviter l'énumération de comptes
        json_success(['sent' => true]);
    }

    // Générer un code à 6 chiffres, valable 15 minutes
    $code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + 900);

    // Supprimer les anciens codes non utilisés pour ce numéro
    db()->prepare('DELETE FROM password_resets WHERE phone=? AND used=0')
        ->execute([$body['phone']]);

    db()->prepare('INSERT INTO password_resets (phone, code, expires_at) VALUES (?,?,?)')
        ->execute([$body['phone'], $code, $expires]);

    require_once __DIR__ . '/../helpers/Sms.php';
    send_sms($body['phone'], "Votre code P.R.T : $code (valable 15 minutes)");

    json_success(['sent' => true]);
}

if ($action === 'reset') {
    validate_required($body, ['phone', 'code', 'password']);
    validate_lengths($body, ['password' => 128]);
    $body['phone'] = normalize_phone($body['phone']);

    // 5 tentatives max sur 15 min → blocage 15 min
    rate_limit_check("reset:{$client_ip}", 5, 900, 900);

    if (strlen($body['password']) < 6) {
        json_error('Le mot de passe doit faire au moins 6 caractères', 422);
    }

    $stmt = db()->prepare(
        'SELECT id FROM password_resets
         WHERE phone=? AND code=? AND used=0 AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$body['phone'], $body['code']]);
    $reset = $stmt->fetch();

    if (!$reset) {
        error_log("[AUTH] Reset mot de passe échoué - IP:{$client_ip} phone:{$body['phone']}");
        json_error('Code invalide ou expiré', 422);
    }

    // Marquer le code comme utilisé
    db()->prepare('UPDATE password_resets SET used=1 WHERE id=?')
        ->execute([$reset['id']]);

    // Mettre à jour le mot de passe
    $hash = password_hash($body['password'], PASSWORD_BCRYPT);
    db()->prepare('UPDATE users SET password_hash=? WHERE phone=?')
        ->execute([$hash, $body['phone']]);

    rate_limit_reset("reset:{$client_ip}");
    error_log("[AUTH] Mot de passe réinitialisé - IP:{$client_ip} phone:{$body['phone']}");
    json_success(['reset' => true]);
}

json_error('Action auth invalide', 400);
