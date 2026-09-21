<?php
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/seo_tools.php';
require_once __DIR__.'/sitemap_helper.php';
$success='';$error='';$prefs=seoAutomationSettings();
try {
 if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  switch($_POST['action']??''){
   case 'check_seo': seoSaveReport(seoCheckSite());$success='Проверка завершена; результат добавлен в историю.';break;
   case 'update_sitemap': case 'update_image_sitemap': case 'sync_schema':
    if(!updateSitemapLastmod())throw new RuntimeException('Не удалось обновить все файлы. Проверьте права записи.');$success='Обе карты сайта и разметка синхронизированы.';break;
   case 'save_meta':
    $html=seoApplyMeta(file_get_contents(INDEX_HTML_PATH),$_POST['meta']??[]);adminAtomicWrite(INDEX_HTML_PATH,$html);$success='Мета-теги на обоих языках сохранены.';break;
   case 'save_automation':
    $prefs=['meta_on_save'=>isset($_POST['meta_on_save'])];adminAtomicWrite(DATA_DIR.'/seo-automation.json',json_encode($prefs));$success='Настройка автоматизации сохранена.';break;
   default:throw new RuntimeException('Неизвестное действие.');
  }
 }
 $report=seoCheckSite();
} catch(Throwable $e){$error=$e->getMessage();$report=['checks'=>[]];}
$html=file_get_contents(INDEX_HTML_PATH);$xp=seoDocument($html);$meta=[];
foreach(['uk','ru'] as $lang){$t=$xp->query('//title')->item(0);$d=$xp->query('//meta[@name="description"]')->item(0);$meta[$lang]=['title'=>$t?($t->getAttribute('data-lang-'.$lang)?:$t->textContent):'','description'=>$d?($d->getAttribute('data-lang-'.$lang)?:$d->getAttribute('content')):''];}
$page_title='🚀 SEO: проверки и автоматизация';include __DIR__.'/header.php';
?>
<h2>SEO сайта</h2><p class="muted">Проверки опубликованных файлов. Они не подтверждают индексацию в Google и не измеряют скорость загрузки.</p>
<?php if($success):?><div class="success"><?=adminEscape($success)?></div><?php endif ?>
<?php if($error):?><div class="error" role="alert"><?=adminEscape($error)?></div><?php endif ?>
<div class="grid"><?php foreach($report['checks'] as $key=>$check):?><div class="card"><h3><?=adminEscape(['meta_tags'=>'Мета-теги','images'=>'Изображения','sitemap'=>'Карты сайта','structured_data'=>'Микроразметка','robots'=>'robots.txt','page_speed'=>'Скорость','mobile_friendly'=>'Мобильная версия','security'=>'Доступность'][$key]??$key)?></h3><strong><?=adminEscape(['ok'=>'✓ Проверено','warning'=>'Требует внимания','info'=>'Не измерено'][$check['status']]??$check['status'])?></strong><p><?=adminEscape($check['message'])?></p></div><?php endforeach ?></div>
<div class="btn-group"><?php foreach(['check_seo'=>'Проверить и сохранить отчёт','update_sitemap'=>'Обновить карты и микроразметку'] as $action=>$label):?><form method="post"><?=getCsrfField()?><input type="hidden" name="action" value="<?=$action?>"><button><?=$label?></button></form><?php endforeach ?></div>
<p><a href="https://pagespeed.web.dev/analysis?url=https%3A%2F%2Ftonirovka.kh.ua%2F" target="_blank" rel="noopener">Google PageSpeed Insights</a> · <a href="https://search.google.com/test/rich-results?url=https%3A%2F%2Ftonirovka.kh.ua%2F" target="_blank" rel="noopener">Проверка расширенных результатов</a> · <a href="setup-seo-monitoring.php">История и настройка cron</a></p>
<h2>Автоматизация после редактирования</h2><p>Карты сайта, контакты, цены и FAQ в разметке обновляются после сохранения контента. Изображения и резервные копии автоматически не удаляются.</p>
<form method="post"><?=getCsrfField()?><input type="hidden" name="action" value="save_automation"><label><input type="checkbox" name="meta_on_save"<?=$prefs['meta_on_save']?' checked':''?>> Обновлять title и description из главного блока при сохранении контента</label><p class="muted">При включении эта опция заменяет ручные SEO-тексты после изменений. По умолчанию выключена.</p><button>Сохранить настройку</button></form>
<h2>Мета-теги на двух языках</h2><form method="post"><?=getCsrfField()?><input type="hidden" name="action" value="save_meta"><div class="grid"><?php foreach(['uk'=>'Українська','ru'=>'Русский'] as $lang=>$label):?><div><h3><?=$label?></h3><label>Title<input type="text" name="meta[<?=$lang?>][title]" value="<?=adminEscape($meta[$lang]['title'])?>" required></label><label>Description<textarea name="meta[<?=$lang?>][description]" required><?=adminEscape($meta[$lang]['description'])?></textarea></label></div><?php endforeach ?></div><button>Сохранить мета-теги</button></form>
<?php include __DIR__.'/footer.php'; ?>
