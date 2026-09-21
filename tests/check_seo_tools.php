<?php
require __DIR__.'/../admin/seo_tools.php';
$html=file_get_contents(INDEX_HTML_PATH);
try {
    $report=seoCheckSite();if($report['checks']['page_speed']['status']!=='info')throw new RuntimeException('Fake speed score');
    $meta=['uk'=>['title'=>'Тест $1 "назва"','description'=>'Опис & текст'],'ru'=>['title'=>'Тест $1 название','description'=>'Описание']];
    $new=seoApplyMeta($html,$meta);if(strpos($new,'$1')===false || strpos($new,'data-lang-ru="Тест $1 название"')===false)throw new RuntimeException('Bilingual metadata');
    $dc=seoDocument(seoApplyMeta($new,$meta));
    foreach(['DC.title'=>'title','DC.description'=>'description'] as $name=>$key){
        $nodes=$dc->query('//meta[@name="'.$name.'"]');
        if($nodes->length!==1 || $nodes->item(0)->getAttribute('content')!==$meta['uk'][$key] || $nodes->item(0)->getAttribute('data-lang-ru')!==$meta['ru'][$key])throw new RuntimeException('Dublin Core must match saved bilingual SEO fields without duplicates');
    }
    $broken=preg_replace('/alt="[^"]*"/','', $html,1);adminAtomicWrite(INDEX_HTML_PATH,$broken);
    if(seoCheckSite()['checks']['images']['status']!=='warning')throw new RuntimeException('Missing alt not detected');
    $fixed=seoSyncFaq($html);adminAtomicWrite(INDEX_HTML_PATH,$fixed);
    if(seoCheckSite()['checks']['structured_data']['status']!=='ok')throw new RuntimeException('FAQ JSON parity');
}finally{adminAtomicWrite(INDEX_HTML_PATH,$html);}
echo "SEO checks: honest status, bilingual literal metadata, missing alt, FAQ parity passed\n";
