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

$page_title = '📖 Выбор и защита плёнкой';
include __DIR__ . '/header.php';
?>
<div class="page-intro"><div><h2>Карточки выбора плёнки</h2><p class="muted">Заголовки и описания на украинском и русском. Сохранение сразу обновляет сайт.</p></div></div>
<?php if ($error): ?><div class="error" role="alert"><?=guideEscape($error)?></div><?php endif ?>
<?php if ($success): ?><div class="success" role="status"><?=guideEscape($success)?></div><?php endif ?>
<?php if (isset($fields, $version)): ?><form method="post"><?=getCsrfField()?><input type="hidden" name="version" value="<?=guideEscape($version)?>">
<?php
$groups = []; $current = null; $card = 0;
foreach ($fields as $i => $field) {
    if ($field['tag'] === 'h3') { $card++; $current = 'card_'.$card; $groups[$current] = ['title'=>'Карточка '.$card.' · '.$field['uk'], 'fields'=>[]]; }
    elseif ($field['tag'] === 'h2') { $current='heading'; $groups[$current]=['title'=>'Заголовок раздела', 'fields'=>[]]; }
    elseif ($field['tag'] === 'a') { $current='links'; if (!isset($groups[$current])) $groups[$current]=['title'=>'Ссылки под карточками', 'fields'=>[]]; }
    $groups[$current]['fields'][$i]=$field;
}
foreach ($groups as $group): ?>
<section class="guide-card"><h3><?=guideEscape($group['title'])?></h3><div class="editor-languages">
<?php foreach (['uk'=>'Українська','ru'=>'Русский'] as $lang=>$label): ?><div><h4><?=$label?></h4>
<?php foreach ($group['fields'] as $i=>$field): ?><div class="form-group"><label for="field-<?=$i?>-<?=$lang?>"><?= $field['tag']==='p'?'Описание':($field['tag']==='a'?'Подпись ссылки':'Заголовок') ?></label>
<?php if ($field['tag']==='p'): ?><textarea id="field-<?=$i?>-<?=$lang?>" name="fields[<?=$i?>][<?=$lang?>]" required><?=guideEscape($field[$lang])?></textarea>
<?php else: ?><input type="text" id="field-<?=$i?>-<?=$lang?>" name="fields[<?=$i?>][<?=$lang?>]" value="<?=guideEscape($field[$lang])?>" required><?php endif ?></div><?php endforeach ?>
</div><?php endforeach ?></div></section><?php endforeach ?>
<div class="save-bar"><span class="muted">Изменения применятся к обоим языкам</span><button type="submit">Сохранить и опубликовать</button></div></form><?php endif ?>
<?php include __DIR__ . '/footer.php'; ?>
