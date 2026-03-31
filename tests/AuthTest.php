<?php
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase {
    public function test_make_token_returns_string(): void {
        require_once __DIR__ . '/../api/middleware/Auth.php';
        $token = auth_make_token(1, 'admin');
        $this->assertIsString($token);
        $this->assertStringContainsString('.', $token);
    }

    public function test_get_payload_without_header_returns_null(): void {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        require_once __DIR__ . '/../api/middleware/Auth.php';
        $this->assertNull(auth_get_payload());
    }
}
