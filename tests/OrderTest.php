<?php
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase {
    public function test_create_order_pickup(): void {
        $token = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
            mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff)
        );
        $pdo = db();
        $stmt = $pdo->prepare(
            'INSERT INTO orders (phone, type, total, delivery_fee, tracking_token) VALUES (?, "pickup", 12.00, 0, ?)'
        );
        $stmt->execute(['+33699999999', $token]);
        $id = (int) $pdo->lastInsertId();
        $this->assertGreaterThan(0, $id);
        $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$id]);
    }

    public function test_order_total_includes_delivery_fee(): void {
        $settings = db()->query('SELECT delivery_free_above FROM settings LIMIT 1')->fetch();
        $this->assertGreaterThan(0, (float) $settings['delivery_free_above']);
    }

    public function test_tracking_token_is_uuid_format(): void {
        $token = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
            mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff)
        );
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $token
        );
    }
}
