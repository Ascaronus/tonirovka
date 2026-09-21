<?php
/** Keep editable HTML readable; compact only JSON-LD and tag indentation. */
function compactPublishedHtml($html) {
    $html=preg_replace_callback('~(<script\b[^>]*type="application/ld\+json"[^>]*>)(.*?)(</script>)~s',function($m){
        try {
            $data=json_decode($m[2],false,512,JSON_THROW_ON_ERROR);
            return $m[1].json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR).$m[3];
        } catch (Throwable $e) { return $m[0]; }
    },$html);
    // Preserve whitespace in scripts and elements where it can affect content.
    $parts=preg_split('~(<(?:script|style|pre|textarea)\b[^>]*>.*?</(?:script|style|pre|textarea)>)~is',$html,-1,PREG_SPLIT_DELIM_CAPTURE);
    foreach($parts as $i=>$part) if($i%2===0)$parts[$i]=preg_replace('/^[\t ]+(?=<)/m','',$part);
    return implode('',$parts);
}
