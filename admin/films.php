<?php
ob_start();
require_once __DIR__ . '/bootstrap.php';
include __DIR__ . '/lang_functions.php';
require_once __DIR__ . '/sitemap_helper.php';

$pdo = getDBConnection();
if ($pdo) {
    // Успешное подключение
} else {
    $error_message = "❌ Ошибка подключения к базе данных";
    writeLog('db_error', "Ошибка подключения к БД в films.php: не удалось подключиться", 'error');
}

// Function to safely encode Ukrainian text with apostrophes for JavaScript
function safeJsonEncode($text) {
    // Replace Ukrainian apostrophes with safe alternatives for JavaScript
    $text = str_replace("'", "'", $text); // Replace curly apostrophe with straight
    $text = str_replace("'", "'", $text); // Replace other curly apostrophes
    $text = str_replace("'", "'", $text); // Replace other curly apostrophes
    $text = str_replace("'", "'", $text); // Replace other curly apostrophes
    $text = str_replace("'", "'", $text); // Replace other curly apostrophes
    $text = str_replace("'", "'", $text); // Replace other curly apostrophes
    
    // Encode for JavaScript
    return json_encode($text, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
}

// Function to load films from database
function loadFilmsFromDB($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM films ORDER BY sort_order");
        $films = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $films[] = [
                'id' => $row['id'],
                'image' => $row['image'],
                'alt_text' => $row['alt_text'] ?? $row['alt_uk'] ?? '',
                'alt_uk' => $row['alt_uk'] ?? '',
                'alt_ru' => $row['alt_ru'] ?? '',
                'title_uk' => $row['title_uk'] ?? $row['name_uk'],
                'title_ru' => $row['title_ru'] ?? $row['name_ru'],
                'description_uk' => $row['description_uk'],
                'description_ru' => $row['description_ru'],
                'features_uk' => json_decode($row['features_uk'] ?? '[]', true) ?: [],
                'features_ru' => json_decode($row['features_ru'] ?? '[]', true) ?: []
            ];
        }
        return $films;
    } catch (PDOException $e) {
        writeLog('db_error', "Ошибка загрузки пленок из БД: " . $e->getMessage(), 'error');
        return [];
    }
}

// Function to update index.html with current films data
function updateIndexHtml($films) {
    $html_content = file_get_contents(INDEX_HTML_PATH);
    if (!$html_content) {
        return false;
    }
    
    // Find the films section in index.html
    $films_pattern = '/<section[^>]*id="films"[^>]*>.*?<\/section>/s';
    if (!preg_match($films_pattern, $html_content, $matches)) {
        return false;
    }
    
    $old_films_section = $matches[0];
    
    // Generate new films HTML
    $new_films_html = '<section id="films">';
    $new_films_html .= '<div class="container">';
    $new_films_html .= '<h2 data-lang-uk="Наші плівки" data-lang-ru="Наши пленки">Наші плівки</h2>';
    $new_films_html .= '<div class="films-container">';
    
    foreach ($films as $film) {
        $new_films_html .= '<article class="film-card">';
        $new_films_html .= '<figure>';
        $image_path = IMAGES_DIR . '/' . $film['image'];
        
        // Принудительно обновляем время модификации файла для cache-busting
        if (file_exists($image_path)) {
            touch($image_path);
        }
        
        $cache_buster = file_exists($image_path) ? filemtime($image_path) : time();
        $new_films_html .= '<img src="images/' . htmlspecialchars($film['image']) . '?v=' . $cache_buster . '" alt="' . htmlspecialchars($film['alt_uk']) . '" data-alt-uk="' . htmlspecialchars($film['alt_uk']) . '" data-alt-ru="' . htmlspecialchars($film['alt_ru']) . '" class="film-img" loading="lazy" decoding="async">';
        $new_films_html .= '<figcaption class="film-content">';
        $new_films_html .= '<h3 data-lang-uk="' . htmlspecialchars($film['title_uk']) . '" data-lang-ru="' . htmlspecialchars($film['title_ru']) . '">' . htmlspecialchars($film['title_uk']) . '</h3>';
        $new_films_html .= '<p data-lang-uk="' . htmlspecialchars($film['description_uk']) . '" data-lang-ru="' . htmlspecialchars($film['description_ru']) . '">' . htmlspecialchars($film['description_uk']) . '</p>';
        
        // Add features if they exist
        if (!empty($film['features_uk']) || !empty($film['features_ru'])) {
            $features_uk = implode('|', array_map('htmlspecialchars', $film['features_uk'] ?? []));
            $features_ru = implode('|', array_map('htmlspecialchars', $film['features_ru'] ?? []));
            
            $new_films_html .= '<ul class="film-features" data-features-uk="' . $features_uk . '" data-features-ru="' . $features_ru . '">';
            
            $uk_features = array_values($film['features_uk'] ?? []);
            $ru_features = array_values($film['features_ru'] ?? []);
            for ($i=0; $i<max(count($uk_features),count($ru_features)); $i++) {
                $uk = htmlspecialchars(trim($uk_features[$i] ?? ''));
                $ru = htmlspecialchars(trim($ru_features[$i] ?? ''));
                $new_films_html .= '<li data-lang-uk="'.$uk.'" data-lang-ru="'.$ru.'">'.$uk.'</li>';
            }
            $new_films_html .= '</ul>';
        }
        
        $new_films_html .= '</figcaption>';
        $new_films_html .= '</figure>';
        $new_films_html .= '</article>';
    }
    
    $new_films_html .= '</div>';
    $new_films_html .= '</div>';
    $new_films_html .= '</section>';
    
    // Replace the old films section with the new one
    $updated_html = str_replace($old_films_section, $new_films_html, $html_content);
    
    // Write the updated content back to index.html
    $result = adminAtomicWrite(INDEX_HTML_PATH, $updated_html);
    
    // Принудительно обновляем время модификации index.html для предотвращения кеширования
    if ($result !== false) {
        touch(INDEX_HTML_PATH);
    }
    
    return $result !== false;
}

// Load films from database
if ($pdo) {
    $films = loadFilmsFromDB($pdo);
} else {
    $films = [];
    $error_message = "❌ Невозможно загрузить данные пленок из-за ошибки подключения к базе данных.";
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$pdo) adminFail('Нет соединения с базой данных. Изменения не сохранены.',503);
    if (!isset($_POST['action']) || !is_string($_POST['action'])) adminFail('Не указано действие.');
    if (in_array($_POST['action'], ['edit','delete','replace_image'], true) && (!isset($_POST['index']) || !is_scalar($_POST['index']) || !ctype_digit((string)$_POST['index']) || !isset($films[(int)$_POST['index']]))) adminFail('Запись не найдена. Обновите страницу.',404);

    if (!validateCsrf()) {
        $error_message = 'Недействительный запрос (CSRF). Обновите страницу и попробуйте снова.';
    } elseif ($_POST['action'] === 'add') {
        // Validate required fields
        $validation_errors = [];
        
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $validation_errors[] = "Изображение обязательно для загрузки.";
        }
        
        if (empty($_POST['alt_uk']) || adminTextLength($_POST['alt_uk']) < 10 || adminTextLength($_POST['alt_uk']) > 200) {
            $validation_errors[] = "Alt текст (UA) должен быть от 10 до 200 символов.";
        }
        
        if (empty($_POST['alt_ru']) || adminTextLength($_POST['alt_ru']) < 10 || adminTextLength($_POST['alt_ru']) > 200) {
            $validation_errors[] = "Alt текст (RU) должен быть от 10 до 200 символов.";
        }
        
        if (empty($_POST['title_uk']) || adminTextLength($_POST['title_uk']) < 5 || adminTextLength($_POST['title_uk']) > 100) {
            $validation_errors[] = "Название (UA) должно быть от 5 до 100 символов.";
        }
        
        if (empty($_POST['title_ru']) || adminTextLength($_POST['title_ru']) < 5 || adminTextLength($_POST['title_ru']) > 100) {
            $validation_errors[] = "Название (RU) должно быть от 5 до 100 символов.";
        }
        
        if (empty($_POST['description_uk']) || adminTextLength($_POST['description_uk']) < 20 || adminTextLength($_POST['description_uk']) > 500) {
            $validation_errors[] = "Описание (UA) должно быть от 20 до 500 символов.";
        }
        
        if (empty($_POST['description_ru']) || adminTextLength($_POST['description_ru']) < 20 || adminTextLength($_POST['description_ru']) > 500) {
            $validation_errors[] = "Описание (RU) должно быть от 20 до 500 символов.";
        }
        
        if (empty($_POST['features_uk']) || empty($_POST['features_ru'])) {
            $validation_errors[] = "Особенности (UA) и (RU) обязательны для заполнения.";
        }
        
        if (empty($validation_errors)) {
            $upload_dir = IMAGES_DIR . '/';
            $uploaded_file = $_FILES['image'];
            $tmp = $uploaded_file['tmp_name'];
            if (@getimagesize($tmp) === false) {
                $validation_errors[] = "Файл не является изображением или повреждён.";
            }
            $ext = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $validation_errors[] = "Разрешены только JPG, PNG, GIF.";
            }
        }
        if (empty($validation_errors)) {
            $upload_dir = IMAGES_DIR . '/';
            $uploaded_file = $_FILES['image'];
            $ext = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
            $filename = uniqid('film_', true) . '.' . $ext;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($uploaded_file['tmp_name'], $filepath)) {
                // Сохраняем в базу данных
                $stmt = $pdo->prepare("INSERT INTO films (image, alt_text, alt_uk, alt_ru, title_uk, title_ru, name_uk, name_ru, description_uk, description_ru, features_uk, features_ru, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $features_uk = array_filter(array_map('trim', explode("\n", $_POST['features_uk'])), function($item) { return !empty($item); });
                $features_ru = array_filter(array_map('trim', explode("\n", $_POST['features_ru'])), function($item) { return !empty($item); });
                
                $stmt->execute([
                    $filename,
                    $_POST['alt_uk'], // Use Ukrainian as default alt
                    $_POST['alt_uk'],
                    $_POST['alt_ru'],
                    $_POST['title_uk'],
                    $_POST['title_ru'],
                    $_POST['title_uk'], // name_uk same as title_uk
                    $_POST['title_ru'], // name_ru same as title_ru
                    $_POST['description_uk'],
                    $_POST['description_ru'],
                    json_encode($features_uk, JSON_UNESCAPED_UNICODE),
                    json_encode($features_ru, JSON_UNESCAPED_UNICODE),
                    count($films) // sort_order
                ]);
                
                // Перезагружаем данные из БД
                $films = loadFilmsFromDB($pdo);
                
                // Автоматически обновляем сайт после добавления новой пленки
                $html_updated = updateIndexHtml($films);
                
                // Generate language JSON files
                $json_generated = false;
                if ($pdo && $html_updated) {
                    $json_generated = generateLangFiles($pdo);
                }
                
                if ($html_updated) {
                    updateSitemapLastmod();
                    writeLog('add_film', "Добавлена пленка: {$filename} - {$_POST['title_uk']} (БД + HTML обновлен" . ($json_generated ? ' + JSON файлы' : '') . ")", 'success');
                } else {
                    writeLog('add_film', "Добавлена пленка в БД, но не удалось обновить HTML", 'warning');
                }
                
                ob_clean();
                header('Location: films.php');
                exit;
            } else {
                $error_message = "❌ Не удалось загрузить изображение.";
                writeLog('add_film', "Ошибка загрузки изображения: {$filename}", 'error');
            }
        } else {
            $error_message = "❌ Ошибки валидации:<br>" . implode("<br>", $validation_errors);
            writeLog('add_film', "Ошибки валидации при добавлении пленки: " . implode(", ", $validation_errors), 'error');
        }
    } elseif ($_POST['action'] === 'edit') {
        $index = $_POST['index'];
        if (isset($_POST['image']) && (!adminImageName($_POST['image']) || !is_file(IMAGES_DIR.'/'.$_POST['image']))) adminFail('Изображение не найдено.');
        $old_title = $films[$index]['title_uk'] ?? 'Неизвестная пленка';
        
        // Обновляем в базе данных
        $stmt = $pdo->prepare("UPDATE films SET image = ?, alt_text = ?, alt_uk = ?, alt_ru = ?, title_uk = ?, title_ru = ?, name_uk = ?, name_ru = ?, description_uk = ?, description_ru = ?, features_uk = ?, features_ru = ? WHERE id = ?");
        
        $features_uk = array_filter(array_map('trim', explode("\n", $_POST['features_uk'])), function($item) { return !empty($item); });
        $features_ru = array_filter(array_map('trim', explode("\n", $_POST['features_ru'])), function($item) { return !empty($item); });
        
        $stmt->execute([
            $_POST['image'],
            $_POST['alt_uk'], // Use Ukrainian as default alt_text
            $_POST['alt_uk'],
            $_POST['alt_ru'],
            $_POST['title_uk'],
            $_POST['title_ru'],
            $_POST['title_uk'], // name_uk same as title_uk
            $_POST['title_ru'], // name_ru same as title_ru
            $_POST['description_uk'],
            $_POST['description_ru'],
            json_encode($features_uk, JSON_UNESCAPED_UNICODE),
            json_encode($features_ru, JSON_UNESCAPED_UNICODE),
            $films[$index]['id']
        ]);
        
        // Перезагружаем данные из БД
        $films = loadFilmsFromDB($pdo);
        
        // Автоматически обновляем сайт после редактирования пленки
        $html_updated = updateIndexHtml($films);
        
        // Generate language JSON files
        $json_generated = false;
        if ($pdo && $html_updated) {
            $json_generated = generateLangFiles($pdo);
        }
        
        if ($html_updated) {
            updateSitemapLastmod();
            writeLog('edit_film', "Отредактирована пленка: {$old_title} -> {$_POST['title_uk']} (БД + HTML обновлен" . ($json_generated ? ' + JSON файлы' : '') . ")", 'success');
        } else {
            writeLog('edit_film', "Отредактирована пленка в БД, но не удалось обновить HTML", 'warning');
        }
    } elseif ($_POST['action'] === 'delete') {
        $index = $_POST['index'];
        $deleted_title = $films[$index]['title_uk'] ?? 'Неизвестная пленка';
        
        // Удаляем из базы данных
        $stmt = $pdo->prepare("DELETE FROM films WHERE id = ?");
        $stmt->execute([$films[$index]['id']]);
        
        // Перезагружаем данные из БД
        $films = loadFilmsFromDB($pdo);
        
        // Автоматически обновляем сайт после удаления пленки
        $html_updated = updateIndexHtml($films);
        
        // Generate language JSON files
        $json_generated = false;
        if ($pdo && $html_updated) {
            $json_generated = generateLangFiles($pdo);
        }
        
        if ($html_updated) {
            updateSitemapLastmod();
            writeLog('delete_film', "Удалена пленка: {$deleted_title} (БД + HTML обновлен" . ($json_generated ? ' + JSON файлы' : '') . ")", 'success');
        } else {
            writeLog('delete_film', "Удалена пленка из БД, но не удалось обновить HTML", 'warning');
        }
    } elseif ($_POST['action'] === 'replace_image') {
        $index = $_POST['index'];
        $old_image = $films[$index]['image'];
        if (!adminImageName($old_image)) adminFail('Некорректное имя изображения.');
        
        if (isset($_FILES['new_image']) && $_FILES['new_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = IMAGES_DIR . '/';
            $temp_file = $_FILES['new_image']['tmp_name'];
            $file_extension = strtolower(pathinfo($_FILES['new_image']['name'], PATHINFO_EXTENSION));
            
            if (!in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                $error_message = 'Неподдерживаемый формат файла. Разрешены только JPG, PNG, GIF.';
            } elseif (@getimagesize($temp_file) === false) {
                $error_message = 'Файл не является изображением или повреждён.';
            } else {
                
                // Переименовываем новое изображение в старое имя
                if (adminReplaceUpload($temp_file, $upload_dir . $old_image)) {
                    // Принудительно обновляем время модификации файла для cache-busting
                    touch($upload_dir . $old_image);
                    
                    $success_message = 'Изображение успешно заменено! Сайт будет обновлен автоматически.';
                    // Автоматически обновляем сайт после замены изображения
                    $html_updated = updateIndexHtml($films);
                    
                    // Generate language JSON files
                    $json_generated = false;
                    if ($pdo && $html_updated) {
                        $json_generated = generateLangFiles($pdo);
                    }
                    
                    if ($html_updated) {
                        updateSitemapLastmod();
                        writeLog('replace_film_image', "Заменено изображение пленки: {$old_image} (БД + HTML обновлен" . ($json_generated ? ' + JSON файлы' : '') . ")", 'success');
                    } else {
                        writeLog('replace_film_image', "Заменено изображение пленки, но не удалось обновить HTML", 'warning');
                    }
                } else {
                    $error_message = 'Ошибка при загрузке изображения.';
                    writeLog('replace_film_image', "Ошибка замены изображения пленки: {$old_image}", 'error');
                }
            }
        } else {
            $error_message = 'Ошибка при загрузке файла.';
        }
    } elseif ($_POST['action'] === 'update_site') {
        if (updateIndexHtml($films)) {
            updateSitemapLastmod();
            $success_message = '✅ Сайт успешно обновлен! Изменения применены к index.html';
            writeLog('update_site', "Принудительное обновление сайта (films)", 'success');
        } else {
            $error_message = '❌ Ошибка при обновлении сайта. Проверьте права доступа к index.html';
            writeLog('update_site', "Ошибка принудительного обновления сайта (films)", 'error');
        }
    }
    
    ob_clean();
    header('Location: films.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление пленками - Админ-панель</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
        .header { background: #333; color: white; padding: 20px; }
        .header h1 { margin: 0; }
        .back { float: right; color: white; text-decoration: none; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .nav { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
        .nav a { display: inline-block; padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; flex: 0 0 auto; white-space: nowrap; }
        .nav a:hover { background: #0056b3; }
        .content { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .form-group { margin-bottom: 30px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; }
        input[type="text"], textarea, select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; }
        textarea { height: 120px; resize: vertical; }
        button { background: #28a745; color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #218838; }
        .film-item { border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; border-radius: 10px; background: #f9f9f9; }
        .film-item img { max-width: 200px; height: auto; border-radius: 5px; margin: 10px 0; }
        .film-header { display: flex; gap: 20px; margin-bottom: 15px; }
        .film-header p { margin: 0; flex: 1; }
        .film-description { margin: 15px 0; }
        .film-description p { margin: 5px 0; }
        .btn-group { display: flex; gap: 10px; margin-top: 15px; }
        .btn-edit, .btn-delete { padding: 8px 12px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.3s ease; }
        .btn-edit { background: #007bff; color: white; }
        .btn-edit:hover { background: #0056b3; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,123,255,0.3); }
        .btn-replace { background: #fd7e14; color: white; padding: 8px 12px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.3s ease; }
        .btn-replace:hover { background: #e8690b; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(253,126,20,0.3); }
        .btn-delete { background: #dc3545; color: white; }
        .btn-delete:hover { background: #c82333; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(220,53,69,0.3); }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: white; margin: 5% auto; padding: 20px; border-radius: 10px; width: 80%; max-width: 800px; max-height: 80vh; overflow-y: auto; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: #000; }
        .info-box { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .modal button[type="submit"] { background: #28a745; color: white; padding: 10px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s ease; }
        .modal button[type="submit"]:hover { background: #218838; transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .required-field { color: #dc3545; font-weight: bold; }
        .info-box { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .info-box-toggle { background: #17a2b8; color: white; padding: 8px 12px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; margin-bottom: 10px; }
        .info-box-toggle:hover { background: #138496; }
        .info-box-content { display: block; }
        .info-box-content.collapsed { display: none; }
        .info-box-toggle .toggle-icon { transition: transform 0.3s ease; }
        .info-box-toggle.collapsed .toggle-icon { transform: rotate(-90deg); }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎨 Управление пленками</h1>
        <a href="index.php" class="back">← Назад</a>
    </div>
    
    <div class="container">
        <div class="nav">
            <?php include __DIR__ . '/nav.php'; ?>
        </div>
        
        <div class="content">
            <h2>🎨 Управление пленками</h2>
            
            <?php if (isset($success_message)): ?>
                <div class="success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <div class="info-box">
                <button class="info-box-toggle collapsed" onclick="toggleInfoBox()">
                    <span class="toggle-icon">▶</span> ℹ️ Информация о пленках
                </button>
                <div class="info-box-content collapsed" id="infoBoxContent">
                    <strong>ℹ️ Информация:</strong> Данные загружаются исключительно из базы данных. 
                    Все изменения сохраняются в БД и автоматически синхронизируются с сайтом.
                    <br><strong>💡 Подсказка:</strong> Оба языка отображаются с метками "(UA)" и "(RU)" для удобства редактирования.
                    <br><strong>✅ Исправления:</strong> Названия отображаются рядом, добавлен dual-language alt текст, исправлены описания, добавлены особенности.
                    <br><strong>📤 Загрузка файлов:</strong> Теперь можно загружать изображения напрямую, как в галерее.
                    <br><strong>🖼️ Замена изображений:</strong> Добавлена функция замены изображений с сохранением имени файла.
                    <br><strong>🔍 Alt-атрибуты:</strong> Поддержка dual-language alt текста для лучшего SEO.
                    <br><strong>✅ Валидация:</strong> Все поля обязательны с проверкой длины и формата.
                    <br><strong>🔄 Обновление сайта:</strong> Автоматическое обновление index.html после изменений + кнопка "Обновить сайт".
                    <br><strong>🔤 Апострофы:</strong> Безопасная обработка апострофов в украинских словах.
                    <br><strong>🗄️ База данных:</strong> Все данные хранятся в MySQL базе данных.
                    <br><strong>🔄 Синхронизация:</strong> Автоматическая синхронизация с фронтендом через API.
                </div>
            </div>
            
            <h3>➕ Добавить новый тип пленки</h3>
            
            <form method="POST" enctype="multipart/form-data" onsubmit="return validateFilmForm()">
                <?php echo getCsrfField(); ?>
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label>Изображение:</label>
                    <input type="file" name="image" accept="image/*" required>
                    <small style="color: #666;">Поддерживаемые форматы: JPG, PNG, GIF</small>
                </div>
                
                <div class="grid">
                    <div class="form-group">
                        <label>Alt текст (UA): <span class="required-field">*</span></label>
                        <input type="text" name="alt_uk" id="uploadAltUk" placeholder="Дзеркальні сонцезахисні плівки для захисту від сонячного випромінювання" required minlength="10" maxlength="200">
                        <small style="color: #666;">Минимум 10 символов, максимум 200 символов</small>
                    </div>
                    <div class="form-group">
                        <label>Alt текст (RU): <span class="required-field">*</span></label>
                        <input type="text" name="alt_ru" id="uploadAltRu" placeholder="Зеркальные солнцезащитные пленки для защиты от солнечного излучения" required minlength="10" maxlength="200">
                        <small style="color: #666;">Минимум 10 символов, максимум 200 символов</small>
                    </div>
                </div>
                
                <div class="grid">
                    <div class="form-group">
                        <label>Название (UA): <span class="required-field">*</span></label>
                        <input type="text" name="title_uk" id="uploadTitleUk" placeholder="Дзеркальні сонцезахисні" required minlength="5" maxlength="100">
                        <small style="color: #666;">Минимум 5 символов, максимум 100 символов</small>
                    </div>
                    <div class="form-group">
                        <label>Название (RU): <span class="required-field">*</span></label>
                        <input type="text" name="title_ru" id="uploadTitleRu" placeholder="Зеркальные солнцезащитные" required minlength="5" maxlength="100">
                        <small style="color: #666;">Минимум 5 символов, максимум 100 символов</small>
                    </div>
                </div>
                
                <div class="grid">
                    <div class="form-group">
                        <label>Описание (UA): <span class="required-field">*</span></label>
                        <textarea name="description_uk" id="uploadDescriptionUk" required minlength="20" maxlength="500" placeholder="Захищають від сонячного випромінювання, зменшують нагрівання приміщень, знижують витрати на кондиціонування." rows="4"></textarea>
                        <small style="color: #666;">Минимум 20 символов, максимум 500 символов</small>
                    </div>
                    <div class="form-group">
                        <label>Описание (RU): <span class="required-field">*</span></label>
                        <textarea name="description_ru" id="uploadDescriptionRu" required minlength="20" maxlength="500" placeholder="Защищают от солнечного излучения, уменьшают нагрев помещений, снижают расходы на кондиционирование." rows="4"></textarea>
                        <small style="color: #666;">Минимум 20 символов, максимум 500 символов</small>
                    </div>
                </div>
                
                <div class="grid">
                    <div class="form-group">
                        <label>Особенности (UA) - по одной на строку:</label>
                        <textarea name="features_uk" id="uploadFeaturesUk" placeholder="Енергозбереження&#10;Захист від УФ-променів&#10;Зменшення нагрівання&#10;Елегантний дзеркальний ефект" rows="4"></textarea>
                        <small style="color: #666;">Каждая особенность с новой строки</small>
                    </div>
                    <div class="form-group">
                        <label>Особенности (RU) - по одной на строку:</label>
                        <textarea name="features_ru" id="uploadFeaturesRu" placeholder="Энергосбережение&#10;Защита от УФ-лучей&#10;Уменьшение нагрева&#10;Элегантный зеркальный эффект" rows="4"></textarea>
                        <small style="color: #666;">Каждая особенность с новой строки</small>
                    </div>
                </div>
                
                <div style="text-align: center; margin: 40px 0;">
                    <button type="submit" style="font-size: 18px; padding: 20px 40px;">
                        ✅ Добавить пленку
                    </button>
                </div>
            </form>
            
            <h3>📋 Существующие пленки</h3>
            <?php if (empty($films)): ?>
                <p>Пленки не найдены. Добавьте первую пленку выше.</p>
            <?php else: ?>
                <div style="margin-bottom: 20px;">
                    <form method="POST" style="display: inline;">
                        <?php echo getCsrfField(); ?>
                        <input type="hidden" name="action" value="update_site">
                        <button type="submit" style="background: #17a2b8; color: white; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer;">🔄 Обновить сайт</button>
                    </form>
                    <small style="color: #666; margin-left: 10px;">Принудительно обновить пленки на сайте</small>
                </div>
                <?php foreach ($films as $index => $film): ?>
                    <div class="film-item">
                        <div class="film-header">
                            <p><strong>Название (UA):</strong> <?php echo htmlspecialchars($film['title_uk']); ?></p>
                            <p><strong>Название (RU):</strong> <?php echo htmlspecialchars($film['title_ru']); ?></p>
                        </div>
                        <?php if (file_exists(IMAGES_DIR . '/' . $film['image'])): ?>
                            <?php 
                            $image_path = IMAGES_DIR . '/' . $film['image'];
                            $cache_buster = file_exists($image_path) ? filemtime($image_path) : time(); // Используем уникальный timestamp
                            ?>
                            <img src="../images/<?php echo htmlspecialchars($film['image']); ?>?v=<?php echo $cache_buster; ?>" alt="<?php echo htmlspecialchars($film['alt_text']); ?>">
                        <?php else: ?>
                            <p style="color: red;">⚠️ Файл не найден: <?php echo htmlspecialchars($film['image']); ?></p>
                        <?php endif; ?>
                        <div class="film-description">
                            <p><strong>Описание (UA):</strong> <?php echo htmlspecialchars($film['description_uk']); ?></p>
                            <p><strong>Описание (RU):</strong> <?php echo htmlspecialchars($film['description_ru']); ?></p>
                            <p><strong>Alt текст (UA):</strong> <?php echo htmlspecialchars($film['alt_uk']); ?></p>
                            <p><strong>Alt текст (RU):</strong> <?php echo htmlspecialchars($film['alt_ru']); ?></p>
                        </div>
                        <?php if (!empty($film['features_uk'])): ?>
                            <p><strong>Особенности (UA):</strong></p>
                            <ul>
                                <?php foreach ($film['features_uk'] as $feature): ?>
                                    <li><?php echo htmlspecialchars(trim($feature)); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (!empty($film['features_ru'])): ?>
                            <p><strong>Особенности (RU):</strong></p>
                            <ul>
                                <?php foreach ($film['features_ru'] as $feature): ?>
                                    <li><?php echo htmlspecialchars(trim($feature)); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <div class="btn-group">
                            <button class="btn-edit" onclick='editFilm(<?php echo $index; ?>, <?php echo safeJsonEncode($film['image']); ?>, <?php echo safeJsonEncode($film['alt_uk']); ?>, <?php echo safeJsonEncode($film['alt_ru']); ?>, <?php echo safeJsonEncode($film['title_uk']); ?>, <?php echo safeJsonEncode($film['title_ru']); ?>, <?php echo safeJsonEncode($film['description_uk']); ?>, <?php echo safeJsonEncode($film['description_ru']); ?>, <?php echo safeJsonEncode(implode("\n", $film['features_uk'] ?? [])); ?>, <?php echo safeJsonEncode(implode("\n", $film['features_ru'] ?? [])); ?>)'>✏️ Редактировать</button>
                            <button class="btn-replace" onclick='replaceFilmImage(<?php echo $index; ?>, <?php echo safeJsonEncode($film['image']); ?>)'>🖼️ Заменить изображение</button>
                            <form method="POST" style="display: inline;">
                                <?php echo getCsrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="index" value="<?php echo $index; ?>">
                                <button type="submit" class="btn-delete" onclick="return confirm('Удалить эту пленку?')">🗑️ Удалить</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3>Редактировать пленку</h3>
            <form method="POST">
                <?php echo getCsrfField(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="index" id="editIndex">
                <div class="grid">
                    <div>
                        <div class="form-group">
                            <label>Изображение:</label>
                            <input type="text" name="image" id="editImage" required>
                        </div>
                        <div class="form-group">
                            <label>Alt текст (UA): <span class="required-field">*</span></label>
                            <input type="text" name="alt_uk" id="editAltUk" required minlength="10" maxlength="200">
                        </div>
                        <div class="form-group">
                            <label>Alt текст (RU): <span class="required-field">*</span></label>
                            <input type="text" name="alt_ru" id="editAltRu" required minlength="10" maxlength="200">
                        </div>
                        <div class="form-group">
                            <label>Название (UA): <span class="required-field">*</span></label>
                            <input type="text" name="title_uk" id="editTitleUk" required minlength="5" maxlength="100">
                        </div>
                        <div class="form-group">
                            <label>Название (RU): <span class="required-field">*</span></label>
                            <input type="text" name="title_ru" id="editTitleRu" required minlength="5" maxlength="100">
                        </div>
                    </div>
                    <div>
                        <div class="form-group">
                            <label>Описание (UA): <span class="required-field">*</span></label>
                            <textarea name="description_uk" id="editDescriptionUk" required minlength="20" maxlength="500" rows="4"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Описание (RU): <span class="required-field">*</span></label>
                            <textarea name="description_ru" id="editDescriptionRu" required minlength="20" maxlength="500" rows="4"></textarea>
                        </div>
                    </div>
                </div>
                <div class="grid">
                    <div class="form-group">
                        <label>Особенности (UA) - по одной на строку:</label>
                        <textarea name="features_uk" id="editFeaturesUk" rows="4" placeholder="Введите особенности на украинском языке, каждую с новой строки"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Особенности (RU) - по одной на строку:</label>
                        <textarea name="features_ru" id="editFeaturesRu" rows="4" placeholder="Введите особенности на русском языке, каждую с новой строки"></textarea>
                    </div>
                </div>
                <button type="submit">Сохранить изменения</button>
            </form>
        </div>
    </div>

    <!-- Replace Image Modal -->
    <div id="replaceModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeReplaceModal()">&times;</span>
            <h3>🖼️ Заменить изображение пленки</h3>
            <form method="POST" enctype="multipart/form-data">
                <?php echo getCsrfField(); ?>
                <input type="hidden" name="action" value="replace_image">
                <input type="hidden" name="index" id="replaceIndex">
                <input type="hidden" name="old_image" id="replaceOldImage">
                
                <div class="form-group">
                    <label>Текущее изображение:</label>
                    <img id="replaceCurrentImage" src="" alt="Текущее изображение" style="max-width: 200px; max-height: 150px; border: 1px solid #ddd; margin: 10px 0;">
                </div>
                
                <div class="form-group">
                    <label>Новое изображение: <span class="required-field">*</span></label>
                    <input type="file" name="new_image" accept="image/*" required>
                    <small style="color: #666;">Поддерживаемые форматы: JPG, PNG, GIF. Старое изображение будет заменено.</small>
                </div>
                
                <button type="submit">🔄 Заменить изображение</button>
            </form>
        </div>
    </div>

    <script>
        function editFilm(index, image, altUk, altRu, titleUk, titleRu, descriptionUk, descriptionRu, featuresUk, featuresRu) {
            try {
                // Функция для безопасной обработки апострофов в украинских словах
                function safeDecode(text) {
                    if (!text) return '';
                    // Заменяем различные типы апострофов на безопасные
                    return text.replace(/[''']/g, "'").replace(/["""]/g, '"');
                }
                
                // Проверяем существование всех элементов
                const elements = {
                    'editIndex': document.getElementById('editIndex'),
                    'editImage': document.getElementById('editImage'),
                    'editAltUk': document.getElementById('editAltUk'),
                    'editAltRu': document.getElementById('editAltRu'),
                    'editTitleUk': document.getElementById('editTitleUk'),
                    'editTitleRu': document.getElementById('editTitleRu'),
                    'editDescriptionUk': document.getElementById('editDescriptionUk'),
                    'editDescriptionRu': document.getElementById('editDescriptionRu'),
                    'editFeaturesUk': document.getElementById('editFeaturesUk'),
                    'editFeaturesRu': document.getElementById('editFeaturesRu'),
                    'editModal': document.getElementById('editModal')
                };
                
                // Проверяем, что все элементы существуют
                for (const [name, element] of Object.entries(elements)) {
                    if (!element) {
                        console.error('Элемент не найден:', name);
                        alert('Ошибка: элемент ' + name + ' не найден. Пожалуйста, обновите страницу.');
                        return;
                    }
                }
                
                // Заполняем поля с безопасной обработкой апострофов
                elements.editIndex.value = index || '';
                elements.editImage.value = safeDecode(image) || '';
                elements.editAltUk.value = safeDecode(altUk) || '';
                elements.editAltRu.value = safeDecode(altRu) || '';
                elements.editTitleUk.value = safeDecode(titleUk) || '';
                elements.editTitleRu.value = safeDecode(titleRu) || '';
                elements.editDescriptionUk.value = safeDecode(descriptionUk) || '';
                elements.editDescriptionRu.value = safeDecode(descriptionRu) || '';
                elements.editFeaturesUk.value = safeDecode(featuresUk) || '';
                elements.editFeaturesRu.value = safeDecode(featuresRu) || '';
                
                // Показываем модальное окно
                elements.editModal.style.display = 'block';
                
                console.log('Модальное окно редактирования открыто для пленки с индексом:', index);
                console.log('Данные пленки:', {
                    titleUk: safeDecode(titleUk),
                    titleRu: safeDecode(titleRu),
                    descriptionUk: safeDecode(descriptionUk),
                    descriptionRu: safeDecode(descriptionRu)
                });
                
            } catch (error) {
                console.error('Ошибка при открытии модального окна редактирования:', error);
                alert('Ошибка при открытии модального окна: ' + error.message);
            }
        }
        
        function replaceFilmImage(index, image) {
            try {
                // Проверяем существование всех элементов
                const replaceIndex = document.getElementById('replaceIndex');
                const replaceOldImage = document.getElementById('replaceOldImage');
                const replaceCurrentImage = document.getElementById('replaceCurrentImage');
                const replaceModal = document.getElementById('replaceModal');
                
                if (!replaceIndex || !replaceOldImage || !replaceCurrentImage || !replaceModal) {
                    console.error('Элементы модального окна замены не найдены');
                    alert('Ошибка: элементы модального окна замены не найдены. Пожалуйста, обновите страницу.');
                    return;
                }
                
                // Заполняем поля
                replaceIndex.value = index || '';
                replaceOldImage.value = image || '';
                replaceCurrentImage.src = '../images/' + (image || '') + '?v=' + Date.now();
                
                // Показываем модальное окно
                replaceModal.style.display = 'block';
                
                console.log('Модальное окно замены изображения открыто для пленки с индексом:', index);
                
            } catch (error) {
                console.error('Ошибка при открытии модального окна замены изображения:', error);
                alert('Ошибка при открытии модального окна замены: ' + error.message);
            }
        }
        
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function closeReplaceModal() {
            document.getElementById('replaceModal').style.display = 'none';
        }
        
        function toggleInfoBox() {
            const infoBoxContent = document.getElementById('infoBoxContent');
            const toggleIcon = document.querySelector('.info-box-toggle .toggle-icon');
            
            if (infoBoxContent.classList.contains('collapsed')) {
                infoBoxContent.classList.remove('collapsed');
                toggleIcon.textContent = '▼'; // Change to ▼
            } else {
                infoBoxContent.classList.add('collapsed');
                toggleIcon.textContent = '▶'; // Change to ▶
            }
        }

        window.onclick = function(event) {
            var editModal = document.getElementById('editModal');
            var replaceModal = document.getElementById('replaceModal');
            if (event.target == editModal) {
                editModal.style.display = 'none';
            }
            if (event.target == replaceModal) {
                replaceModal.style.display = 'none';
            }
        }

        function validateFilmForm() {
            let isValid = true;
            const imageInput = document.querySelector('input[name="image"]');
            const altUkInput = document.getElementById('uploadAltUk');
            const altRuInput = document.getElementById('uploadAltRu');
            const titleUkInput = document.getElementById('uploadTitleUk');
            const titleRuInput = document.getElementById('uploadTitleRu');
            const descriptionUkInput = document.getElementById('uploadDescriptionUk');
            const descriptionRuInput = document.getElementById('uploadDescriptionRu');
            const featuresUkInput = document.getElementById('uploadFeaturesUk');
            const featuresRuInput = document.getElementById('uploadFeaturesRu');

            if (!imageInput.files.length) {
                alert('Пожалуйста, выберите изображение.');
                isValid = false;
            }

            if (altUkInput.value.length < 10 || altUkInput.value.length > 200) {
                alert('Alt текст (UA) должен быть от 10 до 200 символов.');
                isValid = false;
            }

            if (altRuInput.value.length < 10 || altRuInput.value.length > 200) {
                alert('Alt текст (RU) должен быть от 10 до 200 символов.');
                isValid = false;
            }

            if (titleUkInput.value.length < 5 || titleUkInput.value.length > 100) {
                alert('Название (UA) должно быть от 5 до 100 символов.');
                isValid = false;
            }

            if (titleRuInput.value.length < 5 || titleRuInput.value.length > 100) {
                alert('Название (RU) должно быть от 5 до 100 символов.');
                isValid = false;
            }

            if (descriptionUkInput.value.length < 20 || descriptionUkInput.value.length > 500) {
                alert('Описание (UA) должно быть от 20 до 500 символов.');
                isValid = false;
            }

            if (descriptionRuInput.value.length < 20 || descriptionRuInput.value.length > 500) {
                alert('Описание (RU) должно быть от 20 до 500 символов.');
                isValid = false;
            }

            if (featuresUkInput.value.trim() === '') {
                alert('Особенности (UA) не могут быть пустыми.');
                isValid = false;
            }

            if (featuresRuInput.value.trim() === '') {
                alert('Особенности (RU) не могут быть пустыми.');
                isValid = false;
            }

            return isValid;
        }
    </script>
</body>
</html> 