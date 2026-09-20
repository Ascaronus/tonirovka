<?php
/**
 * Общий bootstrap для страниц админки: сессия, авторизация, config, CSRF, логирование.
 * Подключать первым: require_once __DIR__ . '/bootstrap.php';
 * Не использовать в index.php (страница входа).
 */
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/csrf_functions.php';

if (!defined('LOGGING_FUNCTIONS_LOADED')) {
    include __DIR__ . '/logging_functions.php';
}
