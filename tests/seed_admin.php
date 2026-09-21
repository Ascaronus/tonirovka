<?php
require __DIR__.'/../admin/config.php';
$pdo=getDBConnection();if(!$pdo)throw new RuntimeException('Test DB unavailable');
$version=(string)$pdo->query('SELECT VERSION()')->fetchColumn();
$expected=getenv('EXPECTED_MYSQL_SERIES');
if($expected && (!str_starts_with($version,$expected.'.') || stripos($version,'MariaDB')!==false))throw new RuntimeException('Expected MySQL '.$expected.', got '.$version);
echo "Database compatibility test: MySQL ".$version."\n";
$tables=[
'content'=>'id INT PRIMARY KEY AUTO_INCREMENT, section VARCHAR(64), language VARCHAR(4), title TEXT, content LONGTEXT',
'prices'=>'id INT PRIMARY KEY AUTO_INCREMENT, service_uk TEXT, service_ru TEXT, price_uk TEXT, price_ru TEXT, sort_order INT',
'films'=>'id INT PRIMARY KEY AUTO_INCREMENT, image TEXT, alt_text TEXT, alt_uk TEXT, alt_ru TEXT, title_uk TEXT, title_ru TEXT, name_uk TEXT, name_ru TEXT, description_uk TEXT, description_ru TEXT, features_uk TEXT, features_ru TEXT, sort_order INT',
'gallery'=>'id INT PRIMARY KEY AUTO_INCREMENT, image TEXT, alt_uk TEXT, alt_ru TEXT, title_uk TEXT, title_ru TEXT, sort_order INT',
'settings'=>'id INT PRIMARY KEY AUTO_INCREMENT, site_title_uk TEXT, site_title_ru TEXT, site_description_uk TEXT, site_description_ru TEXT, site_keywords_uk TEXT, site_keywords_ru TEXT, admin_username TEXT, admin_email TEXT, google_analytics TEXT, google_verification TEXT, version VARCHAR(20)',
'admin_logs'=>'id INT PRIMARY KEY AUTO_INCREMENT, timestamp DATETIME, action TEXT, details TEXT, status VARCHAR(20), ip TEXT, user_agent TEXT, session_id TEXT, error_details LONGTEXT, warning_details LONGTEXT, success_details LONGTEXT, backtrace LONGTEXT, memory_usage BIGINT, peak_memory BIGINT'
];
foreach($tables as $table=>$columns)$pdo->exec("CREATE TABLE IF NOT EXISTS `$table` ($columns) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$uk=json_decode(file_get_contents(__DIR__.'/../langs/uk.json'),true);$ru=json_decode(file_get_contents(__DIR__.'/../langs/ru.json'),true);
$insert=function($table,$row)use($pdo){$pdo->prepare("INSERT INTO `$table` (`".implode('`,`',array_keys($row))."`) VALUES (".implode(',',array_fill(0,count($row),'?')).")")->execute(array_values($row));};
foreach(['uk'=>$uk,'ru'=>$ru] as $lang=>$t){
 $insert('content',['section'=>'hero','language'=>$lang,'title'=>$t['hero']['title'],'content'=>$t['hero']['description']]);
 foreach(['contacts','footer'] as $section)$insert('content',['section'=>$section,'language'=>$lang,'title'=>$section,'content'=>json_encode($t[$section],JSON_UNESCAPED_UNICODE)]);
 foreach($t['faq'] as $key=>$item){$insert('content',['section'=>$key,'language'=>$lang,'title'=>'FAQ','content'=>$item['question']]);$insert('content',['section'=>$key.'_answer','language'=>$lang,'title'=>'FAQ','content'=>$item['answer']]);}
}
foreach($uk['prices'] as $i=>$p)$insert('prices',['service_uk'=>$p['name'],'service_ru'=>$ru['prices'][$i]['name'],'price_uk'=>$p['price'],'price_ru'=>$ru['prices'][$i]['price'],'sort_order'=>$i]);
$images=['6569bd5c-9299-4c40-b084-9efcd8cc400a.jpeg','bron1.png','3.jpg'];
foreach($uk['films'] as $i=>$p)$insert('films',['image'=>$images[$i],'title_uk'=>$p['title'],'title_ru'=>$ru['films'][$i]['title'],'name_uk'=>$p['title'],'name_ru'=>$ru['films'][$i]['title'],'alt_text'=>$p['alt'],'alt_uk'=>$p['alt'],'alt_ru'=>$ru['films'][$i]['alt'],'description_uk'=>$p['description'],'description_ru'=>$ru['films'][$i]['description'],'features_uk'=>json_encode($p['features']),'features_ru'=>json_encode($ru['films'][$i]['features']),'sort_order'=>$i]);
$doc=new DOMDocument();@$doc->loadHTML(file_get_contents(__DIR__.'/../index.html'));$xp=new DOMXPath($doc);$imgs=$xp->query('//*[@id="gallery"]//img');
foreach($uk['gallery'] as $i=>$p)$insert('gallery',['image'=>basename(parse_url($imgs->item($i)->getAttribute('src'),PHP_URL_PATH)),'title_uk'=>$p['title'],'title_ru'=>$ru['gallery'][$i]['title'],'alt_uk'=>$p['alt'],'alt_ru'=>$ru['gallery'][$i]['alt'],'sort_order'=>$i]);
$insert('settings',['site_title_uk'=>'Тонування вікон Харків','site_title_ru'=>'Тонировка окон Харьков','site_description_uk'=>'Встановлення плівок у Харкові','site_description_ru'=>'Установка плёнок в Харькове','site_keywords_uk'=>'','site_keywords_ru'=>'','admin_username'=>'audit','admin_email'=>'audit@example.test','google_analytics'=>'','google_verification'=>'','version'=>'test']);
file_put_contents(__DIR__.'/../admin/.env',"ADMIN_USERNAME=audit\nADMIN_PASSWORD_HASH=".password_hash('Audit-Only-12345',PASSWORD_DEFAULT)."\n");
echo "Isolated admin test database seeded\n";
