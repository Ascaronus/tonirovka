<?php
function contactServices() { return ['facebook'=>'Facebook', 'instagram'=>'Instagram', 'viber'=>'Viber', 'telegram'=>'Telegram']; }
function contactPlaces() { return ['contacts'=>'Контакты', 'footer'=>'Подвал', 'floating'=>'Плавающие кнопки']; }
function contactToday() { return (new DateTimeImmutable('now', new DateTimeZone('Europe/Kyiv')))->format('Y-m-d'); }
function contactStatsPath() { return dirname(__DIR__) . '/data/contact-clicks.json'; }
function contactReadStats($path = null) {
    $path = $path ?? contactStatsPath();
    if (!file_exists($path)) return ['started'=>null, 'days'=>[]];
    $raw = file_get_contents($path);
    if ($raw === false) throw new RuntimeException('Не удалось прочитать статистику.');
    $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($data) || !isset($data['days']) || !is_array($data['days'])) throw new RuntimeException('Файл статистики повреждён.');
    return $data;
}
function contactRecord($service, $place, $path = null) {
    if (!is_string($service) || !isset(contactServices()[$service]) || !is_string($place) || !isset(contactPlaces()[$place])) throw new InvalidArgumentException('Invalid event');
    $path = $path ?? contactStatsPath();
    $lock = fopen($path . '.lock', 'c');
    if (!$lock) throw new RuntimeException('Каталог data недоступен для записи.');
    $tmp = null;
    try {
        if (!flock($lock, LOCK_EX)) throw new RuntimeException('Не удалось заблокировать статистику.');
        $data = contactReadStats($path);
        $today = contactToday();
        $data['started'] = $data['started'] ?? $today;
        $data['days'][$today][$service][$place] = ($data['days'][$today][$service][$place] ?? 0) + 1;
        // Retain two years of daily aggregates, never individual visitor records.
        $cutoff = (new DateTimeImmutable($today))->modify('-729 days')->format('Y-m-d');
        foreach (array_keys($data['days']) as $day) if ($day < $cutoff) unset($data['days'][$day]);
        $tmp = tempnam(dirname($path), 'clicks-');
        if ($tmp === false || file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false || !rename($tmp, $path)) throw new RuntimeException('Не удалось сохранить статистику.');
        $tmp = null;
    } finally {
        if ($tmp && is_file($tmp)) unlink($tmp);
        flock($lock, LOCK_UN); fclose($lock);
    }
}
function contactSummary($data, $from, $to, $place = 'all') {
    $totals = array_fill_keys(array_keys(contactServices()), 0); $rows = [];
    foreach ($data['days'] as $day => $services) {
        if ($day < $from || $day > $to) continue;
        $row = array_fill_keys(array_keys(contactServices()), 0);
        foreach ($row as $service => $unused) foreach (contactPlaces() as $position => $label) {
            if ($place !== 'all' && $place !== $position) continue;
            $count = (int)($services[$service][$position] ?? 0);
            $row[$service] += $count; $totals[$service] += $count;
        }
        $rows[$day] = $row;
    }
    krsort($rows);
    return ['totals'=>$totals, 'rows'=>$rows];
}
