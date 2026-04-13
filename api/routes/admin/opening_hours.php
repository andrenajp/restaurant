<?php
// GET /api/admin/opening-hours
// PUT /api/admin/opening-hours

if ($method === 'GET') {
    $stmt = db()->query('SELECT * FROM opening_hours ORDER BY day_of_week');
    $hours = $stmt->fetchAll();

    // S'assurer que les 7 jours sont présents
    $existing = array_column($hours, 'day_of_week');
    for ($i = 0; $i < 7; $i++) {
        if (!in_array($i, $existing)) {
            $hours[] = [
                'id' => null,
                'day_of_week' => $i,
                'open_time' => null,
                'close_time' => null,
                'is_closed' => 0
            ];
        }
    }

    usort($hours, fn($a, $b) => $a['day_of_week'] <=> $b['day_of_week']);
    json_success($hours);
}

if ($method === 'PUT') {
    validate_required($body, ['hours']);

    foreach ($body['hours'] as $hour) {
        $day = (int) $hour['day_of_week'];
        $open = $hour['open_time'] ?? null;
        $close = $hour['close_time'] ?? null;
        $is_closed = isset($hour['is_closed']) ? (int) $hour['is_closed'] : 0;

        $check = db()->prepare('SELECT id FROM opening_hours WHERE day_of_week = ?');
        $check->execute([$day]);
        $existing = $check->fetch();

        if ($existing) {
            $stmt = db()->prepare('UPDATE opening_hours SET open_time=?, close_time=?, is_closed=? WHERE day_of_week=?');
            $stmt->execute([$open, $close, $is_closed, $day]);
        } else {
            $stmt = db()->prepare('INSERT INTO opening_hours (day_of_week, open_time, close_time, is_closed) VALUES (?, ?, ?, ?)');
            $stmt->execute([$day, $open, $close, $is_closed]);
        }
    }

    // Log
    $actor = auth_get_payload()['sub'] ?? null;
    if ($actor) {
        $stmt = db()->prepare('INSERT INTO admin_logs (admin_id, action, details) VALUES (?, ?, ?)');
        $stmt->execute([$actor, 'update_opening_hours', json_encode(['hours' => $body['hours']])]);
    }

    json_success(['updated' => true]);
}

json_error('Méthode non supportée', 405);