<?php
require_once __DIR__ . '/bootstrap.php';

// Обработка фильтров
$filter = $_GET['filter'] ?? '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$action = $_GET['action'] ?? '';

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCsrf()) {
        $error = 'Недействительный запрос (CSRF). Обновите страницу и попробуйте снова.';
    } else {
    switch ($_POST['action']) {
        case 'clear_logs':
            try {
                $result = clearAllLogs();
                if ($result !== false) {
                    $success = "Логи успешно очищены! Удалено записей: $result";
                    writeSuccessLog('Очистка логов', 'Все логи администратора очищены', [
                        'operation_type' => 'clear_all_logs',
                        'records_deleted' => $result,
                        'deletion_success' => true,
                        'storage_type' => useDBLogs() ? 'database' : 'file'
                    ]);
                } else {
                    $error = 'Ошибка очистки логов!';
                    writeLog('Очистка логов', 'Ошибка очистки всех логов', 'error', [
                        'operation_type' => 'clear_all_logs',
                        'deletion_success' => false,
                        'error_type' => 'clear_failed',
                        'storage_type' => useDBLogs() ? 'database' : 'file',
                        'function_result' => $result
                    ]);
                }
            } catch (Exception $e) {
                $error = 'Ошибка очистки логов: ' . $e->getMessage();
                writeLog('Очистка логов', 'Исключение при очистке всех логов', 'error', [
                    'operation_type' => 'clear_all_logs',
                    'deletion_success' => false,
                    'error_type' => 'exception',
                    'exception_message' => $e->getMessage(),
                    'exception_file' => $e->getFile(),
                    'exception_line' => $e->getLine(),
                    'storage_type' => useDBLogs() ? 'database' : 'file'
                ]);
            }
            break;
            
        case 'clean_old_logs':
            $days = isset($_POST['days']) ? (int)$_POST['days'] : 30;
            try {
                $result = cleanOldLogs($days);
                if ($result !== false) {
                    $success = "Старые логи (старше {$days} дней) успешно удалены!";
                    writeSuccessLog('Очистка старых логов', "Удалены логи старше {$days} дней", [
                        'operation_type' => 'clean_old_logs',
                        'days_old' => $days,
                        'records_deleted' => $result,
                        'cleanup_success' => true
                    ]);
                } else {
                    $error = 'Ошибка очистки старых логов!';
                    writeLog('Очистка старых логов', 'Ошибка очистки старых логов', 'error', [
                        'operation_type' => 'clean_old_logs',
                        'days_old' => $days,
                        'cleanup_success' => false,
                        'error_type' => 'cleanup_failed',
                        'function_result' => $result
                    ]);
                }
            } catch (Exception $e) {
                $error = 'Ошибка очистки старых логов: ' . $e->getMessage();
                writeLog('Очистка старых логов', 'Исключение при очистке старых логов', 'error', [
                    'operation_type' => 'clean_old_logs',
                    'days_old' => $days,
                    'cleanup_success' => false,
                    'error_type' => 'exception',
                    'exception_message' => $e->getMessage(),
                    'exception_file' => $e->getFile(),
                    'exception_line' => $e->getLine()
                ]);
            }
            break;
    }
    }
}

// Получаем логи для отображения
$logs = getLogs($limit, $filter);

// Получаем все логи для статистики (без ограничений)
$all_logs = getLogs(10000, $filter); // Большое число для получения всех логов

// Статистика по всем логам
$total_logs = count($all_logs);
$success_count = 0;
$error_count = 0;
$warning_count = 0;

foreach ($all_logs as $log) {
    switch ($log['status']) {
        case 'success':
            $success_count++;
            break;
        case 'error':
            $error_count++;
            break;
        case 'warning':
            $warning_count++;
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Логи - Админ-панель</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
        .header { background: #333; color: white; padding: 20px; }
        .header h1 { margin: 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .nav { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
        .nav a { display: inline-block; padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; flex: 0 0 auto; white-space: nowrap; }
        .nav a:hover { background: #0056b3; }
        
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .stat-number { font-size: 2em; font-weight: bold; margin-bottom: 10px; }
        .stat-success { color: #28a745; }
        .stat-error { color: #dc3545; }
        .stat-warning { color: #ffc107; }
        
        .filters { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .filters form { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .filters input, .filters select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; }
        .filters button { background: #007bff; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; }
        .filters button:hover { background: #0056b3; }
        
        .actions { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .actions form { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .actions button { 
            background: #28a745; 
            color: white; 
            padding: 15px 25px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-size: 16px; 
            margin-top: 10px;
        }
        .actions button:hover { background: #218838; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        
        .logs-table { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .logs-table table { width: 100%; border-collapse: collapse; }
        .logs-table th, .logs-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .logs-table th { background: #f8f9fa; font-weight: bold; }
        .logs-table tr:hover { background: #f8f9fa; }
        
        .status-success { color: #28a745; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
        .status-warning { color: #ffc107; font-weight: bold; }
        
        .log-details { max-width: 300px; word-wrap: break-word; }
        .log-timestamp { font-size: 0.9em; color: #666; }
        .log-action { font-weight: bold; }
        
        .log-details-expanded { max-width: 600px; word-wrap: break-word; }
        .log-error-details { background: #f8d7da; color: #721c24; padding: 8px; border-radius: 3px; margin-top: 5px; font-size: 0.9em; }
        .log-warning-details { background: #fff3cd; color: #856404; padding: 8px; border-radius: 3px; margin-top: 5px; font-size: 0.9em; }
        .log-success-details { background: #d4edda; color: #155724; padding: 8px; border-radius: 3px; margin-top: 5px; font-size: 0.9em; }
        
        .expand-details { background: none; border: none; color: #007bff; cursor: pointer; text-decoration: underline; font-size: 0.9em; }
        .expand-details:hover { color: #0056b3; }
        
        .no-logs { text-align: center; padding: 40px; color: #666; }
        .back { float: right; color: white; text-decoration: none; }
        
        .details-hidden { display: none; }
        .details-visible { display: block; }
    </style>
</head>
<body>
    <div class="header">
        <h1>📋 Логи администратора</h1>
        <a href="index.php" class="back">← Назад</a>
    </div>
    
    <div class="container">
        <div class="nav">
            <?php include __DIR__ . '/nav.php'; ?>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Статистика -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_logs; ?></div>
                <div>Всего записей</div>
                <small style="color: #666;">Показано: <?php echo count($logs); ?> из <?php echo $total_logs; ?></small>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-success"><?php echo $success_count; ?></div>
                <div>Успешных</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-error"><?php echo $error_count; ?></div>
                <div>Ошибок</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-warning"><?php echo $warning_count; ?></div>
                <div>Предупреждений</div>
            </div>
        </div>
        
        <!-- Фильтры -->
        <div class="filters">
            <form method="GET">
                <input type="text" name="filter" placeholder="Фильтр по действию..." value="<?php echo htmlspecialchars($filter); ?>">
                <select name="limit">
                    <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50 записей</option>
                    <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100 записей</option>
                    <option value="200" <?php echo $limit == 200 ? 'selected' : ''; ?>>200 записей</option>
                    <option value="500" <?php echo $limit == 500 ? 'selected' : ''; ?>>500 записей</option>
                </select>
                <button type="submit">Применить</button>
                <a href="logs.php" style="margin-left: 10px; color: #666; text-decoration: none;">Сбросить</a>
            </form>
        </div>
        
        <!-- Действия -->
        <div class="actions">
            <form method="POST" style="display: inline;">
                <?php echo getCsrfField(); ?>
                <input type="hidden" name="action" value="clear_logs">
                <button type="submit" class="btn-danger" onclick="return confirm('Удалить ВСЕ логи? Это действие нельзя отменить!')">
                    🗑️ Очистить все логи
                </button>
            </form>
            
            <form method="POST" style="display: inline;">
                <?php echo getCsrfField(); ?>
                <input type="hidden" name="action" value="clean_old_logs">
                <select name="days" style="margin-left: 15px;">
                    <option value="7">7 дней</option>
                    <option value="14">14 дней</option>
                    <option value="30" selected>30 дней</option>
                    <option value="60">60 дней</option>
                    <option value="90">90 дней</option>
                </select>
                <button type="submit" class="btn-warning">
                    🧹 Удалить старые логи
                </button>
            </form>
            
            <a href="export_logs.php" style="margin-left: 15px; display: inline-block; background: #17a2b8; color: white; padding: 15px 25px; text-decoration: none; border-radius: 5px; font-size: 16px;">
                📤 Экспорт логов
            </a>
        </div>
        
        <!-- Таблица логов -->
        <div class="logs-table">
            <?php if (empty($logs)): ?>
                <div class="no-logs">
                    <h3>📝 Логи отсутствуют</h3>
                    <p>Пока нет записей в логах. Логи появятся после выполнения действий в админ-панели.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Время</th>
                            <th>Действие</th>
                            <th>Детали</th>
                            <th>Статус</th>
                            <th>IP</th>
                            <th>Дополнительно</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $index => $log): ?>
                            <tr>
                                <td class="log-timestamp"><?php echo htmlspecialchars($log['timestamp']); ?></td>
                                <td class="log-action"><?php echo htmlspecialchars($log['action']); ?></td>
                                <td class="log-details">
                                    <?php echo htmlspecialchars($log['details']); ?>
                                    <?php if ($log['error_details'] || $log['warning_details'] || $log['success_details']): ?>
                                        <button class="expand-details" onclick="toggleDetails(<?php echo $index; ?>)">
                                            🔍 Показать детали
                                        </button>
                                        <div id="details-<?php echo $index; ?>" class="details-hidden">
                                            <?php if ($log['error_details']): ?>
                                                <div class="log-error-details">
                                                    <strong>🔍 Детали ошибки:</strong><br>
                                                    <?php 
                                                    if (is_array($log['error_details'])) {
                                                        echo '<pre>' . htmlspecialchars(json_encode($log['error_details'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . '</pre>';
                                                    } else {
                                                        echo htmlspecialchars($log['error_details']);
                                                    }
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                                                                         <?php if ($log['warning_details']): ?>
                                                 <div class="log-warning-details">
                                                     <strong>⚠️ Детали предупреждения:</strong><br>
                                                     <?php 
                                                     if (is_array($log['warning_details'])) {
                                                         echo '<pre>' . htmlspecialchars(json_encode($log['warning_details'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . '</pre>';
                                                     } else {
                                                         echo htmlspecialchars($log['warning_details']);
                                                     }
                                                     ?>
                                                 </div>
                                             <?php endif; ?>
                                             <?php if ($log['success_details']): ?>
                                                 <div class="log-success-details">
                                                     <strong>✅ Детали успешной операции:</strong><br>
                                                     <?php 
                                                     if (is_array($log['success_details'])) {
                                                         echo '<pre>' . htmlspecialchars(json_encode($log['success_details'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . '</pre>';
                                                     } else {
                                                         echo htmlspecialchars($log['success_details']);
                                                     }
                                                     ?>
                                                 </div>
                                             <?php endif; ?>
                                            <?php if ($log['memory_usage']): ?>
                                                <div class="log-success-details">
                                                    <strong>💾 Использование памяти:</strong> <?php echo round($log['memory_usage'] / 1024, 2); ?> KB
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-<?php echo $log['status']; ?>">
                                        <?php 
                                        switch ($log['status']) {
                                            case 'success':
                                                echo '✅ Успешно';
                                                break;
                                            case 'error':
                                                echo '❌ Ошибка';
                                                break;
                                            case 'warning':
                                                echo '⚠️ Предупреждение';
                                                break;
                                            default:
                                                echo $log['status'];
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['ip']); ?></td>
                                <td>
                                    <?php if ($log['error_details'] || $log['warning_details'] || $log['success_details']): ?>
                                        <span style="color: #007bff; font-size: 0.9em;">📊 Есть детали</span>
                                    <?php else: ?>
                                        <span style="color: #666; font-size: 0.9em;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function toggleDetails(index) {
            const detailsElement = document.getElementById('details-' + index);
            const button = event.target;
            
            if (detailsElement.classList.contains('details-hidden')) {
                detailsElement.classList.remove('details-hidden');
                detailsElement.classList.add('details-visible');
                button.textContent = '🔽 Скрыть детали';
            } else {
                detailsElement.classList.remove('details-visible');
                detailsElement.classList.add('details-hidden');
                button.textContent = '🔍 Показать детали';
            }
        }
    </script>
</body>
</html> 