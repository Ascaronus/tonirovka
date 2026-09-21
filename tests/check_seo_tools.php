<?php
require __DIR__.'/../admin/seo_tools.php';
$html=file_get_contents(INDEX_HTML_PATH);
require_once __DIR__.'/../admin/html_compact.php';
$fixture='<pre>  exact spaces</pre><script type="application/ld+json">{ "name": "Example" }</script>';
$compact=compactPublishedHtml($fixture);
if(!str_contains($compact,'<pre>  exact spaces</pre>') || compactPublishedHtml($compact)!==$compact)throw new RuntimeException('HTML compaction must preserve whitespace-sensitive content and be idempotent');
try {
    $report=seoCheckSite();if($report['checks']['page_speed']['status']!=='info')throw new RuntimeException('Fake speed score');
    $meta=['uk'=>['title'=>'Тест $1 "назва"','description'=>'Опис & текст'],'ru'=>['title'=>'Тест $1 название','description'=>'Описание']];
    $new=seoApplyMeta($html,$meta);if(strpos($new,'$1')===false || strpos($new,'data-lang-ru="Тест $1 название"')===false)throw new RuntimeException('Bilingual metadata');
    $broken=preg_replace('/alt="[^"]*"/','', $html,1);adminAtomicWrite(INDEX_HTML_PATH,$broken);
    if(seoCheckSite()['checks']['images']['status']!=='warning')throw new RuntimeException('Missing alt not detected');
    $fixed=seoSyncFaq($html);adminAtomicWrite(INDEX_HTML_PATH,$fixed);
    if(seoCheckSite()['checks']['structured_data']['status']!=='ok')throw new RuntimeException('FAQ JSON parity');
}finally{adminAtomicWrite(INDEX_HTML_PATH,$html);}
echo "SEO checks: honest status, bilingual literal metadata, missing alt, FAQ parity passed\n";
