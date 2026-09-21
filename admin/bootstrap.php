<?php
/**
 * Общий bootstrap для страниц админки: сессия, авторизация, config, CSRF, логирование.
 * Подключать первым: require_once __DIR__ . '/bootstrap.php';
 * Не использовать в index.php (страница входа).
 */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (($_SESSION['admin_logged_in'] ?? false) !== true) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/csrf_functions.php';

if (!defined('LOGGING_FUNCTIONS_LOADED')) {
    include __DIR__ . '/logging_functions.php';
}

require_once __DIR__ . '/admin_runtime.php';
set_exception_handler(function (Throwable $e) {
    error_log('Admin request failed: '.get_class($e).': '.$e->getMessage());
    adminFail('Не удалось завершить операцию. Проверьте соединение с БД и права записи. Подробности записаны в журнал PHP.', 500);
});
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    adminValidatePost();
    if (!validateCsrf()) adminFail('Сессия формы истекла. Обновите страницу и повторите действие.', 403);
    $adminLock = fopen(DATA_DIR . '/admin-edit.lock', 'c');
    if (!$adminLock || !flock($adminLock, LOCK_EX)) adminFail('Не удалось начать сохранение: проверьте права на каталог data.',503);
    register_shutdown_function(function () use ($adminLock) { flock($adminLock, LOCK_UN); fclose($adminLock); });
    if (isset($_POST['admin_revision']) && (!is_string($_POST['admin_revision']) || !hash_equals(hash_file('sha256', INDEX_HTML_PATH), $_POST['admin_revision']))) adminFail('Сайт уже изменён в другой вкладке. Откройте форму заново, чтобы не затереть изменения.',409);
}
