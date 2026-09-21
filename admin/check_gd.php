<?php
/**
 * Скрипт для проверки GD расширения
 * Откройте в браузере: https://tonirovka.kh.ua/admin/check_gd.php
 */

// Включаем буферизацию вывода для предотвращения проблем с SSL
if (!ob_get_level()) {
    ob_start();
}

require_once __DIR__ . '/bootstrap.php';

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Проверка GD расширения</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .status {
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .ok {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        h1 { color: #333; }
        h2 { color: #555; margin-top: 30px; }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        ul { line-height: 1.8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Проверка GD расширения PHP</h1>
        
        <?php
        // Проверка расширения GD
        $gd_loaded = extension_loaded('gd');
        $gd_info = $gd_loaded ? gd_info() : null;
        ?>
        
        <div class="status <?php echo $gd_loaded ? 'ok' : 'error'; ?>">
            <h2>Статус: <?php echo $gd_loaded ? '✅ GD установлено' : '❌ GD не установлено'; ?></h2>
        </div>
        
        <?php if ($gd_loaded && $gd_info): ?>
            <div class="status info">
                <h3>📊 Информация о GD:</h3>
                <ul>
                    <li><strong>Версия GD:</strong> <?php echo $gd_info['GD Version']; ?></li>
                    <li><strong>Поддержка JPEG:</strong> <?php echo isset($gd_info['JPEG Support']) && $gd_info['JPEG Support'] ? '✅ Да' : '❌ Нет'; ?></li>
                    <li><strong>Поддержка PNG:</strong> <?php echo isset($gd_info['PNG Support']) && $gd_info['PNG Support'] ? '✅ Да' : '❌ Нет'; ?></li>
                    <li><strong>Поддержка GIF:</strong> <?php echo isset($gd_info['GIF Read Support']) && $gd_info['GIF Read Support'] ? '✅ Да' : '❌ Нет'; ?></li>
                    <li><strong>Поддержка WebP:</strong> <?php echo isset($gd_info['WebP Support']) && $gd_info['WebP Support'] ? '✅ Да' : '❌ Нет'; ?></li>
                </ul>
            </div>
            
            <div class="status ok">
                <h3>✅ Проверка функций:</h3>
                <ul>
                    <li><code>imagecreatefromjpeg()</code>: <?php echo function_exists('imagecreatefromjpeg') ? '✅' : '❌'; ?></li>
                    <li><code>imagecreatefrompng()</code>: <?php echo function_exists('imagecreatefrompng') ? '✅' : '❌'; ?></li>
                    <li><code>imagejpeg()</code>: <?php echo function_exists('imagejpeg') ? '✅' : '❌'; ?></li>
                    <li><code>imagepng()</code>: <?php echo function_exists('imagepng') ? '✅' : '❌'; ?></li>
                    <li><code>getimagesize()</code>: <?php echo function_exists('getimagesize') ? '✅' : '❌'; ?></li>
                </ul>
            </div>
            
        <?php else: ?>
            <div class="status error">
                <h3>❌ GD не установлено</h3>
                <p><strong>Что делать:</strong></p>
                
                <h4>1. Если у вас Linux сервер (Ubuntu/Debian):</h4>
                <code>sudo apt-get update && sudo apt-get install php-gd</code>
                <p>Или для PHP 8.x:</p>
                <code>sudo apt-get install php8.x-gd</code>
                <p>После установки перезапустите веб-сервер:</p>
                <code>sudo systemctl restart apache2</code>
                <p>или</p>
                <code>sudo systemctl restart nginx && sudo systemctl restart php-fpm</code>
                
                <h4>2. Если у вас Windows + XAMPP/WAMP:</h4>
                <p>Откройте файл <code>php.ini</code> (обычно в папке установки XAMPP/WAMP)</p>
                <p>Найдите строку <code>;extension=gd</code> и уберите точку с запятой:</p>
                <code>extension=gd</code>
                <p>Перезапустите Apache в панели управления XAMPP/WAMP</p>
                
                <h4>3. Если у вас cPanel/хостинг:</h4>
                <p>Войдите в cPanel → Software → Select PHP Version → Extensions → найдите "gd" и включите его</p>
                <p>Или обратитесь в службу поддержки хостинга с запросом: "Пожалуйста, включите PHP расширение GD"</p>
                
                <h4>4. Если у вас Docker:</h4>
                <p>Добавьте в Dockerfile:</p>
                <code>RUN apt-get update && apt-get install -y libgd-dev && docker-php-ext-install gd</code>
                
                <h4>5. Общая информация:</h4>
                <p><strong>PHP версия:</strong> <?php echo phpversion(); ?></p>
                <p><strong>Файл php.ini:</strong> <?php echo php_ini_loaded_file(); ?></p>
                <p><strong>Дополнительные ini файлы:</strong> <?php echo php_ini_scanned_files() ?: 'Нет'; ?></p>
            </div>
        <?php endif; ?>
        
        <div class="status info">
            <h3>ℹ️ Дополнительная информация:</h3>
            <ul>
                <li><strong>PHP версия:</strong> <?php echo phpversion(); ?></li>
                <li><strong>Веб-сервер:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Неизвестно'; ?></li>
                <li><strong>Операционная система:</strong> <?php echo PHP_OS; ?></li>
                <li><strong>Файл php.ini:</strong> <?php 
                    $php_ini = php_ini_loaded_file(); 
                    if ($php_ini) {
                        echo $php_ini . ' ✅';
                    } else {
                        echo 'Используются отдельные .ini файлы';
                        $scanned = php_ini_scanned_files();
                        if ($scanned) {
                            echo ' (из ' . dirname($scanned) . '/conf.d/)';
                        }
                    }
                ?></li>
                <?php if (php_ini_scanned_files()): ?>
                <li><strong>Дополнительные ini файлы:</strong> <?php echo php_ini_scanned_files(); ?></li>
                <?php endif; ?>
                <li><strong>Путь к конфигурации:</strong> /usr/local/etc/php/</li>
                <li><strong>Путь к conf.d:</strong> /usr/local/etc/php/conf.d/</li>
            </ul>
        </div>
        
        <p style="margin-top: 30px;">
            <a href="seo-auto.php" style="color: #007bff;">← Вернуться к SEO Автоматике</a>
        </p>
    </div>
</body>
</html>
<?php
// Очищаем буфер и отправляем вывод
if (ob_get_level()) {
    ob_end_flush();
}
?>

