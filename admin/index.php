<?php
session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/csrf_functions.php';

$admin_username = $_ENV['ADMIN_USERNAME'] ?? getenv('ADMIN_USERNAME') ?: 'admin';
$admin_password_hash = $_ENV['ADMIN_PASSWORD_HASH'] ?? getenv('ADMIN_PASSWORD_HASH') ?: '';

// Проверка входа
if (!isset($_SESSION['admin_logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
        if (!validateCsrf()) {
            $error = 'Недействительная форма. Обновите страницу и попробуйте снова.';
        } elseif (!$admin_password_hash) {
            $error = 'Не настроен .env: задайте ADMIN_PASSWORD_HASH (см. admin/.env.example).';
        } elseif ($_POST['username'] === $admin_username && password_verify($_POST['password'], $admin_password_hash)) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
        } else {
            $error = 'Неверные данные для входа';
        }
    }
}

// Если не авторизован, показываем форму входа
if (!isset($_SESSION['admin_logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="uk">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Админ-панель - tonirovka.kh.ua</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
            .login-container { max-width: 400px; margin: 100px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
            .form-group { margin-bottom: 20px; }
            label { display: block; margin-bottom: 5px; font-weight: bold; }
            input[type="text"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; }
            button { background: #007bff; color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; width: 100%; }
            button:hover { background: #0056b3; }
            .error { color: red; margin-bottom: 15px; }
            h1 { text-align: center; color: #333; margin-bottom: 30px; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h1>🔐 Админ-панель</h1>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <?php echo getCsrfField(); ?>
                <div class="form-group">
                    <label>Логин:</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Пароль:</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit">Войти</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель - tonirovka.kh.ua</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
        .header { background: #333; color: white; padding: 20px; }
        .header h1 { margin: 0; }
        .logout { float: right; color: white; text-decoration: none; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .nav { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
        .nav a { display: inline-block; padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; flex: 0 0 auto; white-space: nowrap; }
        .nav a:hover { background: #0056b3; }
        .content { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="number"], textarea, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; }
        textarea { height: 100px; resize: vertical; }
        button { background: #28a745; color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #218838; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .card { background: #f8f9fa; padding: 20px; border-radius: 5px; border: 1px solid #dee2e6; }
        .image-preview { max-width: 200px; max-height: 150px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔧 Админ-панель tonirovka.kh.ua</h1>
        <a href="logout.php" class="logout">Выйти</a>
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
            <h2>📊 Панель управления</h2>
            
            <?php
            // Показываем статистику из БД
            $stats = [
                'Цены' => 0,
                'Изображения в галерее' => 0,
                'Типы пленок' => 0
            ];
            
            $pdo = getDBConnection();
            if ($pdo) {
                // Получаем статистику из БД
                $stats['Цены'] = $pdo->query("SELECT COUNT(*) FROM prices")->fetchColumn();
                $stats['Изображения в галерее'] = $pdo->query("SELECT COUNT(*) FROM gallery")->fetchColumn();
                $stats['Типы пленок'] = $pdo->query("SELECT COUNT(*) FROM films")->fetchColumn();
            } else {
                // Показываем ошибку подключения к БД
                echo '<div class="error">Ошибка подключения к базе данных</div>';
            }
            ?>
            
            <div class="grid">
                <?php foreach ($stats as $name => $count): ?>
                <div class="card">
                    <h3><?php echo $name; ?></h3>
                    <p style="font-size: 24px; font-weight: bold; color: #007bff;"><?php echo $count; ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            
            <h3>🚀 Быстрые действия</h3>
            <div class="grid">
                <div class="card">
                    <h4>💰 Управление ценами</h4>
                    <p>Добавляйте, редактируйте и удаляйте услуги и цены</p>
                    <a href="prices.php" style="color: #007bff;">Перейти →</a>
                </div>
                <div class="card">
                    <h4>🖼️ Управление галереей</h4>
                    <p>Загружайте новые изображения и управляйте галереей</p>
                    <a href="gallery.php" style="color: #007bff;">Перейти →</a>
                </div>
                <div class="card">
                    <h4>🎨 Управление пленками</h4>
                    <p>Редактируйте описания и характеристики пленок</p>
                    <a href="films.php" style="color: #007bff;">Перейти →</a>
                </div>
                <div class="card">
                    <h4>📝 Редактирование контента</h4>
                    <p>Изменяйте тексты, заголовки и описания</p>
                    <a href="content.php" style="color: #007bff;">Перейти →</a>
                </div>
                <div class="card">
                    <h4>🚀 SEO Автоматика</h4>
                    <p>Автоматическое управление SEO, мета-тегами и оптимизацией</p>
                    <a href="seo-auto.php" style="color: #007bff;">Перейти →</a>
                </div>
                
        <div class="card">
            <h4>🔧 Диагностика системы</h4>
            <p>Автоматическая проверка подключения к БД и состояния системы</p>
            <span style="color: #28a745;">✅ Работает автоматически</span>
        </div>
        <div class="card">
            <h4>📊 Извлечение данных</h4>
            <p>Автоматическое извлечение и синхронизация данных с сайта</p>
            <span style="color: #28a745;">✅ Работает автоматически</span>
        </div>
            </div>
        </div>
    </div>
</body>
</html> 