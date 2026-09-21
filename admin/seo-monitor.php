<?php
if (PHP_SAPI !== 'cli') require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/seo_tools.php';
// CLI cron is independent of the working directory and never modifies site content.
if (realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__) {
    try {
        if(PHP_SAPI!=='cli' && ($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);header('Allow: POST');exit;}
        $report=seoCheckSite();seoSaveReport($report);
        if(PHP_SAPI!=='cli')header('Content-Type: application/json; charset=utf-8');
        echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);if(PHP_SAPI==='cli')exit($report['overall_status']==='ok'?0:1);
    }catch(Throwable $e){if(PHP_SAPI!=='cli')http_response_code(500);echo json_encode(['error'=>'Не удалось выполнить мониторинг.']);exit(2);}
}
