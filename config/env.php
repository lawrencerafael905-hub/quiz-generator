<?php
// config/env.php — Load .env variables into $_ENV / getenv()
function loadEnv(string $path): void {
    if (!file_exists($path)) {
        throw new RuntimeException('.env file not found at: ' . $path);
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        // Strip surrounding quotes
        if (preg_match('/^["\'](.*)["\']$/', $value, $m)) {
            $value = $m[1];
        }
        if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

loadEnv(dirname(__DIR__) . '/.env');
