<?php
function env(string $key, string $default = ''): string {
    static $loaded = false;
    if (!$loaded) {
        $file = dirname(__DIR__, 2) . '/.env';
        if (file_exists($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with(trim($line), '#')) continue;
                [$k, $v] = array_map('trim', explode('=', $line, 2));
                $_ENV[$k] = $v;
            }
        }
        $loaded = true;
    }
    return $_ENV[$key] ?? $default;
}
