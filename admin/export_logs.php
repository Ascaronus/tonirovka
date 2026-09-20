<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

// Включаем функции логирования и конфиг (logging_functions подключает config)
include __DIR__ . '/logging_functions.php';
require_once __DIR__ . '/config.php';

// Обработка запроса экспорта
if (isset($_GET['format']) && isset($_GET['action']) && $_GET['action'] === 'export') {
    $format = $_GET['format'];
    $filter = $_GET['filter'] ?? '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10000; // По умолчанию все логи
    
    // Получаем логи
    $logs = getLogs($limit, $filter);
    
    if (empty($logs)) {
        echo "<h1>📋 Экспорт логов</h1>";
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px;'>";
        echo "❌ Логи не найдены для экспорта";
        echo "</div>";
        echo "<p><a href='logs.php'>← Назад к логам</a></p>";
        exit;
    }
    
    // Функция для очистки текста для CSV
    function cleanForCSV($text) {
        return str_replace(['"', "\n", "\r"], ['""', ' ', ' '], $text);
    }
    
    // Функция для форматирования деталей
    function formatDetails($details) {
        if (is_array($details)) {
            return json_encode($details, JSON_UNESCAPED_UNICODE);
        }
        return $details;
    }
    
    switch ($format) {
        case 'csv':
            // Экспорт в CSV
            header('Content-Type: text/csv; charset=windows-1251');
            header('Content-Disposition: attachment; filename="admin_logs_' . date('Y-m-d_H-i-s') . '.csv"');
            
            // Конвертируем в Windows-1251 для корректного отображения в Excel на Windows
            function convertToWindows1251($text) {
                return iconv('UTF-8', 'Windows-1251//IGNORE', $text);
            }
            
            // Заголовки CSV
            echo convertToWindows1251("Время,Действие,Детали,Статус,IP,Детали ошибки,Детали предупреждения,Детали успеха,Использование памяти (KB)\n");
            
            foreach ($logs as $log) {
                $timestamp = convertToWindows1251(cleanForCSV($log['timestamp']));
                $action = convertToWindows1251(cleanForCSV($log['action']));
                $details = convertToWindows1251(cleanForCSV($log['details']));
                $status = convertToWindows1251(cleanForCSV($log['status']));
                $ip = convertToWindows1251(cleanForCSV($log['ip']));
                $error_details = convertToWindows1251(cleanForCSV(formatDetails($log['error_details'])));
                $warning_details = convertToWindows1251(cleanForCSV(formatDetails($log['warning_details'])));
                $success_details = convertToWindows1251(cleanForCSV(formatDetails($log['success_details'])));
                $memory = $log['memory_usage'] ? round($log['memory_usage'] / 1024, 2) : '';
                
                echo "\"$timestamp\",\"$action\",\"$details\",\"$status\",\"$ip\",\"$error_details\",\"$warning_details\",\"$success_details\",\"$memory\"\n";
            }
            break;
            
        case 'json':
            // Экспорт в JSON
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="admin_logs_' . date('Y-m-d_H-i-s') . '.json"');
            
            $export_data = [
                'export_info' => [
                    'exported_at' => date('Y-m-d H:i:s'),
                    'total_records' => count($logs),
                    'filter_applied' => $filter,
                    'limit_applied' => $limit
                ],
                'logs' => $logs
            ];
            
            echo json_encode($export_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
            
        case 'txt':
            // Экспорт в текстовый формат
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="admin_logs_' . date('Y-m-d_H-i-s') . '.txt"');
            
            echo "ЭКСПОРТ ЛОГОВ АДМИНИСТРАТОРА\n";
            echo "Дата экспорта: " . date('Y-m-d H:i:s') . "\n";
            echo "Всего записей: " . count($logs) . "\n";
            if ($filter) echo "Фильтр: $filter\n";
            echo "Ограничение: $limit\n";
            echo str_repeat("=", 80) . "\n\n";
            
            foreach ($logs as $log) {
                echo "[" . $log['timestamp'] . "] [" . strtoupper($log['status']) . "] " . $log['action'] . "\n";
                echo "IP: " . $log['ip'] . "\n";
                echo "Детали: " . $log['details'] . "\n";
                
                if ($log['error_details']) {
                    echo "🔍 Детали ошибки: " . formatDetails($log['error_details']) . "\n";
                }
                if ($log['warning_details']) {
                    echo "⚠️ Детали предупреждения: " . formatDetails($log['warning_details']) . "\n";
                }
                if ($log['success_details']) {
                    echo "✅ Детали успеха: " . formatDetails($log['success_details']) . "\n";
                }
                if ($log['memory_usage']) {
                    echo "💾 Память: " . round($log['memory_usage'] / 1024, 2) . " KB\n";
                }
                echo str_repeat("-", 80) . "\n\n";
            }
            break;
            
        default:
            echo "<h1>📋 Экспорт логов</h1>";
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px;'>";
            echo "❌ Неподдерживаемый формат экспорта";
            echo "</div>";
            echo "<p><a href='logs.php'>← Назад к логам</a></p>";
            break;
    }
    exit;
}

// Интерфейс для экспорта
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Экспорт логов - Админ-панель</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
        .header { background: #333; color: white; padding: 20px; }
        .header h1 { margin: 0; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .nav { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .nav a { display: inline-block; margin: 5px; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
        .nav a:hover { background: #0056b3; }
        
        .export-form { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; }
        .form-group textarea { height: 100px; resize: vertical; }
        
        .format-options { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .format-option { border: 2px solid #ddd; border-radius: 10px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.3s; min-height: 120px; display: flex; flex-direction: column; justify-content: center; }
        .format-option:hover { border-color: #007bff; background: #f8f9fa; }
        .format-option.selected { border-color: #007bff; background: #e3f2fd; }
        .format-option input[type="radio"] { display: none; }
        .format-icon { font-size: 2em; margin-bottom: 10px; }
        .format-title { font-weight: bold; margin-bottom: 5px; }
        .format-description { font-size: 0.9em; color: #666; }
        
        .export-button { background: #28a745; color: white; border: none; padding: 15px 30px; border-radius: 5px; cursor: pointer; font-size: 16px; width: 100%; }
        .export-button:hover { background: #218838; }
        .export-button:disabled { background: #6c757d; cursor: not-allowed; }
        
        .info-box { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .warning-box { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>📤 Экспорт логов</h1>
        <a href="logs.php" style="float: right; color: white; text-decoration: none;">← Назад к логам</a>
    </div>
    
    <div class="container">
        <div class="nav">
            <a href="index.php">📊 Главная</a>
            <a href="prices.php">💰 Цены</a>
            <a href="gallery.php">🖼️ Галерея</a>
            <a href="films.php">🎨 Пленки</a>
            <a href="content.php">📝 Контент</a>
            <a href="settings.php">⚙️ Настройки</a>
            <a href="logs.php">📋 Логи</a>
        </div>
        
        <div class="export-form">
            <h2>📤 Выберите формат экспорта</h2>
            
            <div class="info-box">
                <strong>ℹ️ Информация:</strong> Экспорт будет включать все логи с учетом фильтров. 
                Большие файлы могут занять некоторое время для обработки.
            </div>
            
            <form method="GET" id="exportForm">
                <input type="hidden" name="action" value="export">
                
                <div class="format-options">
                    <label class="format-option" onclick="selectFormat('csv')">
                        <input type="radio" name="format" value="csv" checked>
                        <div class="format-icon">📊</div>
                        <div class="format-title">CSV (Excel)</div>
                        <div class="format-description">Для анализа в Excel или Google Sheets</div>
                    </label>
                    
                    <label class="format-option" onclick="selectFormat('json')">
                        <input type="radio" name="format" value="json">
                        <div class="format-icon">🔧</div>
                        <div class="format-title">JSON</div>
                        <div class="format-description">Для программной обработки</div>
                    </label>
                    
                    <label class="format-option" onclick="selectFormat('txt')">
                        <input type="radio" name="format" value="txt">
                        <div class="format-icon">📄</div>
                        <div class="format-title">TXT</div>
                        <div class="format-description">Читаемый текстовый формат</div>
                    </label>
                </div>
                
                <div class="form-group">
                    <label for="filter">Фильтр по действию (необязательно):</label>
                    <input type="text" id="filter" name="filter" placeholder="Например: settings, error, warning...">
                </div>
                
                <div class="form-group">
                    <label for="limit">Количество записей:</label>
                    <select id="limit" name="limit">
                        <option value="100">Последние 100</option>
                        <option value="500">Последние 500</option>
                        <option value="1000">Последние 1000</option>
                        <option value="5000">Последние 5000</option>
                        <option value="10000" selected>Все логи</option>
                    </select>
                </div>
                
                <div class="warning-box">
                    <strong>⚠️ Внимание:</strong> Экспорт всех логов может создать большой файл. 
                    Рекомендуется использовать фильтры для ограничения объема данных.
                </div>
                
                <button type="submit" class="export-button" id="exportBtn">
                    📤 Экспортировать логи
                </button>
            </form>
        </div>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="logs.php" style="color: #666; text-decoration: none;">← Вернуться к логам</a>
        </div>
    </div>
    
    <script>
        function selectFormat(format) {
            // Убираем выделение со всех опций
            document.querySelectorAll('.format-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Выделяем выбранную опцию
            event.currentTarget.classList.add('selected');
            
            // Устанавливаем значение radio button
            document.querySelector(`input[value="${format}"]`).checked = true;
        }
        
        // Инициализация - выделяем первую опцию
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.format-option').classList.add('selected');
        });
        
        // Обработка отправки формы
        document.getElementById('exportForm').addEventListener('submit', function(e) {
            const exportBtn = document.getElementById('exportBtn');
            exportBtn.disabled = true;
            exportBtn.textContent = '⏳ Подготовка файла...';
            
            // Показываем пользователю, что процесс начался
            setTimeout(() => {
                exportBtn.textContent = '📤 Экспорт завершен';
            }, 1000);
        });
    </script>
</body>
</html> 