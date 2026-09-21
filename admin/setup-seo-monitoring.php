<?php
require_once __DIR__.'/bootstrap.php';require_once __DIR__.'/seo_tools.php';
$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){try{seoSaveReport(seoCheckSite());}catch(Throwable $e){$error=$e->getMessage();}}
$path=DATA_DIR.'/seo-history.json';$reports=is_file($path)?json_decode(file_get_contents($path),true):[];if(!is_array($reports))$reports=[];
$page_title='📋 История SEO-проверок';include __DIR__.'/header.php';?>
<h2>Мониторинг SEO</h2><p>Последние 50 запусков. Проверка читает файлы сайта и не меняет тексты, карты или изображения.</p>
<?php if($error):?><div class="error"><?=adminEscape($error)?></div><?php endif ?>
<form method="post"><?=getCsrfField()?><button>Запустить проверку</button></form>
<h3>Автоматическая проверка через cron</h3><p>Добавьте в планировщик хостинга запуск раз в шесть часов:</p><pre style="white-space:pre-wrap;overflow-wrap:anywhere">0 */6 * * * /usr/bin/php <?=adminEscape(__DIR__.'/seo-monitor.php')?></pre><p class="muted">Путь к PHP уточните на хостинге. HTTP-запуск требует входа и CSRF; публичной ссылки для cron нет. SQL-таблица для новых отчётов не нужна.</p>
<?php if(!$reports):?><div class="empty-state">Проверок пока нет.</div><?php endif ?>
<?php foreach($reports as $report):?><details class="card" style="margin-top:16px"><summary><?=adminEscape($report['timestamp']??'')?> · <?=adminEscape($report['overall_status']??'')?></summary><?php foreach($report['checks']??[] as $check):?><p><?=adminEscape($check['message']??'')?></p><?php endforeach ?></details><?php endforeach ?>
<?php include __DIR__.'/footer.php'; ?>
