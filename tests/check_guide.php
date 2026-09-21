<?php
require __DIR__ . '/../admin/guide_helper.php';
function check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
$html = file_get_contents(__DIR__ . '/../index.html');
$fields = guideFields($html);
check(count($fields) === 19, 'Eight cards, section heading and two links');
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
