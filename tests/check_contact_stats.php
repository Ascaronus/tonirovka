<?php
require __DIR__ . '/../admin/contact_stats_store.php';
function ensure($v, $message) { if (!$v) throw new RuntimeException($message); }
$dir = sys_get_temp_dir() . '/contacts-' . bin2hex(random_bytes(8)); mkdir($dir);
$path = $dir . '/stats.json';
try {
    ensure(contactReadStats($path)['days'] === [], 'Empty state');
    contactRecord('telegram', 'contacts', $path); contactRecord('telegram', 'contacts', $path);
    contactRecord('telegram', 'footer', $path); contactRecord('viber', 'footer', $path);
    $data = contactReadStats($path); $today = contactToday();
    ensure(contactSummary($data, $today, $today)['totals']['telegram'] === 3, 'Total aggregation');
    ensure(contactSummary($data, $today, $today, 'footer')['totals']['telegram'] === 1, 'Position filter');
    ensure(contactSummary($data, '2000-01-01', '2000-01-02')['rows'] === [], 'Date filter');
    try { contactRecord('bad', 'footer', $path); throw new LogicException('Invalid accepted'); } catch (InvalidArgumentException $e) {}
    $data['days']['2000-01-01']=['facebook'=>['footer'=>8]];
    file_put_contents($path, json_encode($data)); contactRecord('facebook','contacts',$path);
    ensure(!isset(contactReadStats($path)['days']['2000-01-01']), 'Retention');
    file_put_contents($path, '{broken');
    try { contactRecord('facebook','footer',$path); throw new LogicException('Corrupt data overwritten'); } catch (JsonException $e) {}
    ensure(file_get_contents($path)==='{broken', 'Preserve corrupt file for recovery');
} finally { foreach (glob($dir.'/*') as $file) unlink($file); rmdir($dir); }
// Render authenticated pages without touching the production database.
foreach (['contact-stats.php','guide.php'] as $page) {
    $render = tempnam(sys_get_temp_dir(), 'admin-render-');
    $script = '<?php session_start(); $_SESSION["admin_logged_in"]=true; session_write_close(); $_SERVER["REQUEST_METHOD"]="GET"; $_SERVER["SCRIPT_NAME"]="/admin/'.$page.'"; require '.var_export(__DIR__.'/../admin/'.$page, true).';';
    file_put_contents($render, $script);
    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($render).' 2>&1', $lines, $status); unlink($render);
    $html=implode("\n",$lines); $lines=[];
    ensure($status===0 && strpos($html,'Fatal error')===false && strpos($html,'Warning:')===false, 'Admin page PHP: '.$html);
    ensure(strpos($html,'href="contact-stats.php"')!==false && strpos($html,'assets/admin.css')!==false, 'Shared menu and styles');
    ensure(strpos($html, $page==='guide.php'?'Карточка 8':'Статистика контактов')!==false, 'Page content');
}
echo "Contact aggregate, validation, storage and admin render checks passed\n";
