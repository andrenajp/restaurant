<?php
$stmt = db()->query('SELECT * FROM settings LIMIT 1');
$settings = $stmt->fetch();
unset($settings['id']);
json_success($settings);
