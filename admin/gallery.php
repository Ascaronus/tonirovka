<?php
require_once __DIR__ . '/bootstrap.php';
include __DIR__ . '/lang_functions.php';

$pdo = getDBConnection();
if ($pdo) {
    // Успешное подключение
} else {
    $error_message = "❌ Ошибка подключения к базе данных";
    writeLog('db_error', "Ошибка подключения к БД в gallery.php: не удалось подключиться", 'error');
}

// Функция для загрузки галереи из БД
function loadGalleryFromDB($pdo) {
    $stmt = $pdo->query("SELECT * FROM gallery ORDER BY sort_order");
    $gallery = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $gallery[] = [
            'id' => $row['id'],
            'filename' => $row['image'],
            'alt_uk' => $row['alt_uk'] ?? '',
            'alt_ru' => $row['alt_ru'] ?? '',
            'title_uk' => $row['title_uk'] ?? '',
            'title_ru' => $row['title_ru'] ?? ''
        ];
    }
    return $gallery;
}

// Validation function for gallery fields
function validateGalleryFields($alt_uk, $alt_ru, $title_uk, $title_ru) {
    $errors = [];
    
    // Check if fields are empty
    if (empty(trim($alt_uk))) {
        $errors[] = "Alt текст (UA) не может быть пустым";
    }
    if (empty(trim($alt_ru))) {
        $errors[] = "Alt текст (RU) не может быть пустым";
    }
    if (empty(trim($title_uk))) {
        $errors[] = "Название (UA) не может быть пустым";
    }
    if (empty(trim($title_ru))) {
        $errors[] = "Название (RU) не может быть пустым";
    }
    
    // Check minimum length (at least 10 characters)
    if (strlen(trim($alt_uk)) < 10) {
        $errors[] = "Alt текст (UA) должен содержать минимум 10 символов";
    }
    if (strlen(trim($alt_ru)) < 10) {
        $errors[] = "Alt текст (RU) должен содержать минимум 10 символов";
    }
    if (strlen(trim($title_uk)) < 5) {
        $errors[] = "Название (UA) должно содержать минимум 5 символов";
    }
    if (strlen(trim($title_ru)) < 5) {
        $errors[] = "Название (RU) должно содержать минимум 5 символов";
    }
    
    // Check maximum length (not more than 200 characters)
    if (strlen(trim($alt_uk)) > 200) {
        $errors[] = "Alt текст (UA) не должен превышать 200 символов";
    }
    if (strlen(trim($alt_ru)) > 200) {
        $errors[] = "Alt текст (RU) не должен превышать 200 символов";
    }
    if (strlen(trim($title_uk)) > 100) {
        $errors[] = "Название (UA) не должно превышать 100 символов (длина: " . strlen(trim($title_uk)) . ")";
    }
    if (strlen(trim($title_ru)) > 100) {
        $errors[] = "Название (RU) не должно превышать 100 символов (длина: " . strlen(trim($title_ru)) . ")";
    }
    
    return $errors;
}

$gallery_file = DATA_DIR . '/gallery.json';



// Function to update index.html with new gallery data
function updateIndexHtml($gallery) {
    $html_content = file_get_contents(INDEX_HTML_PATH);
    if (!$html_content) return false;
    
    // Find the gallery section - corrected pattern
    $gallery_pattern = '/(<section[^>]*id="gallery"[^>]*>.*?<div[^>]*class="gallery"[^>]*>)(.*?)(<\/div>\s*<\/div>\s*<\/section>)/s';
    
    if (preg_match($gallery_pattern, $html_content, $matches)) {
        $section_start = $matches[1];
        $section_end = $matches[3];
        
        // Build new gallery content
        $new_gallery = '';
        foreach ($gallery as $item) {
            $image_path = IMAGES_DIR . '/gallery/' . $item['filename'];
            
            // Принудительно обновляем время модификации файла для cache-busting
            if (file_exists($image_path)) {
                touch($image_path);
            }
            
            $cache_buster = file_exists($image_path) ? filemtime($image_path) : time();
            $new_gallery .= "                <figure class=\"gallery-item\">\n";
            $new_gallery .= "                    <img src=\"images/gallery/" . htmlspecialchars($item['filename']) . "?v=" . $cache_buster . "\" alt=\"" . htmlspecialchars($item['alt_uk']) . "\" data-alt-uk=\"" . htmlspecialchars($item['alt_uk']) . "\" data-alt-ru=\"" . htmlspecialchars($item['alt_ru']) . "\" onclick=\"openModal(this)\">\n";
            $new_gallery .= "                    <figcaption data-lang-uk=\"" . htmlspecialchars($item['title_uk']) . "\" data-lang-ru=\"" . htmlspecialchars($item['title_ru']) . "\">" . htmlspecialchars($item['title_uk']) . "</figcaption>\n";
            $new_gallery .= "                </figure>\n";
        }
        
        // Replace the gallery content
        $new_html = preg_replace_callback($gallery_pattern, function ($match) use ($new_gallery) {
            return $match[1] . $new_gallery . $match[3];
        }, $html_content, 1);
        
        // Save the updated HTML
        $result = file_put_contents(INDEX_HTML_PATH, $new_html);
        
        // Принудительно обновляем время модификации index.html для предотвращения кеширования
        if ($result !== false) {
            touch(INDEX_HTML_PATH);
        }
        
        return $result !== false;
    }
    
    return false;
}

// Load gallery data from DB
if ($pdo) {
    $gallery = loadGalleryFromDB($pdo);
} else {
    $gallery = [];
    $error_message = "❌ Невозможно загрузить данные галереи из-за ошибки подключения к базе данных.";
}


// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $error_message = 'Недействительный запрос (CSRF). Обновите страницу и попробуйте снова.';
    } elseif (isset($_POST['action'])) {
        $updated = false;
        
        if ($_POST['action'] === 'upload') {
            $upload_dir = IMAGES_DIR . '/gallery/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            if (!is_writable($upload_dir)) {
                $error_message = "❌ Папка галереи недоступна для записи";
            } else {
                $validation_errors = validateGalleryFields(
                    $_POST['alt_uk'] ?? '',
                    $_POST['alt_ru'] ?? '',
                    $_POST['title_uk'] ?? '',
                    $_POST['title_ru'] ?? ''
                );
                
                if (!empty($validation_errors)) {
                    $error_message = "❌ Ошибки валидации:<br>" . implode("<br>", $validation_errors);
                } elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $tmp = $_FILES['image']['tmp_name'];
                    $image_info = @getimagesize($tmp);
                    if ($image_info === false) {
                        $error_message = "❌ Файл не является изображением или повреждён.";
                    } else {
                        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
                        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                        if (!in_array($ext, $allowed_ext)) {
                            $error_message = "❌ Разрешены только JPG, PNG, GIF.";
                        } else {
                            // Удобные имена: foto1, foto2, foto3 ...
                            $next = 1;
                            foreach ($gallery as $item) {
                                if (preg_match('/^foto(\d+)\./i', $item['filename'], $m) && (int)$m[1] >= $next) {
                                    $next = (int)$m[1] + 1;
                                }
                            }
                            foreach (glob($upload_dir . 'foto*') ?: [] as $existing) {
                                if (preg_match('/foto(\d+)\./i', basename($existing), $m) && (int)$m[1] >= $next) {
                                    $next = (int)$m[1] + 1;
                                }
                            }
                            $filename = 'foto' . $next . '.' . $ext;
                            $filepath = $upload_dir . $filename;
                            if (move_uploaded_file($tmp, $filepath)) {
                                $stmt = $pdo->prepare("INSERT INTO gallery (image, alt_uk, alt_ru, title_uk, title_ru, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
                                $result = $stmt->execute([
                                    $filename,
                                    trim($_POST['alt_uk']),
                                    trim($_POST['alt_ru']),
                                    trim($_POST['title_uk']),
                                    trim($_POST['title_ru']),
                                    count($gallery) + 1
                                ]);
                                if ($result) {
                                    $gallery = loadGalleryFromDB($pdo);
                                    $updated = true;
                                    writeLog('Загрузка изображения', 'Загружено изображение: ' . $filename . ' - ' . trim($_POST['title_uk']), 'success');
                                } else {
                                    $error_message = "❌ Ошибка сохранения в базу данных";
                                }
                            } else {
                                $error_message = "❌ Не удалось загрузить изображение. Проверьте права доступа.";
                                writeLog('Загрузка изображения', 'Ошибка загрузки изображения: ' . $filename, 'error');
                            }
                        }
                    }
                } else {
                    $error_message = "❌ Ошибка загрузки файла или файл не выбран.";
                    writeLog('Загрузка изображения', 'Ошибка: файл не выбран или ошибка загрузки', 'error');
                }
            }
        } elseif ($_POST['action'] === 'edit') {
            $index = $_POST['index'];
            
            // Validate fields
            $validation_errors = validateGalleryFields(
                $_POST['alt_uk'] ?? '',
                $_POST['alt_ru'] ?? '',
                $_POST['title_uk'] ?? '',
                $_POST['title_ru'] ?? ''
            );
            
            if (!empty($validation_errors)) {
                $error_message = "❌ Ошибки валидации:<br>" . implode("<br>", $validation_errors);
            } elseif (isset($gallery[$index])) {
                $old_title = $gallery[$index]['title_uk'];
                
                // Обновляем в базе данных
                $stmt = $pdo->prepare("UPDATE gallery SET alt_uk = ?, alt_ru = ?, title_uk = ?, title_ru = ? WHERE id = ?");
                $stmt->execute([
                    trim($_POST['alt_uk']),
                    trim($_POST['alt_ru']),
                    trim($_POST['title_uk']),
                    trim($_POST['title_ru']),
                    $gallery[$index]['id']
                ]);
                
                // Перезагружаем данные из БД
                $gallery = loadGalleryFromDB($pdo);
                $updated = true;
                writeLog('Редактирование изображения', 'Изменено изображение: ' . $gallery[$index]['filename'] . ' - ' . $old_title . ' → ' . trim($_POST['title_uk']), 'success');
            } else {
                $error_message = "❌ Изображение не найдено.";
            }
        } elseif ($_POST['action'] === 'replace_image') {
            $index = $_POST['index'];
            $upload_dir = IMAGES_DIR . '/gallery/';
            
            if (isset($_FILES['new_image']) && $_FILES['new_image']['error'] === UPLOAD_ERR_OK) {
                $old_filename = $gallery[$index]['filename'];
                $new_filename_tmp = $_FILES['new_image']['tmp_name'];
                $file_extension = strtolower(pathinfo($_FILES['new_image']['name'], PATHINFO_EXTENSION));
                
                if (!in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $error_message = "❌ Неподдерживаемый формат файла. Разрешены только JPG, PNG, GIF.";
                } elseif (@getimagesize($new_filename_tmp) === false) {
                    $error_message = "❌ Файл не является изображением или повреждён.";
                } else {
                    $old_filepath = $upload_dir . $old_filename;
                    if (file_exists($old_filepath)) {
                        unlink($old_filepath);
                    }
                    if (move_uploaded_file($new_filename_tmp, $old_filepath)) {
                        // Принудительно обновляем время модификации файла для cache-busting
                        touch($old_filepath);
                        
                        $updated = true;
                        $success_message = "✅ Изображение успешно заменено! Сайт обновлен автоматически.";
                        writeLog('Замена изображения', 'Заменено изображение: ' . $old_filename . ' → ' . $old_filename, 'success');
                        // Автоматически обновляем сайт после замены изображения
                        $html_updated = updateIndexHtml($gallery);
                        
                        // Generate language JSON files
                        $json_generated = false;
                        if ($pdo && $html_updated) {
                            $json_generated = generateLangFiles($pdo);
                        }
                        
                        if ($html_updated) {
                            $success_message .= " Сайт обновлен!";
                            if ($json_generated) {
                                $success_message .= " JSON файлы локализации обновлены.";
                            }
                            writeLog('Замена изображения', 'Заменено изображение: ' . $old_filename . ' → ' . $old_filename . ' (БД + HTML обновлен' . ($json_generated ? ' + JSON файлы' : '') . ')', 'success');
                        } else {
                            $error_message = "⚠️ Изображение заменено, но не удалось обновить сайт.";
                            writeLog('Замена изображения', 'Изображение заменено, но ошибка обновления сайта', 'warning');
                        }
                    } else {
                        $error_message = "❌ Не удалось загрузить новое изображение.";
                    }
                }
            } else {
                $error_message = "❌ Ошибка загрузки файла.";
            }
        } elseif ($_POST['action'] === 'delete') {
            $index = $_POST['index'];
            $image = $gallery[$index];
            $filepath = IMAGES_DIR . '/gallery/' . $image['filename'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            
            // Удаляем из базы данных
            $stmt = $pdo->prepare("DELETE FROM gallery WHERE id = ?");
            $stmt->execute([$image['id']]);
            
            // Перезагружаем данные из БД
            $gallery = loadGalleryFromDB($pdo);
            $updated = true;
            writeLog('Удаление изображения', 'Удалено изображение: ' . $image['filename'] . ' - ' . $image['title_uk'], 'success');
        } elseif ($_POST['action'] === 'update_site') {
            // Force update the website with current gallery
            $html_updated = updateIndexHtml($gallery);
            $json_generated = false;
            if ($pdo && $html_updated) {
                $json_generated = generateLangFiles($pdo);
            }
            
            if ($html_updated) {
                $success_message = "✅ Сайт успешно обновлен!";
                if ($json_generated) {
                    $success_message .= " JSON файлы локализации обновлены.";
                }
                writeLog('Обновление сайта', 'Принудительное обновление галереи на сайте' . ($json_generated ? ' + JSON файлы' : ''), 'success');
                if (is_file(__DIR__ . '/sitemap_helper.php')) {
                    require_once __DIR__ . '/sitemap_helper.php';
                    updateSitemapLastmod();
                }
            } else {
                $error_message = "❌ Не удалось обновить сайт. Проверьте права доступа к index.html";
                writeLog('Обновление сайта', 'Ошибка принудительного обновления галереи на сайте', 'error');
            }
            if (isset($success_message)) $_SESSION['flash_success'] = $success_message;
            if (isset($error_message)) $_SESSION['flash_error'] = $error_message;
            header('Location: gallery.php');
            exit;
        }
        
        if ($updated) {
            // Автоматически обновляем сайт после всех изменений
            $html_updated = updateIndexHtml($gallery);
            
            // Generate language JSON files
            $json_generated = false;
            if ($pdo && $html_updated) {
                $json_generated = generateLangFiles($pdo);
            }
            
            if ($html_updated) {
                $success_message = "✅ Изменения сохранены в базе данных и сайт обновлен автоматически!";
                if ($json_generated) {
                    $success_message .= " JSON файлы локализации обновлены.";
                }
                writeLog('Сохранение галереи', 'Галерея сохранена в БД и обновлена на сайте' . ($json_generated ? ' + JSON файлы' : ''), 'success');
                if (is_file(__DIR__ . '/sitemap_helper.php')) {
                    require_once __DIR__ . '/sitemap_helper.php';
                    updateSitemapLastmod();
                }
            } else {
                $success_message = "✅ Изменения сохранены в базе данных! Сайт обновлен автоматически.";
                writeLog('Сохранение галереи', 'Галерея сохранена в БД, но ошибка обновления сайта', 'warning');
            }
            $_SESSION['flash_success'] = $success_message;
            header('Location: gallery.php');
            exit;
        }
    }
}

if (isset($_SESSION['flash_success'])) {
    $success_message = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $error_message = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
?>

<?php $page_title = '🖼️ Управление галереей'; $show_back = true; include __DIR__ . '/header.php'; ?>
            <h2>🖼️ Управление галереей</h2>
            
            <?php if (isset($success_message)): ?>
                <div class="success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <div class="info-box">
                <button class="info-box-toggle collapsed" onclick="toggleInfoBox()">
                    <span class="toggle-icon">▶</span> ℹ️ Информация о галерее
                </button>
                <div class="info-box-content collapsed" id="infoBoxContent">
                    <strong>ℹ️ Информация:</strong> Данные загружаются исключительно из базы данных. 
                    Все изменения сохраняются в MySQL и автоматически синхронизируются с сайтом.
                    <br><strong>💡 Подсказка:</strong> Оба языка отображаются с метками "(UA)" и "(RU)" для удобства редактирования.
                    <br><strong>💡 Подсказка:</strong> После изменения галереи нажмите кнопку "🔄 Обновить сайт" для применения изменений на сайте.
                    <br><strong>🖼️ Функция замены:</strong> При замене изображения старое имя файла сохраняется. Изображение сразу отображается в админке, а на сайте - после нажатия "🔄 Обновить сайт".
                    <br><strong>🔍 Alt-атрибуты:</strong> Теперь поддерживают два языка для лучшего SEO.
                    <br><strong>🔄 Кэширование:</strong> В админке изображения отображаются с параметром версии для предотвращения кэширования.
                    <br><strong>🌐 Сайт:</strong> При обновлении сайта изображения получают новый параметр версии, что решает проблему кэширования.
                    <br><strong>✅ Валидация:</strong> Все поля обязательны для заполнения с минимальной и максимальной длиной.
                    <br><strong>🗄️ База данных:</strong> Все данные хранятся в MySQL с автоматическим резервным копированием.
                </div>
            </div>
            
            <h3>📤 Загрузить новое изображение</h3>
            
            <form method="POST" enctype="multipart/form-data">
                <?php echo getCsrfField(); ?>
                <input type="hidden" name="action" value="upload">
                <div class="form-group">
                    <label>Выберите изображение: <span style="color: red;">*</span></label>
                    <input type="file" name="image" accept="image/*" required id="uploadImage">
                    <small style="color: #666;">Поддерживаемые форматы: JPG, PNG, GIF</small>
                </div>
                <div class="form-group">
                    <label>Alt текст (UA): <span style="color: red;">*</span></label>
                    <input type="text" name="alt_uk" id="uploadAltUk" placeholder="Встановлення тонувальної плівки на вікна офісної будівлі в Харкові" required minlength="10" maxlength="200">
                    <small style="color: #666;">Минимум 10 символов, максимум 200 символов</small>
                    <div id="altUkError" style="color: red; font-size: 12px;"></div>
                </div>
                <div class="form-group">
                    <label>Alt текст (RU): <span style="color: red;">*</span></label>
                    <input type="text" name="alt_ru" id="uploadAltRu" placeholder="Установка тонировочной пленки на офисном здании" required minlength="10" maxlength="200">
                    <small style="color: #666;">Минимум 10 символов, максимум 200 символов</small>
                    <div id="altRuError" style="color: red; font-size: 12px;"></div>
                </div>
                <div class="form-group">
                    <label>Название (UA): <span style="color: red;">*</span></label>
                    <input type="text" name="title_uk" id="uploadTitleUk" placeholder="Встановлення тонувальної плівки на офісній будівлі" required minlength="5" maxlength="100">
                    <small style="color: #666;">Минимум 5 символов, максимум 100 символов</small>
                    <div id="titleUkError" style="color: red; font-size: 12px;"></div>
                </div>
                <div class="form-group">
                    <label>Название (RU): <span style="color: red;">*</span></label>
                    <input type="text" name="title_ru" id="uploadTitleRu" placeholder="Установка тонировочной пленки на офисном здании" required minlength="5" maxlength="100">
                    <small style="color: #666;">Минимум 5 символов, максимум 100 символов</small>
                    <div id="titleRuError" style="color: red; font-size: 12px;"></div>
                </div>
                <button type="submit">Загрузить изображение</button>
            </form>
            
            <h3>📋 Существующие изображения</h3>
            <?php if (empty($gallery)): ?>
                <p>Изображения не найдены. Загрузите первое изображение выше.</p>
            <?php else: ?>
                <div style="margin-bottom: 20px;">
                    <form method="POST" style="display: inline;">
                        <?php echo getCsrfField(); ?>
                        <input type="hidden" name="action" value="update_site">
                        <button type="submit" style="background: #17a2b8; color: white; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer;">🔄 Обновить сайт</button>
                    </form>
                    <small style="color: #666; margin-left: 10px;">Принудительно обновить галерею на сайте</small>
                </div>
                
                <div class="gallery-grid">
                    <?php foreach ($gallery as $index => $image): ?>
                        <div class="gallery-item">
                            <?php 
                            $image_path = IMAGES_DIR . '/gallery/' . $image['filename'];
                            $cache_buster = file_exists($image_path) ? filemtime($image_path) : time();
                            ?>
                            <img src="../images/gallery/<?php echo htmlspecialchars($image['filename']); ?>?v=<?php echo $cache_buster; ?>" alt="<?php echo htmlspecialchars($image['alt_uk']); ?>" data-alt-uk="<?php echo htmlspecialchars($image['alt_uk']); ?>" data-alt-ru="<?php echo htmlspecialchars($image['alt_ru']); ?>" onclick="openModal(this)" style="max-width: 200px; max-height: 200px; object-fit: cover;">
                            <p><strong>Название (UA):</strong> <?php echo htmlspecialchars($image['title_uk']); ?></p>
                            <p><strong>Название (RU):</strong> <?php echo htmlspecialchars($image['title_ru']); ?></p>
                            <p><strong>Alt текст (UA):</strong> <?php echo htmlspecialchars($image['alt_uk']); ?></p>
                            <p><strong>Alt текст (RU):</strong> <?php echo htmlspecialchars($image['alt_ru']); ?></p>
                            <div class="btn-group">
                                <button class="btn-edit" onclick="editImage(<?php echo $index; ?>, '<?php echo htmlspecialchars($image['alt_uk']); ?>', '<?php echo htmlspecialchars($image['alt_ru']); ?>', '<?php echo htmlspecialchars($image['title_uk']); ?>', '<?php echo htmlspecialchars($image['title_ru']); ?>')">✏️ Редактировать</button>
                                <button class="btn-replace" onclick="replaceImage(<?php echo $index; ?>)">🖼️ Заменить изображение</button>
                                <form method="POST" style="display: inline;">
                                    <?php echo getCsrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="index" value="<?php echo $index; ?>">
                                    <button type="submit" class="btn-delete" onclick="return confirm('Удалить это изображение?')">🗑️ Удалить</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3>✏️ Редактировать изображение</h3>
            <form method="POST" onsubmit="return validateEditForm()">
                <?php echo getCsrfField(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="index" id="editIndex">
                <div class="form-group">
                    <label>Alt текст (UA): <span style="color: red;">*</span></label>
                    <input type="text" name="alt_uk" id="editAltUk" placeholder="Встановлення тонувальної плівки на вікна офісної будівлі в Харкові" required minlength="10" maxlength="200">
                    <small style="color: #666;">Минимум 10 символов, максимум 200 символов</small>
                    <div id="editAltUkError" style="color: red; font-size: 12px;"></div>
                </div>
                <div class="form-group">
                    <label>Alt текст (RU): <span style="color: red;">*</span></label>
                    <input type="text" name="alt_ru" id="editAltRu" placeholder="Установка тонировочной пленки на офисном здании" required minlength="10" maxlength="200">
                    <small style="color: #666;">Минимум 10 символов, максимум 200 символов</small>
                    <div id="editAltRuError" style="color: red; font-size: 12px;"></div>
                </div>
                <div class="form-group">
                    <label>Название (UA): <span style="color: red;">*</span></label>
                    <input type="text" name="title_uk" id="editTitleUk" placeholder="Встановлення тонувальної плівки на офісній будівлі" required minlength="5" maxlength="100">
                    <small style="color: #666;">Минимум 5 символов, максимум 100 символов</small>
                    <div id="editTitleUkError" style="color: red; font-size: 12px;"></div>
                </div>
                <div class="form-group">
                    <label>Название (RU): <span style="color: red;">*</span></label>
                    <input type="text" name="title_ru" id="editTitleRu" placeholder="Установка тонировочной пленки на офисном здании" required minlength="5" maxlength="100">
                    <small style="color: #666;">Минимум 5 символов, максимум 100 символов</small>
                    <div id="editTitleRuError" style="color: red; font-size: 12px;"></div>
                </div>
                <button type="submit">Сохранить изменения</button>
            </form>
        </div>
    </div>

    <!-- Replace Image Modal -->
    <div id="replaceModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeReplaceModal()">&times;</span>
            <h3>🖼️ Заменить изображение</h3>
            <form method="POST" enctype="multipart/form-data">
                <?php echo getCsrfField(); ?>
                <input type="hidden" name="action" value="replace_image">
                <input type="hidden" name="index" id="replaceIndex">
                <div class="form-group">
                    <label>Выберите новое изображение:</label>
                    <input type="file" name="new_image" accept="image/*" required>
                </div>
                <div class="form-group">
                    <p><strong>ℹ️ Как это работает:</strong></p>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>✅ Новое изображение загрузится на сервер</li>
                        <li>✅ Старое изображение будет удалено</li>
                        <li>✅ Новое изображение получит старое имя файла</li>
                        <li>✅ Все метаданные (названия, alt-текст) сохранятся</li>
                        <li>✅ Сайт автоматически обновится</li>
                    </ul>
                    <p><strong>⚠️ Внимание:</strong> Старое изображение будет полностью удалено!</p>
                </div>
                <button type="submit" class="btn-replace">Заменить изображение</button>
            </form>
        </div>
    </div>

    <script>
        function validateUploadForm() {
            console.log('validateUploadForm called');
            const altUkInput = document.getElementById('uploadAltUk');
            const altRuInput = document.getElementById('uploadAltRu');
            const titleUkInput = document.getElementById('uploadTitleUk');
            const titleRuInput = document.getElementById('uploadTitleRu');
            const uploadImageInput = document.getElementById('uploadImage');

            const altUkError = document.getElementById('altUkError');
            const altRuError = document.getElementById('altRuError');
            const titleUkError = document.getElementById('titleUkError');
            const titleRuError = document.getElementById('titleRuError');
            const imageError = document.getElementById('imageError');

            let hasError = false;
            
            console.log('Values:', {
                altUk: altUkInput.value,
                altRu: altRuInput.value,
                titleUk: titleUkInput.value,
                titleRu: titleRuInput.value,
                image: uploadImageInput.files.length
            });

            if (altUkInput.value.trim() === '') {
                altUkError.textContent = 'Alt текст (UA) не может быть пустым';
                hasError = true;
            } else {
                altUkError.textContent = '';
            }
            if (altRuInput.value.trim() === '') {
                altRuError.textContent = 'Alt текст (RU) не может быть пустым';
                hasError = true;
            } else {
                altRuError.textContent = '';
            }
            if (titleUkInput.value.trim() === '') {
                titleUkError.textContent = 'Название (UA) не может быть пустым';
                hasError = true;
            } else {
                titleUkError.textContent = '';
            }
            if (titleRuInput.value.trim() === '') {
                titleRuError.textContent = 'Название (RU) не может быть пустым';
                hasError = true;
            } else {
                titleRuError.textContent = '';
            }

            if (uploadImageInput.files.length === 0) {
                imageError.textContent = 'Выберите изображение для загрузки';
                hasError = true;
            } else {
                imageError.textContent = '';
            }

            console.log('Validation result:', !hasError);
            return !hasError;
        }

        function validateEditForm() {
            const altUkInput = document.getElementById('editAltUk');
            const altRuInput = document.getElementById('editAltRu');
            const titleUkInput = document.getElementById('editTitleUk');
            const titleRuInput = document.getElementById('editTitleRu');

            const altUkError = document.getElementById('editAltUkError');
            const altRuError = document.getElementById('editAltRuError');
            const titleUkError = document.getElementById('editTitleUkError');
            const titleRuError = document.getElementById('editTitleRuError');

            let hasError = false;

            if (altUkInput.value.trim() === '') {
                altUkError.textContent = 'Alt текст (UA) не может быть пустым';
                hasError = true;
            } else {
                altUkError.textContent = '';
            }
            if (altRuInput.value.trim() === '') {
                altRuError.textContent = 'Alt текст (RU) не может быть пустым';
                hasError = true;
            } else {
                altRuError.textContent = '';
            }
            if (titleUkInput.value.trim() === '') {
                titleUkError.textContent = 'Название (UA) не может быть пустым';
                hasError = true;
            } else {
                titleUkError.textContent = '';
            }
            if (titleRuInput.value.trim() === '') {
                titleRuError.textContent = 'Название (RU) не может быть пустым';
                hasError = true;
            } else {
                titleRuError.textContent = '';
            }

            return !hasError;
        }

        function editImage(index, altUk, altRu, titleUk, titleRu) {
            document.getElementById('editIndex').value = index;
            document.getElementById('editAltUk').value = altUk;
            document.getElementById('editAltRu').value = altRu;
            document.getElementById('editTitleUk').value = titleUk;
            document.getElementById('editTitleRu').value = titleRu;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function replaceImage(index) {
            document.getElementById('replaceIndex').value = index;
            document.getElementById('replaceModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function closeReplaceModal() {
            document.getElementById('replaceModal').style.display = 'none';
        }
        
        function toggleInfoBox() {
            const infoBoxContent = document.getElementById('infoBoxContent');
            infoBoxContent.classList.toggle('collapsed');
            const toggleIcon = document.querySelector('.info-box-toggle .toggle-icon');
            if (toggleIcon) {
                toggleIcon.textContent = infoBoxContent.classList.contains('collapsed') ? '▶' : '▼';
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
    </script>
<?php include __DIR__ . '/footer.php'; ?> 