<?php
function adminEscape($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function adminAtomicWrite($path, $data) {
    if (!is_string($data)) throw new RuntimeException('Не удалось подготовить данные файла.');
    if (defined('INDEX_HTML_PATH') && $path === INDEX_HTML_PATH) {
        require_once __DIR__.'/html_compact.php';
        $data = compactPublishedHtml($data);
    }
    $tmp = tempnam(dirname($path), '.admin-');
    if ($tmp === false) throw new RuntimeException('Каталог недоступен для записи.');
    try {
        if (file_put_contents($tmp, $data) !== strlen($data)) throw new RuntimeException('Не удалось записать файл целиком.');
        chmod($tmp, is_file($path) ? (fileperms($path) & 0777) : 0644);
        if (!rename($tmp, $path)) throw new RuntimeException('Не удалось заменить файл.');
    } finally { if (is_file($tmp)) unlink($tmp); }
    return strlen($data);
}
function adminUpdateEnv($key, $value) {
    if (!in_array($key, ['ADMIN_PASSWORD_HASH','ADMIN_USERNAME'], true) || preg_match('/[\r\n]/', $value)) throw new InvalidArgumentException('Некорректное значение настройки.');
    $path = __DIR__.'/.env';
    if (!is_file($path)) throw new RuntimeException('Сначала создайте admin/.env.');
    $env=file_get_contents($path); $line=$key.'='.$value;
    $pattern='/^'.preg_quote($key,'/').'=.*$/m';
    $env=preg_match($pattern,$env)?preg_replace_callback($pattern,fn()=>$line,$env):rtrim($env)."\n".$line."\n";
    adminAtomicWrite($path,$env); $_ENV[$key]=$value;
}
function adminBackupPath($name) {
    if (!is_string($name) || basename($name)!==$name || !preg_match('/^[a-zA-Z0-9_.-]+\.json$/D',$name)) throw new InvalidArgumentException('Недопустимое имя резервной копии.');
    $root=realpath(DATA_DIR.'/backups'); $file=$root?realpath($root.'/'.$name):false;
    if (!$file || dirname($file)!==$root || !is_file($file)) throw new RuntimeException('Резервная копия не найдена.');
    return $file;
}
function adminImageName($name) {
    return is_string($name) && basename($name)===$name && preg_match('/^[^\/\\\\\x00]+\.(?:jpe?g|png|gif|webp)$/iD',$name);
}
function adminReplaceUpload($tmp, $destination) {
    $info=@getimagesize($tmp); $ext=strtolower(pathinfo($destination,PATHINFO_EXTENSION));
    $mime=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp'];
    if (!$info || ($info['mime']??'')!==($mime[$ext]??null)) throw new RuntimeException('Формат нового изображения должен совпадать с расширением текущего файла.');
    // move_uploaded_file replaces an existing file; do not delete the original first.
    return move_uploaded_file($tmp,$destination);
}
function adminFail($message, $status=400) {
    http_response_code($status);
    echo '<!doctype html><html lang="ru"><meta charset="utf-8"><title>Админ-панель</title><link rel="stylesheet" href="assets/admin.css"><div class="container"><div class="content"><h1>Изменения не применены</h1><p>'.adminEscape($message).'</p><a href="index.php">Вернуться в админку</a></div></div></html>';exit;
}

function adminTextLength($text) { return preg_match_all("/./us", (string)$text); }
function adminValidatePost() {
    foreach(['facebook','instagram','viber','telegram'] as $key) {
        if(!isset($_POST[$key]) || $_POST[$key]==='')continue;
        if(!is_string($_POST[$key])||!in_array(strtolower(parse_url($_POST[$key],PHP_URL_SCHEME)??''),['https','http','viber','tg'],true))adminFail('Некорректная ссылка контакта: '.$key);
    }
    foreach($_FILES as $file){
        if(!is_array($file)||is_array($file['error']??null))adminFail('Некорректная загрузка.');
        if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)continue;
        if(($file['error']??1)!==UPLOAD_ERR_OK)adminFail('Файл не загружен полностью.');
        $info=@getimagesize($file['tmp_name']);$ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
        $types=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp'];
        if(!$info||($info['mime']??'')!==($types[$ext]??null)||filesize($file['tmp_name'])>8*1024*1024||$info[0]*$info[1]>40000000)adminFail('Нужен JPG, PNG, GIF или WebP до 8 МБ и 40 мегапикселей с правильным расширением.');
    }
}
