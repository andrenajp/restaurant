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

    public function test_register_creates_user(): void {
        $phone = '+336' . rand(10000000, 99999999);
        $stmt = db()->prepare(
            'INSERT INTO users (phone, password_hash, role) VALUES (?, ?, "client")'
        );
        $stmt->execute([$phone, password_hash('test1234', PASSWORD_BCRYPT)]);
        $id = (int) db()->lastInsertId();
        $this->assertGreaterThan(0, $id);
        db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }

    public function test_password_verify_works(): void {
        $hash = password_hash('secret123', PASSWORD_BCRYPT);
        $this->assertTrue(password_verify('secret123', $hash));
        $this->assertFalse(password_verify('wrong', $hash));
    }
}
