<?php
require __DIR__ . '/../admin/guide_helper.php';
function check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
$html = file_get_contents(__DIR__ . '/../index.html');
$fields = guideFields($html);
check(count($fields) === 19, 'Eight cards, section heading and two links');
$legacy=preg_replace_callback('~<(h2|h3|p|a)([^>]*?) data-lang-ru="([^"]*)"([^>]*)>(.*?)</\1>~s',function($m){return '<'.$m[1].$m[2].' data-lang-uk="'.guideEscape(html_entity_decode(strip_tags($m[5]),ENT_QUOTES|ENT_HTML5,'UTF-8')).'" data-lang-ru="'.$m[3].'"'.$m[4].'>'.$m[5].'</'.$m[1].'>';},$html);
check(guideFields($legacy)===$fields,'Read both compact and legacy guide markup');
$fields[2]['uk'] = 'Уламки & "лапки" <script>alert(1)</script> $1';
$fields[2]['ru'] = 'Осколки и кавычки "тест"';
$updated = guideReplace($html, $fields);
$read = guideFields($updated);
check($read[2]['uk'] === $fields[2]['uk'] && $read[2]['ru'] === $fields[2]['ru'], 'Bilingual round-trip');
check(strpos(guideSection($updated), '<script>') === false, 'Escape HTML input');
check(str_replace(guideSection($html), '', $html) === str_replace(guideSection($updated), '', $updated), 'Preserve unrelated page content');
check(strpos(guideSection($updated), 'href="#contacts"') !== false, 'Preserve link target');
try { guideReplace($html, []); throw new LogicException('Missing fields accepted'); } catch (RuntimeException $e) {}
$fields[2]['uk'] = '';
try { guideReplace($html, $fields); throw new LogicException('Empty field accepted'); } catch (RuntimeException $e) {}
echo "Guide editor checks passed\n";
