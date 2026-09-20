<?php
/**
 * Функции для логирования действий в админ-панели
 */

// Предотвращаем повторное подключение файла
if (defined('LOGGING_FUNCTIONS_LOADED')) {
    return;
}
define('LOGGING_FUNCTIONS_LOADED', true);

// Подключаем конфигурацию БД
require_once __DIR__ . '/config.php';

// Проверяем, используется ли БД для логов
function useDBLogs() {
    return file_exists(__DIR__ . '/logs_config.php');
}

// Функция для записи логов в БД
function writeLogToDB($action, $message, $level = 'info', $details = []) {
    try {
        $pdo = getDBConnection();
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $session_id = session_id() ?? 'unknown';
        
        // Подготавливаем детали для БД
        $error_details = null;
        $warning_details = null;
        $success_details = null;
        $backtrace = null;
        $memory_usage = null;
        $peak_memory = null;
        
        if ($level === 'error') {
            $error_details = json_encode($details, JSON_UNESCAPED_UNICODE);
            $backtrace = json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3), JSON_UNESCAPED_UNICODE);
            $memory_usage = memory_get_usage(true);
            $peak_memory = memory_get_peak_usage(true);
        } elseif ($level === 'warning') {
            $warning_details = json_encode($details, JSON_UNESCAPED_UNICODE);
            $backtrace = json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3), JSON_UNESCAPED_UNICODE);
            $memory_usage = memory_get_usage(true);
            $peak_memory = memory_get_peak_usage(true);
        } elseif ($level === 'success') {
            $success_details = json_encode($details, JSON_UNESCAPED_UNICODE);
        }
        
        $stmt = $pdo->prepare("INSERT INTO admin_logs (
            timestamp, action, details, status, ip, user_agent, session_id,
            error_details, warning_details, success_details, backtrace, memory_usage, peak_memory
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $timestamp,
            $action,
            $message,
            $level,
            $ip,
            substr($user_agent, 0, 200),
            $session_id,
            $error_details,
            $warning_details,
            $success_details,
            $backtrace,
            $memory_usage,
            $peak_memory
        ]);
        
        return true;
    } catch (Exception $e) {
        // Если БД недоступна, записываем в файл как fallback
        writeLogToFile($action, $message, $level, $details);
        return false;
    }
}

// Функция для записи логов в файл (fallback)
function writeLogToFile($action, $message, $level = 'info', $details = []) {
    $log_dir = (defined('DATA_DIR') ? DATA_DIR : __DIR__ . '/../data') . '/logs/';
    
    // Создаем директорию для логов, если её нет
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . 'admin_actions.log';
    
    // Формируем запись лога в JSON формате с дополнительными деталями
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $session_id = session_id() ?? 'unknown';
    
    // Добавляем дополнительную информацию в зависимости от уровня
    $log_data = [
        'timestamp' => $timestamp,
        'action' => $action,
        'details' => $message,
        'status' => $level,
        'ip' => $ip,
        'user_agent' => substr($user_agent, 0, 200),
        'session_id' => $session_id
    ];
    
    // Добавляем детали в зависимости от уровня
    if ($level === 'error') {
        $log_data['error_details'] = $details;
        $log_data['backtrace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $log_data['memory_usage'] = memory_get_usage(true);
        $log_data['peak_memory'] = memory_get_peak_usage(true);
    } elseif ($level === 'warning') {
        $log_data['warning_details'] = $details;
        $log_data['backtrace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $log_data['memory_usage'] = memory_get_usage(true);
        $log_data['peak_memory'] = memory_get_peak_usage(true);
    } elseif ($level === 'success') {
        $log_data['success_details'] = $details;
    }
    
    // Добавляем общую информацию о деталях для совместимости
    if (!empty($details)) {
        $log_data['function_details'] = $details;
    }
    
    $log_entry = json_encode($log_data, JSON_UNESCAPED_UNICODE) . "\n";
    
    // Записываем в файл
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// Основная функция для записи логов
function writeLog($action, $message, $level = 'info', $details = []) {
    if (useDBLogs()) {
        writeLogToDB($action, $message, $level, $details);
    } else {
        writeLogToFile($action, $message, $level, $details);
    }
    
    // Также записываем в системный лог PHP для критических ошибок
    if ($level === 'error') {
        $error_message = "Admin Error [$action]: $message";
        if (!empty($details)) {
            $error_message .= " | Details: " . json_encode($details, JSON_UNESCAPED_UNICODE);
        }
        error_log($error_message);
    }
}

// Функция для логирования ошибок БД с подробностями
function writeDBErrorLog($action, $error_message, $sql_query = '', $params = []) {
    $details = [
        'error_type' => 'database_error',
        'sql_query' => $sql_query,
        'parameters' => $params,
        'error_code' => null,
        'error_file' => null,
        'error_line' => null
    ];
    
    // Получаем информацию об ошибке, если это PDOException
    if (is_object($error_message) && $error_message instanceof PDOException) {
        $details['error_code'] = $error_message->getCode();
        $details['error_file'] = $error_message->getFile();
        $details['error_line'] = $error_message->getLine();
        $error_message = $error_message->getMessage();
    }
    
    writeLog($action, $error_message, 'error', $details);
}

// Функция для логирования предупреждений с контекстом
function writeWarningLog($action, $warning_message, $context = []) {
    $details = [
        'warning_type' => 'system_warning',
        'warning_details' => $context,
        'file_checked' => $context['file'] ?? null,
        'table_checked' => $context['table'] ?? null,
        'function_called' => $context['function'] ?? null
    ];
    
    writeLog($action, $warning_message, 'warning', $details);
}

// Функция для логирования успешных операций с деталями
function writeSuccessLog($action, $message, $operation_details = []) {
    $details = [
        'operation_type' => 'success',
        'success_details' => $operation_details,
        'records_affected' => $operation_details['records'] ?? null,
        'files_processed' => $operation_details['files'] ?? null,
        'tables_updated' => $operation_details['tables'] ?? null
    ];
    
    writeLog($action, $message, 'success', $details);
}

// Функция для чтения логов из БД
function getLogsFromDB($limit = 100, $filter = '') {
    try {
        $pdo = getDBConnection();
        
        $sql = "SELECT * FROM admin_logs";
        $params = [];
        
        if ($filter) {
            $sql .= " WHERE action LIKE ? OR details LIKE ?";
            $params[] = "%$filter%";
            $params[] = "%$filter%";
        }
        
        $sql .= " ORDER BY timestamp DESC LIMIT ?";
        $params[] = (int) $limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $logs = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $logs[] = [
                'timestamp' => $row['timestamp'],
                'action' => $row['action'],
                'details' => $row['details'],
                'status' => $row['status'],
                'ip' => $row['ip'],
                'level' => $row['status'],
                'error_details' => $row['error_details'] ? json_decode($row['error_details'], true) : null,
                'warning_details' => $row['warning_details'] ? json_decode($row['warning_details'], true) : null,
                'success_details' => $row['success_details'] ? json_decode($row['success_details'], true) : null,
                'backtrace' => $row['backtrace'] ? json_decode($row['backtrace'], true) : null,
                'memory_usage' => $row['memory_usage']
            ];
        }
        
        return $logs;
    } catch (Exception $e) {
        // Если БД недоступна, читаем из файла
        return getLogsFromFile($limit, $filter);
    }
}

// Функция для чтения логов из файла (fallback)
function getLogsFromFile($limit = 100, $filter = '') {
    $log_dir = (defined('DATA_DIR') ? DATA_DIR : __DIR__ . '/../data') . '/logs/';
    $log_file = $log_dir . 'admin_actions.log';
    
    $logs = [];
    
    if (!file_exists($log_file)) {
        return $logs;
    }
    
    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        return $logs;
    }
    
    // Читаем файл с конца (новые записи внизу)
    $lines = array_reverse($lines);
    
    foreach ($lines as $line) {
        // Сначала пытаемся декодировать JSON (новый формат)
        $log_data = json_decode($line, true);
        
        if ($log_data && isset($log_data['timestamp'], $log_data['action'], $log_data['details'])) {
            // JSON формат найден
            // Применяем фильтр
            if ($filter && stripos($log_data['action'], $filter) === false && stripos($log_data['details'], $filter) === false) {
                continue;
            }
            
            $logs[] = [
                'timestamp' => $log_data['timestamp'],
                'action' => $log_data['action'],
                'details' => $log_data['details'],
                'status' => $log_data['status'] ?? 'info',
                'ip' => $log_data['ip'] ?? 'unknown',
                'level' => $log_data['status'] ?? 'info',
                'error_details' => $log_data['error_details'] ?? null,
                'warning_details' => $log_data['warning_details'] ?? null,
                'success_details' => $log_data['success_details'] ?? null,
                'backtrace' => $log_data['backtrace'] ?? null,
                'memory_usage' => $log_data['memory_usage'] ?? null
            ];
        } else {
            // Пытаемся парсить старый текстовый формат
            if (preg_match('/^\[([^\]]+)\] \[([^\]]+)\] \[([^\]]+)\] \[IP: ([^\]]+)\] \[UA: ([^\]]+)\] (.+)$/', $line, $matches)) {
                $timestamp = $matches[1];
                $level = strtolower($matches[2]);
                $action = $matches[3];
                $ip = $matches[4];
                $user_agent = $matches[5];
                $message = $matches[6];
                
                // Применяем фильтр
                if ($filter && stripos($action, $filter) === false && stripos($message, $filter) === false) {
                    continue;
                }
                
                // Определяем статус на основе уровня
                $status = 'info';
                switch ($level) {
                    case 'success':
                        $status = 'success';
                        break;
                    case 'error':
                        $status = 'error';
                        break;
                    case 'warning':
                        $status = 'warning';
                        break;
                }
                
                $logs[] = [
                    'timestamp' => $timestamp,
                    'action' => $action,
                    'details' => $message,
                    'status' => $status,
                    'ip' => $ip,
                    'level' => $level,
                    'error_details' => null,
                    'warning_details' => null,
                    'backtrace' => null,
                    'memory_usage' => null
                ];
            }
        }
        
        // Ограничиваем количество записей
        if (count($logs) >= $limit) {
            break;
        }
    }
    
    return $logs;
}

// Основная функция для чтения логов
function getLogs($limit = 100, $filter = '') {
    if (useDBLogs()) {
        return getLogsFromDB($limit, $filter);
    } else {
        return getLogsFromFile($limit, $filter);
    }
}

// Функция для полной очистки всех логов из БД
function clearAllLogsFromDB() {
    try {
        $pdo = getDBConnection();
        
        $sql = "DELETE FROM admin_logs";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->rowCount();
    } catch (Exception $e) {
        return false;
    }
}

// Функция для очистки старых логов из БД
function cleanOldLogsFromDB($days = 30) {
    try {
        $pdo = getDBConnection();
        
        $sql = "DELETE FROM admin_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$days]);
        
        return $stmt->rowCount();
    } catch (Exception $e) {
        return false;
    }
}

// Функция для полной очистки всех логов из файла
function clearAllLogsFromFile() {
    $log_dir = (defined('DATA_DIR') ? DATA_DIR : __DIR__ . '/../data') . '/logs/';
    $log_file = $log_dir . 'admin_actions.log';
    
    if (file_exists($log_file)) {
        return unlink($log_file);
    }
    
    return true;
}

// Функция для очистки старых логов из файла
function cleanOldLogsFromFile($days = 30) {
    $log_dir = (defined('DATA_DIR') ? DATA_DIR : __DIR__ . '/../data') . '/logs/';
    $log_file = $log_dir . 'admin_actions.log';
    
    if (!file_exists($log_file)) {
        return true;
    }
    
    $file_time = filemtime($log_file);
    $days_old = (time() - $file_time) / (24 * 60 * 60);
    
    if ($days_old > $days) {
        // Создаем архив старого лога
        $archive_name = $log_dir . 'admin_actions_' . date('Y-m-d', $file_time) . '.log';
        return rename($log_file, $archive_name);
    }
    
    return true;
}

// Функция для полной очистки всех логов
function clearAllLogs() {
    if (useDBLogs()) {
        return clearAllLogsFromDB();
    } else {
        return clearAllLogsFromFile();
    }
}

// Функция для очистки старых логов
function cleanOldLogs($days = 30) {
    if (useDBLogs()) {
        return cleanOldLogsFromDB($days);
    } else {
        return cleanOldLogsFromFile($days);
    }
}

// Очистку старых логов выполнять по крону или кнопкой в разделе «Логи» админки 