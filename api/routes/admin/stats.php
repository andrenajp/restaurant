<?php
// GET /api/admin/stats — CA et commandes par période + top produits

if ($method !== 'GET') json_error('Méthode non supportée', 405);

// CA et nombre de commandes : aujourd'hui, 7 jours, 30 jours
$periods = [
    'today'   => 'DATE(created_at) = CURDATE()',
    'week'    => 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
    'month'   => 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
];

$revenue = [];
foreach ($periods as $key => $condition) {
    $row = db()->query(
        "SELECT COUNT(*) as orders, COALESCE(SUM(total),0) as revenue
         FROM orders WHERE status='delivered' AND $condition"
    )->fetch();
    $revenue[$key] = [
        'orders'  => (int)$row['orders'],
        'revenue' => round((float)$row['revenue'], 2),
    ];
}

// CA par jour sur les 14 derniers jours (pour graphique)
$daily = db()->query(
    "SELECT DATE(created_at) as day, COUNT(*) as orders, COALESCE(SUM(total),0) as revenue
     FROM orders WHERE status='delivered' AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
     GROUP BY DATE(created_at) ORDER BY day ASC"
)->fetchAll();

// Top 5 produits (par quantité vendue)
$top_products = db()->query(
    "SELECT p.name, SUM(oi.quantity) as qty_sold, SUM(oi.quantity * oi.unit_price) as revenue
     FROM order_items oi
     JOIN products p ON p.id = oi.product_id
     JOIN orders o ON o.id = oi.order_id
     WHERE o.status = 'delivered'
     GROUP BY oi.product_id, p.name
     ORDER BY qty_sold DESC
     LIMIT 5"
)->fetchAll();

// Répartition pickup vs livraison (30 jours)
$split = db()->query(
    "SELECT type, COUNT(*) as count FROM orders
     WHERE status='delivered' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY type"
)->fetchAll();

json_success([
    'revenue'      => $revenue,
    'daily'        => $daily,
    'top_products' => $top_products,
    'type_split'   => $split,
]);
