<?php
if ($method === 'GET') {
    json_success(db()->query('SELECT * FROM settings LIMIT 1')->fetch());
}

if ($method === 'PUT') {
    $allowed = ['restaurant_name','logo_url','color_primary','color_accent',
                'color_band_1','color_band_2','color_band_3','color_band_4',
                'delivery_free_above','twilio_phone','stripe_pk_public','promo_banner'];
    $sets = [];
    $vals = [];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $body)) {
            $sets[] = "$field = ?";
            $vals[] = $body[$field];
        }
    }
    if (!$sets) json_error('Aucun champ à mettre à jour', 422);
    db()->prepare('UPDATE settings SET ' . implode(', ', $sets) . ' WHERE id = 1')
        ->execute($vals);
    json_success(['updated' => true]);
}

json_error('Route settings invalide', 405);
