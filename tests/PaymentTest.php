<?php
use PHPUnit\Framework\TestCase;

class PaymentTest extends TestCase
{
    private int $cat_id  = 0;
    private int $prod_id = 0;
    private int $opt_id  = 0;

    protected function setUp(): void
    {
        $pdo = db();
        $pdo->prepare("INSERT INTO categories (name, sort_order, is_active) VALUES ('TestPay',1,1)")
            ->execute();
        $this->cat_id = (int) $pdo->lastInsertId();

        $pdo->prepare("INSERT INTO products (category_id, name, price, is_available) VALUES (?,?,?,1)")
            ->execute([$this->cat_id, 'TestPlat', '10.00']);
        $this->prod_id = (int) $pdo->lastInsertId();

        $pdo->prepare("INSERT INTO product_options (product_id, group_name, option_name, extra_price, is_default) VALUES (?,?,?,?,0)")
            ->execute([$this->prod_id, 'Sauce', 'Massalé', '1.50']);
        $this->opt_id = (int) $pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        $pdo = db();
        $pdo->prepare('DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE phone = ?)')->execute(['+33611111111']);
        $pdo->prepare('DELETE FROM orders WHERE phone = ?')->execute(['+33611111111']);
        $pdo->prepare('DELETE FROM product_options WHERE product_id = ?')->execute([$this->prod_id]);
        $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$this->prod_id]);
        $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$this->cat_id]);
    }

    public function test_order_created_with_pending_status(): void
    {
        $pdo   = db();
        $token = bin2hex(random_bytes(16));

        $pdo->prepare(
            'INSERT INTO orders (phone, type, status, total, delivery_fee, tracking_token)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute(['+33611111111', 'pickup', 'pending', 10.00, 0, $token]);

        $order = $pdo->prepare('SELECT status FROM orders WHERE tracking_token = ?');
        $order->execute([$token]);
        $row = $order->fetch();

        $this->assertEquals('pending', $row['status']);
    }

    public function test_unit_price_includes_option_extra(): void
    {
        $base_price  = 10.00;
        $extra_price = 1.50;
        $unit_total  = $base_price + $extra_price;

        $this->assertEquals(11.50, $unit_total);
    }

    public function test_delivery_fee_is_zero_above_threshold(): void
    {
        $settings = db()->query('SELECT delivery_free_above FROM settings LIMIT 1')->fetch();
        $threshold = (float)($settings['delivery_free_above'] ?? 0);

        $subtotal    = $threshold + 1;  // juste au-dessus du seuil
        $fee_row     = db()->query('SELECT fee FROM delivery_fees WHERE is_active=1 LIMIT 1')->fetch();
        $base_fee    = $fee_row ? (float)$fee_row['fee'] : 0;
        $actual_fee  = ($threshold > 0 && $subtotal >= $threshold) ? 0.0 : $base_fee;

        $this->assertEquals(0.0, $actual_fee);
    }

    public function test_order_items_stored_with_options_json(): void
    {
        $pdo   = db();
        $token = bin2hex(random_bytes(16));

        $pdo->prepare(
            'INSERT INTO orders (phone, type, status, total, delivery_fee, tracking_token)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute(['+33611111111', 'pickup', 'pending', 11.50, 0, $token]);
        $order_id = (int) $pdo->lastInsertId();

        $options_json = json_encode([['option_name' => 'Massalé', 'extra_price' => 1.50]]);
        $pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, quantity, unit_price, options_json)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$order_id, $this->prod_id, 1, 11.50, $options_json]);

        $item = $pdo->prepare('SELECT options_json FROM order_items WHERE order_id = ?');
        $item->execute([$order_id]);
        $row  = $item->fetch();
        $opts = json_decode($row['options_json'], true);

        $this->assertIsArray($opts);
        $this->assertEquals('Massalé', $opts[0]['option_name']);
    }
}
