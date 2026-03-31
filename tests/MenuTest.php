<?php
use PHPUnit\Framework\TestCase;

class MenuTest extends TestCase {
    public function test_settings_has_required_keys(): void {
        $stmt = db()->query('SELECT * FROM settings LIMIT 1');
        $s = $stmt->fetch();
        $this->assertArrayHasKey('color_primary', $s);
        $this->assertArrayHasKey('color_band_1', $s);
        $this->assertArrayHasKey('restaurant_name', $s);
    }

    public function test_categories_returns_active_only(): void {
        $stmt = db()->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order');
        $cats = $stmt->fetchAll();
        $this->assertGreaterThan(0, count($cats));
        foreach ($cats as $c) {
            $this->assertEquals(1, (int) $c['is_active']);
        }
    }

    public function test_products_have_options(): void {
        $stmt = db()->query('SELECT p.id FROM products p JOIN product_options po ON po.product_id = p.id LIMIT 1');
        $row = $stmt->fetch();
        $this->assertNotEmpty($row);
    }
}
