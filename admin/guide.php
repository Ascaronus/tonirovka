<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/guide_helper.php';
require_once __DIR__ . '/sitemap_helper.php';
$error = '';
$success = '';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validateCsrf()) throw new RuntimeException('Сессия формы истекла. Обновите страницу.');
        $file = fopen(INDEX_HTML_PATH, 'r+');
        if (!$file) throw new RuntimeException('Нет доступа для записи index.html.');
        try {
            if (!flock($file, LOCK_EX)) throw new RuntimeException('Не удалось заблокировать файл.');
            $html = stream_get_contents($file);
            if (!hash_equals(hash('sha256', guideSection($html)), (string)($_POST['version'] ?? ''))) throw new RuntimeException('Раздел уже изменён. Обновите страницу перед сохранением.');
            $updated = guideReplace($html, $_POST['fields'] ?? []);
            rewind($file);
            if (fwrite($file, $updated) !== strlen($updated) || !ftruncate($file, strlen($updated)) || !fflush($file)) throw new RuntimeException('Ошибка записи index.html.');
            $success = 'Тексты сохранены и опубликованы на обоих языках.';
        } finally { flock($file, LOCK_UN); fclose($file); }
        if (!updateSitemapLastmod()) $success .= ' Не удалось обновить дату sitemap.';
    }
    $html = file_get_contents(INDEX_HTML_PATH);
    $fields = guideFields($html);
    $version = hash('sha256', guideSection($html));
} catch (Throwable $e) {
    $error = $e->getMessage();
    // Keep submitted text visible after validation/conflict errors.
    if (isset($html)) {
        $fields = guideFields($html);
        $version = hash('sha256', guideSection($html));
        foreach ($fields as $i => &$field) foreach (['uk','ru'] as $lang) {
            if (isset($_POST['fields'][$i][$lang]) && is_string($_POST['fields'][$i][$lang])) $field[$lang] = $_POST['fields'][$i][$lang];
        }
        unset($field);
    }
}
?>
<!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Выбор и защита плёнкой — редактор</title>
<style>body{font:16px/1.5 Arial,sans-serif;background:#f5f7fa;color:#222;margin:24px}main{max-width:1100px;margin:auto}fieldset{background:white;border:1px solid #ddd;margin:20px 0;padding:20px}.languages{display:grid;grid-template-columns:1fr 1fr;gap:20px}textarea{box-sizing:border-box;width:100%;min-height:100px;font:inherit}button{padding:14px 24px;cursor:pointer}.error{color:#a00}.success{color:#165c23}@media(max-width:700px){.languages{grid-template-columns:1fr}}</style></head>
<body><main><a href="content.php">← Управление контентом</a><h1>Выбор и защита плёнкой</h1><p>Редактируйте заголовки, описания и подписи ссылок нового раздела. Сохранение сразу обновляет сайт. Поля принимают обычный текст.</p>
<?php if ($error): ?><p class="error" role="alert"><?=guideEscape($error)?></p><?php endif ?>
<?php if ($success): ?><p class="success" role="status"><?=guideEscape($success)?></p><?php endif ?>
<?php if (isset($fields, $version)): ?><form method="post"><?=getCsrfField()?><input type="hidden" name="version" value="<?=guideEscape($version)?>">
<?php foreach ($fields as $i => $field): ?><fieldset><legend><?=guideEscape(($field['tag'] === 'p' ? 'Описание' : 'Заголовок / подпись').' '.($i+1))?></legend><div class="languages">
<?php foreach (['uk'=>'Українська','ru'=>'Русский'] as $lang=>$label): ?><label><?=$label?><textarea name="fields[<?=$i?>][<?=$lang?>]" required><?=guideEscape($field[$lang])?></textarea></label><?php endforeach ?>
</div></fieldset><?php endforeach ?><button type="submit">Сохранить и опубликовать</button></form><?php endif ?>
</main></body></html>
