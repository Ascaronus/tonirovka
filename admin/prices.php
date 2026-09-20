<?php
require_once __DIR__ . '/bootstrap.php';
include __DIR__ . '/lang_functions.php';
require_once __DIR__ . '/sitemap_helper.php';

$pdo = getDBConnection();
if (!$pdo) {
    $error_message = "❌ Ошибка подключения к базе данных";
    writeLog('db_error', "Ошибка подключения к БД в prices.php", 'error');
}

// Функция для загрузки цен из БД
function loadPricesFromDB($pdo) {
    $stmt = $pdo->query("SELECT * FROM prices ORDER BY sort_order");
    $prices = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $prices[] = [
            'id' => $row['id'],
            'name_uk' => $row['service_uk'] ?? '',
            'name_ru' => $row['service_ru'] ?? '',
            'price' => $row['price_uk'] ?? '' // Используем только price_uk для цены
        ];
    }
    return $prices;
}

$prices_file = DATA_DIR . '/prices.json';



// Function to update index.html with new prices
function updateIndexHtml($prices) {
    $html_content = file_get_contents(INDEX_HTML_PATH);
    if (!$html_content) return false;
    
    // Find the pricing table section
    $pricing_pattern = '/(<tbody>\s*)(.*?)(\s*<\/tbody>\s*<\/table>)/s';
    
    if (preg_match($pricing_pattern, $html_content, $matches)) {
        $tbody_start = $matches[1];
        $tbody_end = $matches[3];
        
        // Build new tbody content
        $new_tbody = '';
        foreach ($prices as $price) {
            $new_tbody .= "                    <tr>\n";
            $new_tbody .= "                        <td data-lang-uk=\"" . htmlspecialchars($price['name_uk']) . "\" data-lang-ru=\"" . htmlspecialchars($price['name_ru']) . "\">" . htmlspecialchars($price['name_uk']) . "</td>\n";
            $new_tbody .= "                        <td class=\"price\">" . htmlspecialchars($price['price']) . "</td>\n";
            $new_tbody .= "                    </tr>\n";
        }
        
        // Replace the tbody content
        $new_html = preg_replace($pricing_pattern, $tbody_start . $new_tbody . $tbody_end, $html_content);
        
        // Save the updated HTML
        return file_put_contents(INDEX_HTML_PATH, $new_html) !== false;
    }
    
    return false;
}

// Load prices from database
if ($pdo) {
    $prices = loadPricesFromDB($pdo);
} else {
    $prices = [];
    $error_message = "❌ Невозможно загрузить данные цен из-за ошибки подключения к базе данных.";
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $error_message = 'Недействительный запрос (CSRF). Обновите страницу и попробуйте снова.';
    } elseif (isset($_POST['action'])) {
        $updated = false;
        
        if ($_POST['action'] === 'add') {
            // Сохраняем в базу данных
            $stmt = $pdo->prepare("INSERT INTO prices (service_uk, service_ru, price_uk, price_ru, sort_order) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['name_uk'],
                $_POST['name_ru'],
                $_POST['price'],
                '', // Оставляем price_ru пустым, так как это не цена на другом языке
                count($prices) + 1
            ]);
            
            // Перезагружаем данные из БД
            $prices = loadPricesFromDB($pdo);
            $updated = true;
            writeLog('Добавление цены', 'Добавлена новая цена: ' . $_POST['name_uk'] . ' - ' . $_POST['price'], 'success');
        } elseif ($_POST['action'] === 'edit') {
            $index = $_POST['index'];
            $old_price = $prices[$index] ?? [];
            
            // Обновляем в базе данных
            $stmt = $pdo->prepare("UPDATE prices SET service_uk = ?, service_ru = ?, price_uk = ?, price_ru = ? WHERE id = ?");
            $stmt->execute([
                $_POST['name_uk'],
                $_POST['name_ru'],
                $_POST['price'],
                '', // Оставляем price_ru пустым, так как это не цена на другом языке
                $prices[$index]['id']
            ]);
            
            // Перезагружаем данные из БД
            $prices = loadPricesFromDB($pdo);
            $updated = true;
            writeLog('Редактирование цены', 'Изменена цена: ' . ($old_price['name_uk'] ?? 'неизвестно') . ' → ' . $_POST['name_uk'] . ' - ' . $_POST['price'], 'success');
        } elseif ($_POST['action'] === 'delete') {
            $index = $_POST['index'];
            $deleted_price = $prices[$index] ?? [];
            
            // Удаляем из базы данных
            $stmt = $pdo->prepare("DELETE FROM prices WHERE id = ?");
            $stmt->execute([$prices[$index]['id']]);
            
            // Перезагружаем данные из БД
            $prices = loadPricesFromDB($pdo);
            $updated = true;
            writeLog('Удаление цены', 'Удалена цена: ' . ($deleted_price['name_uk'] ?? 'неизвестно') . ' - ' . ($deleted_price['price'] ?? 'неизвестно'), 'success');
        } elseif ($_POST['action'] === 'update_site') {
            // Force update the website with current prices
            $html_updated = updateIndexHtml($prices);
            $json_generated = false;
            if ($pdo && $html_updated) {
                $json_generated = generateLangFiles($pdo);
            }
            
            if ($html_updated) {
                updateSitemapLastmod();
                $success_message = "✅ Сайт успешно обновлен!";
                if ($json_generated) {
                    $success_message .= " JSON файлы локализации обновлены.";
                }
                writeLog('Обновление сайта', 'Принудительное обновление цен на сайте' . ($json_generated ? ' + JSON файлы' : ''), 'success');
            } else {
                $error_message = "❌ Не удалось обновить сайт. Проверьте права доступа к index.html";
                writeLog('Обновление сайта', 'Ошибка принудительного обновления цен на сайте', 'error');
            }
            header('Location: prices.php');
            exit;
        }
        
        if ($updated) {
            // Update index.html
            $html_updated = updateIndexHtml($prices);
            
            // Generate language JSON files
            $json_generated = false;
            if ($pdo && $html_updated) {
                $json_generated = generateLangFiles($pdo);
            }
            
            if ($html_updated) {
                updateSitemapLastmod();
                $success_message = "✅ Изменения сохранены в базе данных и применены на сайте!";
                if ($json_generated) {
                    $success_message .= " JSON файлы локализации обновлены.";
                }
                writeLog('Сохранение цен', 'Цены сохранены в БД и обновлены на сайте' . ($json_generated ? ' + JSON файлы' : ''), 'success');
            } else {
                $success_message = "✅ Изменения сохранены в базе данных!";
                writeLog('Сохранение цен', 'Цены сохранены в БД, но ошибка обновления сайта', 'warning');
            }
        }
        
        header('Location: prices.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление ценами - Админ-панель</title>
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
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="number"], textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; }
        textarea { height: 100px; resize: vertical; }
        button { background: #28a745; color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #218838; }
        .price-item { background: #f8f9fa; padding: 20px; margin-bottom: 15px; border-radius: 5px; border: 1px solid #dee2e6; }
        .price-item h3 { margin-top: 0; }
        .btn-group { margin-top: 15px; display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-edit { background: #007bff; color: white; padding: 8px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s ease; }
        .btn-edit:hover { background: #0056b3; transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn-delete { background: #dc3545; color: white; padding: 8px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s ease; }
        .btn-delete:hover { background: #c82333; transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .info-box { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .info-box-toggle { background: #17a2b8; color: white; padding: 8px 12px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; margin-bottom: 10px; }
        .info-box-toggle:hover { background: #138496; }
        .info-box-content { display: block; }
        .info-box-content.collapsed { display: none; }
        .info-box-toggle .toggle-icon { transition: transform 0.3s ease; }
        .info-box-toggle.collapsed .toggle-icon { transform: rotate(-90deg); }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: white; margin: 5% auto; padding: 20px; border-radius: 10px; width: 80%; max-width: 600px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: #000; }
        .modal button[type="submit"] { background: #28a745; color: white; padding: 10px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s ease; }
        .modal button[type="submit"]:hover { background: #218838; transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="header">
        <h1>💰 Управление ценами</h1>
        <a href="index.php" class="back">← Назад</a>
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
            <h2>💰 Управление ценами</h2>
            
            <?php if (isset($success_message)): ?>
                <div class="success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <div class="info-box">
                <button class="info-box-toggle collapsed" onclick="toggleInfoBox()">
                    <span class="toggle-icon">▶</span> ℹ️ Информация о ценах
                </button>
                <div class="info-box-content collapsed" id="infoBoxContent">
                    <strong>ℹ️ Информация:</strong> Данные загружаются исключительно из базы данных. 
                    Все изменения сохраняются в MySQL и автоматически синхронизируются с сайтом.
                    <br><strong>💡 Подсказка:</strong> После изменения цен они автоматически обновятся на сайте.
                    <br><strong>💡 Подсказка:</strong> Оба языка отображаются с метками "(UA)" и "(RU)" для удобства редактирования.
                    <br><strong>🗄️ База данных:</strong> Все данные хранятся в MySQL с автоматическим резервным копированием.
                </div>
            </div>
            
            <h3>➕ Добавить новую услугу</h3>
            <form method="POST">
                <?php echo getCsrfField(); ?>
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Название услуги (UA):</label>
                    <input type="text" name="name_uk" placeholder="Захисна плівка" required>
                </div>
                <div class="form-group">
                    <label>Название услуги (RU):</label>
                    <input type="text" name="name_ru" placeholder="Защитная пленка" required>
                </div>
                <div class="form-group">
                    <label>Цена:</label>
                    <input type="text" name="price" placeholder="от 600-800 ₴" required>
                </div>
                <button type="submit">Добавить услугу</button>
            </form>
            
            <h3>📋 Существующие услуги</h3>
            <?php if (empty($prices)): ?>
                <p>Услуги не найдены. Добавьте первую услугу выше.</p>
            <?php else: ?>
                <div style="margin-bottom: 20px;">
                    <form method="POST" style="display: inline;">
                        <?php echo getCsrfField(); ?>
                        <input type="hidden" name="action" value="update_site">
                        <button type="submit" style="background: #17a2b8; color: white; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer;">🔄 Обновить сайт</button>
                    </form>
                    <small style="color: #666; margin-left: 10px;">Принудительно обновить цены на сайте</small>
                </div>
                
                <?php foreach ($prices as $index => $price): ?>
                    <div class="price-item">
                        <p><strong>Название (UA):</strong> <?php echo htmlspecialchars($price['name_uk']); ?></p>
                        <p><strong>Название (RU):</strong> <?php echo htmlspecialchars($price['name_ru']); ?></p>
                        <p><strong>Цена:</strong> <?php echo htmlspecialchars($price['price']); ?></p>
                        <div class="btn-group">
                            <button class="btn-edit" onclick="editPrice(<?php echo $index; ?>, '<?php echo htmlspecialchars($price['name_uk']); ?>', '<?php echo htmlspecialchars($price['name_ru']); ?>', '<?php echo htmlspecialchars($price['price']); ?>')">✏️ Редактировать</button>
                            <form method="POST" style="display: inline;">
                                <?php echo getCsrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="index" value="<?php echo $index; ?>">
                                <button type="submit" class="btn-delete" onclick="return confirm('Удалить эту услугу?')">🗑️ Удалить</button>
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
            <h3>✏️ Редактировать услугу</h3>
            <form method="POST">
                <?php echo getCsrfField(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="index" id="editIndex">
                <div class="form-group">
                    <label>Название услуги (UA):</label>
                    <input type="text" name="name_uk" id="editNameUk" placeholder="Захисна плівка" required>
                </div>
                <div class="form-group">
                    <label>Название услуги (RU):</label>
                    <input type="text" name="name_ru" id="editNameRu" placeholder="Защитная пленка" required>
                </div>
                <div class="form-group">
                    <label>Цена:</label>
                    <input type="text" name="price" id="editPrice" placeholder="от 600-800 ₴" required>
                </div>
                <button type="submit">Сохранить изменения</button>
            </form>
        </div>
    </div>

    <script>
        function editPrice(index, nameUk, nameRu, price) {
            document.getElementById('editIndex').value = index;
            document.getElementById('editNameUk').value = nameUk;
            document.getElementById('editNameRu').value = nameRu;
            document.getElementById('editPrice').value = price;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function toggleInfoBox() {
            const infoBoxContent = document.getElementById('infoBoxContent');
            const toggleIcon = document.querySelector('.info-box-toggle .toggle-icon');
            
            if (infoBoxContent.classList.contains('collapsed')) {
                infoBoxContent.classList.remove('collapsed');
                toggleIcon.textContent = '▼'; // Change icon to down arrow
            } else {
                infoBoxContent.classList.add('collapsed');
                toggleIcon.textContent = '▶'; // Change icon to right arrow
            }
        }
        
        window.onclick = function(event) {
            var modal = document.getElementById('editModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html> 