<?php
session_start();

$_SESSION = [];
$params = session_get_cookie_params();
if (isset($params['path'], $params['domain'], $params['secure'], $params['httponly'], $params['samesite'])) {
    setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
} else {
    setcookie(session_name(), '', time() - 3600, '/');
}
session_destroy();

header('Location: index.php');
exit;
