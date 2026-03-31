<?php
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase {
    public function test_db_connects(): void {
        $pdo = db();
        $this->assertInstanceOf(PDO::class, $pdo);
    }

    public function test_db_has_settings(): void {
        $stmt = db()->query('SELECT COUNT(*) as n FROM settings');
        $row = $stmt->fetch();
        $this->assertGreaterThan(0, (int) $row['n']);
    }

    public function test_validate_phone_valid(): void {
        $this->assertTrue(validate_phone('+33612345678'));
        $this->assertTrue(validate_phone('0612345678'));
    }

    public function test_validate_phone_invalid(): void {
        $this->assertFalse(validate_phone('abc'));
        $this->assertFalse(validate_phone('123'));
    }
}
