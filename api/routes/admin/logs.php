<?php
// GET /api/admin/logs?limit=100

if ($method !== 'GET') json_error('Méthode non supportée', 405);

$limit = isset($_GET['limit']) ? min(500, (int) $_GET['limit']) : 100;

$stmt = db()->prepare("
    SELECT l.*, u.name as admin_name, u.phone as admin_phone
    FROM admin_logs l
    LEFT JOIN users u ON u.id = l.admin_id
    ORDER BY l.created_at DESC
    LIMIT ?
");
$stmt->execute([$limit]);
$logs = $stmt->fetchAll();

// Décoder les détails JSON
foreach ($logs as &$log) {
    $log['details'] = json_decode($log['details'], true);
}

json_success($logs);