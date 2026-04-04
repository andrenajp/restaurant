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

/**
 * Vérifie que les champs texte ne dépassent pas les longueurs max autorisées.
 * @param array $data   Corps de la requête
 * @param array $limits [ 'field' => max_chars, ... ]
 */
function validate_lengths(array $data, array $limits): void {
    foreach ($limits as $field => $max) {
        if (isset($data[$field]) && mb_strlen((string) $data[$field]) > $max) {
            json_error("Le champ '$field' ne doit pas dépasser $max caractères", 422);
        }
    }
}
