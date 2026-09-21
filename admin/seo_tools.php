<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/admin_runtime.php';
function seoDocument($html) {
    $dom=new DOMDocument();$old=libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html);libxml_clear_errors();libxml_use_internal_errors($old);return new DOMXPath($dom);
}
function seoResult($issues,$ok) { return ['status'=>$issues?'warning':'ok','message'=>$issues?implode(' · ',$issues):$ok]; }
function seoCheckSite() {
    $html=file_get_contents(INDEX_HTML_PATH);if($html===false)throw new RuntimeException('index.html недоступен.');$xp=seoDocument($html);$checks=[];
    $issues=[];
    foreach(['//title'=>'title','//meta[@name="description"]/@content'=>'description'] as $q=>$name){$nodes=$xp->query($q);if($nodes->length!==1||trim($nodes->item(0)->textContent)==='')$issues[]='Проверьте '.$name;}
    $c=$xp->query('//link[@rel="canonical"]/@href');if($c->length!==1||$c->item(0)->textContent!=='https://tonirovka.kh.ua/')$issues[]='Некорректный canonical';
    if($xp->query('//h1')->length!==1)$issues[]='Должен быть один H1';
    foreach($xp->query('//meta[@name="robots"]/@content') as $node)if(preg_match('/\b(noindex|none)\b/i',$node->textContent))$issues[]='Главная закрыта от индексации';
    $checks['meta_tags']=seoResult($issues,'Title, description, canonical, H1 и robots проверены');
    $issues=[];
    foreach($xp->query('//img') as $img){$src=$img->getAttribute('src');if(!$img->hasAttribute('alt'))$issues[]='Нет alt: '.$src;elseif(trim($img->getAttribute('alt'))==='' && $img->getAttribute('role')!=='presentation')$issues[]='Проверьте пустой alt: '.$src;
        $path=parse_url($src,PHP_URL_PATH);if($path && !parse_url($src,PHP_URL_HOST)){$local=realpath(ROOT_DIR.'/'.ltrim(rawurldecode($path),'/'));if(!$local||!str_starts_with($local,ROOT_DIR.'/images/'))$issues[]='Изображение недоступно: '.$src;elseif(filesize($local)>700000)$issues[]='Большое изображение: '.basename($local);}}
    $checks['images']=seoResult(array_unique($issues),'Опубликованные изображения и alt проверены');
    $issues=[];
    foreach(['sitemap.xml','image-sitemap.xml'] as $file){$dom=new DOMDocument();$old=libxml_use_internal_errors(true);$ok=is_file(ROOT_DIR.'/'.$file)&&$dom->load(ROOT_DIR.'/'.$file,LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($old);if(!$ok){$issues[]=$file.': некорректный XML';continue;} $sx=new DOMXPath($dom);$sx->registerNamespace('s','http://www.sitemaps.org/schemas/sitemap/0.9');$urls=$sx->query('/s:urlset/s:url/s:loc');if($urls->length!==1||$urls->item(0)->textContent!=='https://tonirovka.kh.ua/')$issues[]=$file.': неканонические URL';}
    $checks['sitemap']=seoResult($issues,'Обе карты содержат канонический URL и корректный XML');
    $issues=[];$business=0;$faq=[];
    foreach($xp->query('//script[@type="application/ld+json"]') as $script){try{$data=json_decode($script->textContent,true,512,JSON_THROW_ON_ERROR);}catch(Throwable $e){$issues[]='Некорректный JSON-LD';continue;}
      if(in_array($data['@type']??'', ['LocalBusiness','HomeAndConstructionBusiness'],true)){$business++;foreach(['name','address','telephone','hasOfferCatalog'] as $key)if(empty($data[$key]))$issues[]='В компании нет '.$key;}
      if(($data['@type']??'')==='FAQPage')$faq=$data['mainEntity']??[];
    }
    if($business!==1)$issues[]='Нужна одна запись компании';
    $questions=$xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," faq-question ")]');
    if(count($faq)!==$questions->length)$issues[]='FAQ на странице расходится с JSON-LD';
    $checks['structured_data']=seoResult($issues,'JSON-LD компании и число вопросов FAQ проверены');
    $robots=is_file(ROOT_DIR.'/robots.txt')?file_get_contents(ROOT_DIR.'/robots.txt'):'';
    $checks['robots']=seoResult(!$robots||preg_match('/^Disallow:\s*\/\s*$/mi',$robots)?['Проверьте robots.txt']:[],'robots.txt доступен локально, полного запрета нет');
    $checks['page_speed']=['status'=>'info','message'=>'Скорость не измерялась. Для оценки используйте Google PageSpeed Insights.'];
    $checks['mobile_friendly']=['status'=>$xp->query('//meta[@name="viewport"]')->length?'info':'warning','message'=>'Проверено наличие viewport. Удобство мобильной версии требует браузерной проверки.'];
    $checks['security']=['status'=>'info','message'=>'Проверка файлов не подтверждает внешний HTTPS и HTTP-статусы. Проверяйте опубликованный сайт отдельно.'];
    return ['timestamp'=>date('c'),'domain'=>'https://tonirovka.kh.ua','overall_status'=>in_array('warning',array_column($checks,'status'),true)?'warning':'ok','checks'=>$checks];
}
function seoSyncFaq($html) {
    $xp=seoDocument($html);$items=[];
    foreach($xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," faq-item ")]') as $item){$q=$xp->query('.//*[contains(concat(" ",normalize-space(@class)," ")," faq-question ")]',$item)->item(0);$a=$xp->query('.//*[contains(concat(" ",normalize-space(@class)," ")," faq-answer ")]',$item)->item(0);if($q&&$a)$items[]=['@type'=>'Question','name'=>trim($q->textContent),'acceptedAnswer'=>['@type'=>'Answer','text'=>trim($a->textContent)]];}
    $schema=['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>$items];$found=false;
    $html=preg_replace_callback('~<script\b[^>]*type="application/ld\+json"[^>]*>(.*?)</script>~s',function($m)use($schema,&$found){$data=json_decode($m[1],true);if(($data['@type']??'')!=='FAQPage')return $m[0];if($found)return '';$found=true;return '<script type="application/ld+json">'.json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';},$html);
    if(!$found&&$items)$html=str_replace('</head>','<script type="application/ld+json">'.json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG).'</script></head>',$html);
    return $html;
}
function seoApplyMeta($html,$meta) {
    $esc=fn($v)=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    foreach(['title','description'] as $key)foreach(['uk','ru'] as $lang)if(!isset($meta[$lang][$key])||!is_string($meta[$lang][$key])||trim($meta[$lang][$key])==='')throw new InvalidArgumentException('Заполните title и description на обоих языках.');
    $title='<title data-lang-uk="'.$esc($meta['uk']['title']).'" data-lang-ru="'.$esc($meta['ru']['title']).'">'.$esc($meta['uk']['title']).'</title>';
    $html=preg_replace_callback('~<title\b[^>]*>.*?</title>~s',fn()=>$title,$html);
    foreach(['description'=>'description','og:title'=>'title','og:description'=>'description','twitter:title'=>'title','twitter:description'=>'description'] as $name=>$key){$attribute=str_contains($name,':')?'property':'name';$tag='<meta '.$attribute.'="'.$name.'" data-lang-uk="'.$esc($meta['uk'][$key]).'" data-lang-ru="'.$esc($meta['ru'][$key]).'" content="'.$esc($meta['uk'][$key]).'">';$pattern='~<meta\b(?=[^>]*(?:name|property)="'.preg_quote($name,'~').'" )[^>]*>~s';
      // Match attribute order independently.
      $pattern='~<meta\b[^>]*(?:name|property)="'.preg_quote($name,'~').'"[^>]*>~s';
      if(preg_match($pattern,$html))$html=preg_replace_callback($pattern,fn()=>$tag,$html);else $html=str_replace('</head>',$tag."\n</head>",$html);
    }
    return $html;
}
function seoAutomationSettings() { $path=DATA_DIR.'/seo-automation.json';$data=is_file($path)?json_decode(file_get_contents($path),true):[];return ['meta_on_save'=>(bool)($data['meta_on_save']??false)]; }
function seoHeroMeta($html) {
    $xp=seoDocument($html);$title=$xp->query('//section[contains(concat(" ",@class," ")," hero ")]//h1')->item(0);$desc=$xp->query('//section[contains(concat(" ",@class," ")," hero ")]//p')->item(0);if(!$title||!$desc)throw new RuntimeException('Главный блок не найден.');$out=[];
    foreach(['uk','ru'] as $lang){$out[$lang]=['title'=>$title->getAttribute('data-lang-'.$lang),'description'=>$desc->getAttribute('data-lang-'.$lang)];}return $out;
}
function seoSaveReport($report) {
    $path=DATA_DIR.'/seo-history.json';$old=is_file($path)?json_decode(file_get_contents($path),true):[];if(!is_array($old))$old=[];array_unshift($old,$report);adminAtomicWrite($path,json_encode(array_slice($old,0,50),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
}
