<?php
// Загрузка переменных из .env в $_ENV (без внешних зависимостей)
// Файл .env должен находиться в каталоге admin/

if (!isset($_ENV['_ENV_LOADED'])) {
    $env_file = __DIR__ . DIRECTORY_SEPARATOR . '.env';
    if (is_file($env_file) && is_readable($env_file)) {
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                if (preg_match('/^["\'](.*)["\']$/s', $value, $m)) {
                    $value = $m[1];
                }
                $_ENV[$name] = $value;
                putenv("$name=$value");
            }
        }
    }
    $_ENV['_ENV_LOADED'] = '1';
}
