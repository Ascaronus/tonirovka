<?php
/**
 * Настройка автоматического SEO мониторинга
 * Создает таблицу мониторинга и настраивает автоматические проверки
 */

session_start();

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config.php';

if (!defined('LOGGING_FUNCTIONS_LOADED')) {
    include __DIR__ . '/logging_functions.php';
}

$success = '';
$error = '';

// Обработка создания таблицы мониторинга
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_table') {
    try {
        $pdo = getDBConnection();
        if ($pdo) {
            // Читаем SQL файл
            $sql = file_get_contents('create_seo_monitoring_table.sql');
            
            // Выполняем SQL
            $pdo->exec($sql);
            
            $success = 'Таблица SEO мониторинга создана успешно!';
            writeLog('seo_setup', 'Создана таблица SEO мониторинга', 'success');
        } else {
            $error = 'Ошибка подключения к базе данных!';
            writeLog('seo_setup', 'Ошибка подключения к БД при создании таблицы', 'error');
        }
    } catch (Exception $e) {
        $error = 'Ошибка создания таблицы: ' . $e->getMessage();
        writeLog('seo_setup', 'Ошибка создания таблицы: ' . $e->getMessage(), 'error');
    }
}

// Обработка тестового запуска мониторинга
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_monitoring') {
    try {
        $pdo = getDBConnection();
        if ($pdo) {
            include 'seo-monitor.php';
            $monitor = new SEOMonitor($pdo);
            $results = $monitor->runFullCheck();
            
            // Сохраняем результаты
            $monitor->saveMonitoringResults($results);
            
            $success = 'Тестовый мониторинг выполнен успешно! Статус: ' . $results['overall_status'];
            writeLog('seo_setup', 'Выполнен тестовый SEO мониторинг', 'success');
        } else {
            $error = 'Ошибка подключения к базе данных!';
        }
    } catch (Exception $e) {
        $error = 'Ошибка тестового мониторинга: ' . $e->getMessage();
        writeLog('seo_setup', 'Ошибка тестового мониторинга: ' . $e->getMessage(), 'error');
    }
}

// Проверка существования таблицы
$table_exists = false;
$pdo = getDBConnection();
if ($pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'seo_monitoring'");
        $table_exists = $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        // Таблица не существует
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройка SEO Мониторинга - Админ-панель</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
        .header { background: #333; color: white; padding: 20px; }
        .header h1 { margin: 0; }
        .back { float: right; color: white; text-decoration: none; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .nav { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
        .nav a { display: inline-block; padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; flex: 0 0 auto; white-space: nowrap; }
        .nav a:hover { background: #0056b3; }
        .content { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .section { background: #f8f9fa; padding: 25px; border-radius: 10px; margin-bottom: 25px; border: 1px solid #e9ecef; }
        .section h3 { margin-top: 0; margin-bottom: 20px; color: #333; border-bottom: 2px solid #007bff; padding-bottom: 15px; }
        button { background: #28a745; color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin: 5px; }
        button:hover { background: #218838; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-info:hover { background: #138496; }
        .status-ok { color: #28a745; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
        .code-block { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 5px; padding: 15px; font-family: monospace; margin: 10px 0; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        @media (max-width: 768px) {
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔧 Настройка SEO Мониторинга</h1>
        <a href="seo-auto.php" class="back">← Назад к SEO</a>
    </div>
    
    <div class="container">
        <div class="nav">
            <a href="index.php">📊 Главная</a>
            <a href="prices.php">💰 Цены</a>
            <a href="gallery.php">🖼️ Галерея</a>
            <a href="films.php">🎨 Пленки</a>
            <a href="content.php">📝 Контент</a>
            <a href="seo-auto.php">🚀 SEO Автоматика</a>
            <a href="settings.php">⚙️ Настройки</a>
            <a href="logs.php">📋 Логи</a>
        </div>
        
        <div class="content">
            <?php if ($success): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <h2>🔧 Настройка автоматического SEO мониторинга</h2>
            
            <!-- Статус системы -->
            <div class="section">
                <h3>📊 Статус системы</h3>
                <div class="grid">
                    <div>
                        <p><strong>Таблица мониторинга:</strong> 
                            <span class="<?php echo $table_exists ? 'status-ok' : 'status-error'; ?>">
                                <?php echo $table_exists ? '✅ Создана' : '❌ Не создана'; ?>
                            </span>
                        </p>
                        <p><strong>База данных:</strong> 
                            <span class="<?php echo $pdo ? 'status-ok' : 'status-error'; ?>">
                                <?php echo $pdo ? '✅ Подключена' : '❌ Не подключена'; ?>
                            </span>
                        </p>
                    </div>
                    <div>
                        <p><strong>Файл мониторинга:</strong> 
                            <span class="<?php echo file_exists('seo-monitor.php') ? 'status-ok' : 'status-error'; ?>">
                                <?php echo file_exists('seo-monitor.php') ? '✅ Существует' : '❌ Не найден'; ?>
                            </span>
                        </p>
                        <p><strong>SQL скрипт:</strong> 
                            <span class="<?php echo file_exists('create_seo_monitoring_table.sql') ? 'status-ok' : 'status-error'; ?>">
                                <?php echo file_exists('create_seo_monitoring_table.sql') ? '✅ Существует' : '❌ Не найден'; ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Создание таблицы -->
            <?php if (!$table_exists): ?>
            <div class="section">
                <h3>🗄️ Создание таблицы мониторинга</h3>
                <p>Для работы автоматического SEO мониторинга необходимо создать таблицу в базе данных.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="create_table">
                    <button type="submit">🗄️ Создать таблицу мониторинга</button>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Тестирование мониторинга -->
            <?php if ($table_exists): ?>
            <div class="section">
                <h3>🧪 Тестирование мониторинга</h3>
                <p>Запустите тестовую проверку SEO для проверки работы системы мониторинга.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="test_monitoring">
                    <button type="submit" class="btn-info">🧪 Запустить тестовый мониторинг</button>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Настройка автоматического мониторинга -->
            <div class="section">
                <h3>⏰ Настройка автоматического мониторинга</h3>
                <p>Для автоматического мониторинга SEO добавьте следующую задачу в cron:</p>
                <div class="code-block">
# Проверка SEO каждые 6 часов<br>
0 */6 * * * /usr/bin/php <?php echo realpath('seo-monitor.php'); ?><br><br>
# Или проверка каждый день в 9:00<br>
0 9 * * * /usr/bin/php <?php echo realpath('seo-monitor.php'); ?>
                </div>
                <p><strong>Примечание:</strong> Замените путь к PHP на актуальный путь на вашем сервере.</p>
            </div>
            
            <!-- API для внешнего мониторинга -->
            <div class="section">
                <h3>🔌 API для внешнего мониторинга</h3>
                <p>Для интеграции с внешними системами мониторинга используйте следующие URL:</p>
                <div class="code-block">
# Запуск мониторинга через HTTP<br>
GET <?php echo 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']); ?>/seo-monitor.php<br><br>
# Получение результатов в JSON формате<br>
curl -X GET "<?php echo 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']); ?>/seo-monitor.php"
                </div>
            </div>
            
            <!-- Мониторинг через веб-интерфейс -->
            <div class="section">
                <h3>🌐 Веб-интерфейс мониторинга</h3>
                <p>Для просмотра результатов мониторинга используйте веб-интерфейс:</p>
                <div class="grid">
                    <div>
                        <a href="seo-auto.php" class="btn-info" style="text-decoration: none; display: inline-block; padding: 10px 20px;">🚀 SEO Автоматика</a>
                        <p>Основной интерфейс для управления SEO</p>
                    </div>
                    <div>
                        <a href="logs.php" class="btn-warning" style="text-decoration: none; display: inline-block; padding: 10px 20px;">📋 Логи системы</a>
                        <p>Просмотр логов и истории изменений</p>
                    </div>
                </div>
            </div>
            
            <!-- Информация о мониторинге -->
            <div class="section">
                <h3>ℹ️ Информация о мониторинге</h3>
                <div class="grid">
                    <div>
                        <h4>🔍 Проверяемые элементы:</h4>
                        <ul>
                            <li>Мета-теги (title, description, keywords)</li>
                            <li>Open Graph и Twitter Card теги</li>
                            <li>Canonical и hreflang теги</li>
                            <li>Оптимизация изображений</li>
                            <li>Структурированные данные (Schema.org)</li>
                            <li>Sitemap файлы</li>
                            <li>Скорость загрузки страницы</li>
                            <li>Мобильная версия сайта</li>
                            <li>Настройки безопасности</li>
                        </ul>
                    </div>
                    <div>
                        <h4>📊 Статусы проверок:</h4>
                        <ul>
                            <li><span class="status-ok">✅ OK</span> - Все в порядке</li>
                            <li><span class="status-error">⚠️ Warning</span> - Требует внимания</li>
                            <li><span class="status-error">❌ Error</span> - Критическая проблема</li>
                        </ul>
                        
                        <h4>💾 Хранение данных:</h4>
                        <ul>
                            <li>Результаты сохраняются в БД</li>
                            <li>История мониторинга доступна</li>
                            <li>JSON формат для API</li>
                            <li>Автоматическая очистка старых данных</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
