<?php
function validate_required(array $data, array $fields): void {
    foreach ($fields as $field) {
        $val = $data[$field] ?? null;
        $empty = is_array($val) ? empty($val) : ($val === null || trim((string)$val) === '');
        if ($empty) {
            json_error("Champ requis : $field", 422);
        }
    }
}

function normalize_phone(string $phone): string {
    // Supprimer les espaces, tirets, points
    return preg_replace('/[\s\-\.]/', '', $phone);
}

function validate_phone(string $phone): bool {
    return (bool) preg_match('/^\+?[0-9]{8,15}$/', $phone);
}
