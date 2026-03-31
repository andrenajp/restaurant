<?php
function validate_required(array $data, array $fields): void {
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            json_error("Champ requis : $field", 422);
        }
    }
}

function validate_phone(string $phone): bool {
    return (bool) preg_match('/^\+?[0-9]{8,15}$/', preg_replace('/\s/', '', $phone));
}
