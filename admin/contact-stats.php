<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/contact_stats_store.php';
function statsEscape($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function statsDate($value) {
    if (!is_string($value)) return false;
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $d && $d->format('Y-m-d') === $value;
}
$today = contactToday();
$from = $_GET['from'] ?? (new DateTimeImmutable($today))->modify('-29 days')->format('Y-m-d');
$to = $_GET['to'] ?? $today;
$place = $_GET['place'] ?? 'all';
$error = ''; $data = ['started'=>null, 'days'=>[]];
if (!statsDate($from) || !statsDate($to) || $from > $to || !is_string($place) || !in_array($place, array_merge(['all'], array_keys(contactPlaces())), true)) {
    $error = 'Проверьте диапазон дат и место кнопки.'; $from = $today; $to = $today; $place = 'all';
}
try { $data = contactReadStats(); } catch (Throwable $e) { $error = 'Не удалось прочитать статистику. Проверьте файл data/contact-clicks.json и права доступа.'; }
$summary = contactSummary($data, $from, $to, $place);
$page_title = '📈 Нажатия на контакты';
include __DIR__ . '/header.php';
?>
<div class="page-intro"><div><h2>Статистика контактов</h2><p class="muted">Facebook, Instagram, Viber, Telegram и звонки · время Киева</p></div><span class="period-total">Всего за период: <strong><?=array_sum($summary['totals'])?></strong></span></div>
<?php if ($error): ?><div class="error" role="alert"><?=statsEscape($error)?></div><?php endif ?>
<?php if (!is_writable(dirname(contactStatsPath()))): ?><div class="error">Каталог data недоступен PHP для записи. Нажатия не смогут сохраняться, пока не будут исправлены права доступа.</div><?php endif ?>
<form method="get" class="stats-filters">
<label>С даты<input type="date" name="from" value="<?=statsEscape($from)?>" required></label>
<label>По дату<input type="date" name="to" value="<?=statsEscape($to)?>" required></label>
<label>Место кнопки<select name="place"><?php foreach (['all'=>'Все кнопки'] + contactPlaces() as $key=>$label): ?><option value="<?=$key?>"<?=$place===$key?' selected':''?>><?=$label?></option><?php endforeach ?></select></label>
<button type="submit">Показать</button></form>
<div class="period-links"><?php foreach ([1=>'Сегодня',7=>'7 дней',30=>'30 дней'] as $days=>$label): ?><a href="?<?=statsEscape(http_build_query(['from'=>(new DateTimeImmutable($today))->modify('-'.($days-1).' days')->format('Y-m-d'),'to'=>$today,'place'=>$place]))?>"><?=$label?></a><?php endforeach ?></div>
<div class="stats-cards"><?php foreach (contactServices() as $key=>$label): ?><div class="card"><span class="muted"><?=$label?></span><strong class="stat-number"><?=$summary['totals'][$key]?></strong><span class="muted">нажатий за период</span></div><?php endforeach ?></div>
<h3>Нажатия по дням</h3>
<?php if (!$summary['rows']): ?><div class="empty-state"><strong>За этот период нажатий пока нет</strong><p>Статистика начнёт собираться после установки обновлений на сайт. Прошлые переходы восстановить нельзя.</p></div>
<?php else: ?><div class="table-scroll"><table class="stats-table"><thead><tr><th>Дата</th><?php foreach(contactServices() as $label): ?><th><?=$label?></th><?php endforeach ?><th>Всего</th></tr></thead><tbody><?php foreach($summary['rows'] as $day=>$counts): ?><tr><td><?=statsEscape($day)?></td><?php foreach($counts as $count): ?><td><?=$count?></td><?php endforeach ?><td><strong><?=array_sum($counts)?></strong></td></tr><?php endforeach ?></tbody></table></div><?php endif ?>
<p class="muted stats-note">Учитываются нажатия, а не уникальные люди, сообщения, подписки или состоявшиеся звонки. Быстрые повторные нажатия на одну кнопку в течение секунды объединяются. Автоматические запросы и блокировщики могут влиять на точность. IP, cookies и персональные данные счётчик не сохраняет. Хранятся дневные итоги за последние 730 дней.<?php if($data['started']): ?> Первое нажатие: <?=statsEscape($data['started'])?>.<?php endif ?></p>
<?php include __DIR__ . '/footer.php'; ?>
