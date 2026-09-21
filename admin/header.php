<?php
/**
 * Общий шапка админки: HTML head, шапка страницы, навигация.
 * Перед включением задать: $page_title (обязательно), $show_back (опционально, true = ссылка «Назад», иначе «Выйти»).
 */
$page_title = $page_title ?? 'Админ-панель';
$show_back = $show_back ?? false;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Админ-панель tonirovka.kh.ua</title>
    <link rel="stylesheet" href="assets/admin.css?v=20260921-stats">
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
            <?php include __DIR__ . '/nav.php'; ?>
        </div>
        <div class="content">
