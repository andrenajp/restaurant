<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/helpers/Response.php';

function auth_get_payload(): ?array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!str_starts_with($header, 'Bearer ')) return null;
    $token = substr($header, 7);
    try {
        $decoded = JWT::decode($token, new Key(env('JWT_SECRET', 'secret'), 'HS256'));
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
        'exp'  => time() + (int) env('JWT_EXPIRY', '86400'),
    ];
    return JWT::encode($payload, env('JWT_SECRET', 'secret'), 'HS256');
}
