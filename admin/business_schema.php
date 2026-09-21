<?php
/** Derive machine-readable contacts and prices from the published Ukrainian HTML. */
function syncBusinessSchemaHtml($html) {
    $html = str_replace('+3 (050) 850-20-40', '+38 (050) 850-20-40', $html);
    $dom = new DOMDocument(); $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html); libxml_clear_errors(); libxml_use_internal_errors($previous);
    $xp = new DOMXPath($dom);
    $base = null;
    foreach ($xp->query('//script[@type="application/ld+json"]') as $script) {
        $data = json_decode($script->textContent, true);
        if (in_array($data['@type'] ?? '', ['LocalBusiness','HomeAndConstructionBusiness'], true)) { $base=$data; break; }
    }
    if (!$base) return $html;
    $base['@type']='HomeAndConstructionBusiness'; $base['@id']='https://tonirovka.kh.ua/#business';
    unset($base['geo'], $base['address']['postalCode']); // Await a verified business map pin / postal code.
    foreach ($xp->query('//*[@id="contacts"]//p') as $p) {
        $text=trim($p->textContent);
        if (preg_match('/^Телефон:\s*(.+)$/u',$text,$m)) $base['telephone']='+'.preg_replace('/\D/','',$m[1]);
        if (preg_match('/^Email:\s*(.+)$/u',$text,$m)) $base['email']=trim($m[1]);
        if (preg_match('/^Адреса:\s*([^,]+),\s*(.+)$/u',$text,$m)) $base['address']=['@type'=>'PostalAddress','addressLocality'=>trim($m[1]),'streetAddress'=>trim($m[2]),'addressCountry'=>'UA'];
    }
    $offers=[];
    foreach ($xp->query('//*[@id="pricing"]//tbody/tr') as $row) {
        $cells=$row->getElementsByTagName('td'); if ($cells->length<2) continue;
        $name=trim($cells->item(0)->textContent); $price=trim($cells->item(1)->textContent);
        $offer=['@type'=>'Offer','url'=>'https://tonirovka.kh.ua/#pricing','itemOffered'=>['@type'=>'Service','name'=>$name,'provider'=>['@id'=>$base['@id']]],'description'=>$price.' за м²'];
        // Only publish numeric bounds when the visible price unambiguously provides them.
        $numeric=preg_replace('/(?<=\d)[ \x{00a0}](?=\d{3}(?:\D|$))/u','',$price);
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*[-–—]\s*(\d+(?:[.,]\d+)?)/u',$numeric,$m)) {
            $offer['priceSpecification']=['@type'=>'UnitPriceSpecification','minPrice'=>(float)str_replace(',','.',$m[1]),'maxPrice'=>(float)str_replace(',','.',$m[2]),'priceCurrency'=>'UAH','unitCode'=>'MTK'];
        }
        $offers[]=$offer;
    }
    $base['hasOfferCatalog']=['@type'=>'OfferCatalog','name'=>'Плівки для вікон — ціни за м²','itemListElement'=>$offers];
    $json=json_encode($base,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_PRETTY_PRINT);
    $done=false;
    return preg_replace_callback('~<script\b[^>]*type="application/ld\+json"[^>]*>.*?</script>~s',function($m)use($json,&$done){
        preg_match('~>(.*?)</script>~s',$m[0],$body);$data=json_decode($body[1],true);
        if (!in_array($data['@type']??'', ['LocalBusiness','HomeAndConstructionBusiness'],true)) return $m[0];
        if ($done) return ''; $done=true;
        return '<script type="application/ld+json" id="business-schema">'.$json.'</script>';
    },$html);
}
function updateBusinessSchema() {
    $path=dirname(__DIR__).'/index.html';
    $file=fopen($path,'r+'); if(!$file)return false;
    try {
        if(!flock($file,LOCK_EX))return false;
        $html=stream_get_contents($file);$updated=syncBusinessSchemaHtml($html);
        if($updated===$html)return true;
        rewind($file);return fwrite($file,$updated)===strlen($updated) && ftruncate($file,strlen($updated)) && fflush($file);
    } finally {flock($file,LOCK_UN);fclose($file);}
}
