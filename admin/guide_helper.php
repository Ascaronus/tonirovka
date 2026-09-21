<?php
// The published HTML is the source of truth; no database migration is needed.
function guideSection($html) {
    if (!preg_match('~<section id="window-film-guide"[^>]*>.*?</section>~s', $html, $m)) {
        throw new RuntimeException('Раздел выбора плёнки не найден в index.html.');
    }
    return $m[0];
}
function guidePattern() {
    return '~<(h2|h3|p|a)([^>]*?)(?: data-lang-uk="([^"]*)")? data-lang-ru="([^"]*)"([^>]*)>(.*?)</\1>~s';
}
function guideFields($html) {
    preg_match_all(guidePattern(), guideSection($html), $matches, PREG_SET_ORDER);
    $fields = [];
    foreach ($matches as $m) {
        $fields[] = ['tag' => $m[1], 'uk' => html_entity_decode($m[3] !== '' ? $m[3] : strip_tags($m[6]), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'ru' => html_entity_decode($m[4], ENT_QUOTES | ENT_HTML5, 'UTF-8')];
    }
    if (!$fields) throw new RuntimeException('Не найдены поля раздела.');
    return $fields;
}
function guideEscape($text) { return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function guideReplace($html, $fields) {
    $old = guideSection($html);
    $existing = guideFields($html);
    if (!is_array($fields) || count($fields) !== count($existing)) throw new RuntimeException('Набор полей изменился. Обновите страницу.');
    foreach ($existing as $i => $unused) {
        foreach (['uk', 'ru'] as $lang) {
            $value = $fields[$i][$lang] ?? null;
            if (!is_string($value) || trim($value) === '' || strlen($value) > 16000) throw new RuntimeException('Заполните оба языка; максимум 16000 байт на поле.');
        }
    }
    $i = 0;
    $new = preg_replace_callback(guidePattern(), function ($m) use ($fields, &$i) {
        $uk = guideEscape(trim($fields[$i]['uk']));
        $ru = guideEscape(trim($fields[$i]['ru']));
        $i++;
        return '<'.$m[1].$m[2].' data-lang-ru="'.$ru.'"'.$m[5].'>'.$uk.'</'.$m[1].'>';
    }, $old);
    return str_replace($old, $new, $html);
}
