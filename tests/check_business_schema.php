<?php
require __DIR__.'/../admin/business_schema.php';
$html=file_get_contents(__DIR__.'/../index.html');
$html=str_replace('+38 (050) 850-20-40','+38 (067) 123-45-67',$html);
$html=str_replace('от 700-1100 ₴','от 900-1400 ₴',$html);
$html=str_replace('Адреса: Харків, проспект Перемоги, 89','Адреса: Харків, Тестова вулиця, 10',$html);
$updated=syncBusinessSchemaHtml($html);
preg_match('~<script type="application/ld\+json" id="business-schema">(.*?)</script>~s',$updated,$m);
$b=json_decode($m[1],true,512,JSON_THROW_ON_ERROR);
if($b['telephone']!=='+380671234567'||$b['address']['streetAddress']!=='Тестова вулиця, 10')throw new RuntimeException('Contact synchronization');
$p=$b['hasOfferCatalog']['itemListElement'][0]['priceSpecification'];
if($p['minPrice']!=900||$p['maxPrice']!=1400||$p['unitCode']!=='MTK')throw new RuntimeException('Price synchronization');
if(substr_count($updated,'id="business-schema"')!==1)throw new RuntimeException('Duplicate entity');
if(isset($b['geo']))throw new RuntimeException('Unverified coordinates');
if(syncBusinessSchemaHtml($updated)!==$updated)throw new RuntimeException('Not idempotent');
echo "Business schema: contacts, address, numeric price ranges and idempotence passed\n";
