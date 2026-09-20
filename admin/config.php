<?php
// Конфигурационный файл для подключения к базе данных
// ⚠️ ВАЖНО: Этот файл должен быть недоступен через веб-сервер!
// Параметры берутся из переменных окружения (файл admin/.env).

require_once __DIR__ . '/load_env.php';

$env = function ($key, $default = '') {
    return $_ENV[$key] ?? getenv($key) ?: $default;
};

define('DB_HOST', $env('DB_HOST'));
define('DB_PORT', $env('DB_PORT', '3306'));
define('DB_DATABASE', $env('DB_DATABASE'));
define('DB_USERNAME', $env('DB_USERNAME'));
define('DB_PASSWORD', $env('DB_PASSWORD'));

// Единые пути к каталогам проекта (корень — родитель admin/)
define('ROOT_DIR', dirname(__DIR__));
define('DATA_DIR', ROOT_DIR . '/data');
define('IMAGES_DIR', ROOT_DIR . '/images');
define('LANGS_DIR', ROOT_DIR . '/langs');
define('INDEX_HTML_PATH', ROOT_DIR . '/index.html');

// Функция для создания подключения к БД
function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_DATABASE . ";charset=utf8mb4",
            DB_USERNAME,
            DB_PASSWORD,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Ошибка подключения к БД: " . $e->getMessage());
        return false;
    }
}

// Функция для проверки подключения к БД
function checkDBConnection() {
    $pdo = getDBConnection();
    if ($pdo) {
        try {
            $version = $pdo->query('SELECT VERSION() as version')->fetch();
            return [
                'status' => true,
                'version' => $version['version'],
                'message' => 'Подключение успешно'
            ];
        } catch (PDOException $e) {
            return [
                'status' => false,
                'version' => 'Неизвестно',
                'message' => $e->getMessage()
            ];
        }
    }
    return [
        'status' => false,
        'version' => 'Неизвестно',
        'message' => 'Не удалось создать подключение'
    ];
} 