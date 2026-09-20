<?php
/**
 * Общий шапка админки: HTML head, шапка страницы, навигация.
 * Перед включением задать: $page_title (обязательно), $show_back (опционально, true = ссылка «Назад», иначе «Выйти»).
 */
$page_title = $page_title ?? 'Админ-панель';
$show_back = $show_back ?? false;
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Админ-панель tonirovka.kh.ua</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
    <div class="header">
        <h1><?php echo htmlspecialchars($page_title); ?></h1>
        <?php if ($show_back): ?>
            <a href="index.php" class="back">← Назад</a>
        <?php else: ?>
            <a href="logout.php" class="logout">Выйти</a>
        <?php endif; ?>
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
