<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = preg_replace('#^/api#', '', $uri);

if ($uri === '/categories') {
    $stmt = db()->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order');
    json_success($stmt->fetchAll());
}

if ($uri === '/products') {
    $category_id = isset($_GET['category_id']) ? (int) $_GET['category_id'] : null;

    if ($category_id) {
        $stmt = db()->prepare(
            'SELECT p.*, GROUP_CONCAT(
                JSON_OBJECT(
                    "id", po.id,
                    "group_name", po.group_name,
                    "option_name", po.option_name,
                    "extra_price", po.extra_price,
                    "is_default", po.is_default
                ) ORDER BY po.group_name, po.id
            ) as options_raw
            FROM products p
            LEFT JOIN product_options po ON po.product_id = p.id
            WHERE p.category_id = ? AND p.is_available = 1
            GROUP BY p.id
            ORDER BY p.sort_order'
        );
        $stmt->execute([$category_id]);
    } else {
        $stmt = db()->query(
            'SELECT p.*, GROUP_CONCAT(
                JSON_OBJECT(
                    "id", po.id,
                    "group_name", po.group_name,
                    "option_name", po.option_name,
                    "extra_price", po.extra_price,
                    "is_default", po.is_default
                ) ORDER BY po.group_name, po.id
            ) as options_raw
            FROM products p
            LEFT JOIN product_options po ON po.product_id = p.id
            WHERE p.is_available = 1
            GROUP BY p.id
            ORDER BY p.category_id, p.sort_order'
        );
    }

    $products = $stmt->fetchAll();
    foreach ($products as &$p) {
        $p['options'] = $p['options_raw']
            ? json_decode('[' . $p['options_raw'] . ']', true)
            : [];
        unset($p['options_raw']);
    }
    json_success($products);
}

json_error('Route menu non trouvée', 404);
