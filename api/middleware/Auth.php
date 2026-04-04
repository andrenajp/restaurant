<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/helpers/Response.php';

function jwt_secret(): string {
    $secret = env('JWT_SECRET', '');
    if (!$secret && env('APP_ENV', 'development') === 'production') {
        http_response_code(500);
        exit(json_encode(['error' => 'JWT_SECRET non configuré']));
    }
    return $secret ?: 'secret';
}

function auth_get_payload(): ?array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!str_starts_with($header, 'Bearer ')) return null;
    $token = substr($header, 7);
    try {
        $decoded = JWT::decode($token, new Key(jwt_secret(), 'HS256'));
        return (array) $decoded;
    } catch (\Exception $e) {
        return null;
    }
}

function auth_require_role(array $roles): array {
    $payload = auth_get_payload();
    if (!$payload) json_error('Non authentifié', 401);
    if (!in_array($payload['role'], $roles)) json_error('Accès interdit', 403);
    return $payload;
}

function auth_make_token(int $user_id, string $role): string {
    $payload = [
        'sub'  => $user_id,
        'role' => $role,
        'iat'  => time(),
        'exp'  => time() + (int) env('JWT_EXPIRY', '7776000'), // 90 jours par défaut
    ];
    return JWT::encode($payload, jwt_secret(), 'HS256');
}
