<?php
// CSRF: генерация и проверка токена для форм админки

function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function getCsrfField() {
    $name = htmlspecialchars('csrf_token');
    $value = htmlspecialchars(getCsrfToken());
    return '<input type="hidden" name="' . $name . '" value="' . $value . '">';
}

function validateCsrf() {
    $token = $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
