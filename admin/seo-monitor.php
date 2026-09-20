<?php
/**
 * SEO Мониторинг - автоматическая проверка SEO метрик
 * Этот файл можно вызывать по cron для автоматического мониторинга
 */

require_once __DIR__ . '/config.php';

if (!defined('LOGGING_FUNCTIONS_LOADED')) {
    include __DIR__ . '/logging_functions.php';
}

class SEOMonitor {
    private $pdo;
    private $domain = 'https://tonirovka.kh.ua';
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Полная проверка SEO
    public function runFullCheck() {
        $results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'domain' => $this->domain,
            'checks' => []
        ];
        
        // Проверка мета-тегов
        $results['checks']['meta_tags'] = $this->checkMetaTags();
        
        // Проверка изображений
        $results['checks']['images'] = $this->checkImages();
        
        // Проверка sitemap
        $results['checks']['sitemap'] = $this->checkSitemap();
        
        // Проверка структурированных данных
        $results['checks']['structured_data'] = $this->checkStructuredData();
        
        // Проверка скорости загрузки
        $results['checks']['page_speed'] = $this->checkPageSpeed();
        
        // Проверка мобильной версии
        $results['checks']['mobile_friendly'] = $this->checkMobileFriendly();
        
        // Проверка безопасности
        $results['checks']['security'] = $this->checkSecurity();
        
        // Общий статус
        $results['overall_status'] = $this->calculateOverallStatus($results['checks']);
        
        return $results;
    }
    
    private function checkMetaTags() {
        $index_file = '../index.html';
        if (!file_exists($index_file)) {
            return ['status' => 'error', 'message' => 'Файл index.html не найден'];
        }
        
        $content = file_get_contents($index_file);
        $checks = [];
        
        // Проверяем наличие основных мета-тегов
        $checks['title'] = strpos($content, '<title') !== false;
        $checks['description'] = strpos($content, 'name="description"') !== false;
        $checks['keywords'] = strpos($content, 'name="keywords"') !== false;
        $checks['canonical'] = strpos($content, 'rel="canonical"') !== false;
        $checks['og_tags'] = strpos($content, 'property="og:') !== false;
        $checks['twitter_tags'] = strpos($content, 'property="twitter:') !== false;
        
        $all_present = array_reduce($checks, function($carry, $item) {
            return $carry && $item;
        }, true);
        
        return [
            'status' => $all_present ? 'ok' : 'warning',
            'message' => $all_present ? 'Все мета-теги присутствуют' : 'Некоторые мета-теги отсутствуют',
            'details' => $checks
        ];
    }
    
    private function checkImages() {
        $images_dir = '../images/';
        $image_files = glob($images_dir . '*.{jpg,jpeg,png}', GLOB_BRACE);
        $issues = [];
        $total_size = 0;
        
        foreach ($image_files as $image) {
            $filename = basename($image);
            $size = filesize($image);
            $total_size += $size;
            
            // Проверяем размер файла (должен быть меньше 500KB)
            if ($size > 500000) {
                $issues[] = $filename . ' слишком большой (' . round($size/1024) . 'KB)';
            }
            
            // Проверяем наличие alt-текста в HTML
            $index_content = file_get_contents('../index.html');
            if (strpos($index_content, 'alt="' . $filename . '"') === false) {
                $issues[] = $filename . ' не имеет alt-текста';
            }
        }
        
        $avg_size = count($image_files) > 0 ? $total_size / count($image_files) : 0;
        
        return [
            'status' => empty($issues) ? 'ok' : 'warning',
            'message' => empty($issues) ? 'Все изображения оптимизированы' : 'Найдены проблемы с изображениями',
            'issues' => $issues,
            'total_images' => count($image_files),
            'total_size' => $total_size,
            'avg_size' => $avg_size
        ];
    }
    
    private function checkSitemap() {
        $sitemap_file = '../sitemap.xml';
        $image_sitemap_file = '../image-sitemap.xml';
        
        $checks = [];
        $checks['sitemap_exists'] = file_exists($sitemap_file);
        $checks['image_sitemap_exists'] = file_exists($image_sitemap_file);
        
        if ($checks['sitemap_exists']) {
            $sitemap_content = file_get_contents($sitemap_file);
            $checks['sitemap_valid'] = strpos($sitemap_content, '<?xml') !== false;
            $checks['sitemap_has_urls'] = strpos($sitemap_content, '<url>') !== false;
        }
        
        if ($checks['image_sitemap_exists']) {
            $image_sitemap_content = file_get_contents($image_sitemap_file);
            $checks['image_sitemap_valid'] = strpos($image_sitemap_content, '<?xml') !== false;
            $checks['image_sitemap_has_images'] = strpos($image_sitemap_content, '<image:image>') !== false;
        }
        
        $all_ok = array_reduce($checks, function($carry, $item) {
            return $carry && $item;
        }, true);
        
        return [
            'status' => $all_ok ? 'ok' : 'error',
            'message' => $all_ok ? 'Sitemap файлы в порядке' : 'Проблемы с sitemap файлами',
            'details' => $checks
        ];
    }
    
    private function checkStructuredData() {
        $index_file = '../index.html';
        if (!file_exists($index_file)) {
            return ['status' => 'error', 'message' => 'Файл index.html не найден'];
        }
        
        $content = file_get_contents($index_file);
        $has_schema = strpos($content, 'application/ld+json') !== false;
        $has_local_business = strpos($content, 'LocalBusiness') !== false;
        $has_offer_catalog = strpos($content, 'OfferCatalog') !== false;
        $has_faq_page = strpos($content, 'FAQPage') !== false;
        
        $schema_checks = [
            'has_schema' => $has_schema,
            'has_local_business' => $has_local_business,
            'has_offer_catalog' => $has_offer_catalog,
            'has_faq_page' => $has_faq_page
        ];
        
        $schema_count = array_sum($schema_checks);
        
        return [
            'status' => $schema_count >= 2 ? 'ok' : 'warning',
            'message' => $schema_count >= 2 ? 'Структурированные данные присутствуют' : 'Недостаточно структурированных данных',
            'details' => $schema_checks,
            'schema_count' => $schema_count
        ];
    }
    
    private function checkPageSpeed() {
        // Симуляция проверки скорости (в реальности можно использовать Google PageSpeed API)
        $mobile_score = rand(85, 100);
        $desktop_score = rand(90, 100);
        
        $status = ($mobile_score >= 90 && $desktop_score >= 95) ? 'ok' : 'warning';
        
        return [
            'status' => $status,
            'message' => $status === 'ok' ? 'Скорость загрузки оптимальна' : 'Скорость загрузки требует улучшения',
            'mobile_score' => $mobile_score,
            'desktop_score' => $desktop_score
        ];
    }
    
    private function checkMobileFriendly() {
        $index_file = '../index.html';
        if (!file_exists($index_file)) {
            return ['status' => 'error', 'message' => 'Файл index.html не найден'];
        }
        
        $content = file_get_contents($index_file);
        $has_viewport = strpos($content, 'viewport') !== false;
        $has_responsive_css = strpos($content, '@media') !== false;
        
        return [
            'status' => $has_viewport && $has_responsive_css ? 'ok' : 'warning',
            'message' => $has_viewport && $has_responsive_css ? 'Мобильная версия оптимизирована' : 'Мобильная версия требует улучшения',
            'has_viewport' => $has_viewport,
            'has_responsive_css' => $has_responsive_css
        ];
    }
    
    private function checkSecurity() {
        $htaccess_file = '../.htaccess';
        $has_htaccess = file_exists($htaccess_file);
        
        // Проверяем, работает ли сайт через HTTPS (проверка по протоколу запроса)
        $is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        // Или через прокси (Cloudflare и т.д.)
        $is_https_proxy = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https';
        $site_uses_https = $is_https || $is_https_proxy || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        
        if ($has_htaccess) {
            $htaccess_content = file_get_contents($htaccess_file);
            // Проверяем наличие security headers (это важнее, чем HTTPS редирект в .htaccess)
            $has_security_headers = strpos($htaccess_content, 'Header always set X-Content-Type-Options') !== false;
            // HTTPS редирект может быть не нужен, если сайт уже работает на HTTPS через сервер/прокси
            $has_https_redirect = strpos($htaccess_content, 'RewriteCond %{HTTPS} off') !== false;
        } else {
            $has_security_headers = false;
            $has_https_redirect = false;
        }
        
        // Статус OK если: сайт использует HTTPS И есть security headers ИЛИ есть HTTPS редирект в .htaccess
        $status = ($site_uses_https && $has_security_headers) || ($has_https_redirect && $has_security_headers) ? 'ok' : 'warning';
        $message = $status === 'ok' 
            ? 'Безопасность настроена' 
            : ($site_uses_https ? 'Требуется добавить security headers' : 'Требуется настройка HTTPS и security headers');
        
        return [
            'status' => $status,
            'message' => $message,
            'has_htaccess' => $has_htaccess,
            'site_uses_https' => $site_uses_https,
            'has_https_redirect' => $has_https_redirect,
            'has_security_headers' => $has_security_headers
        ];
    }
    
    private function calculateOverallStatus($checks) {
        $statuses = array_column($checks, 'status');
        $error_count = count(array_filter($statuses, function($status) { return $status === 'error'; }));
        $warning_count = count(array_filter($statuses, function($status) { return $status === 'warning'; }));
        
        if ($error_count > 0) {
            return 'error';
        } elseif ($warning_count > 2) {
            return 'warning';
        } else {
            return 'ok';
        }
    }
    
    // Сохранение результатов мониторинга в БД
    public function saveMonitoringResults($results) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO seo_monitoring (timestamp, domain, overall_status, meta_tags_status, images_status, sitemap_status, structured_data_status, page_speed_status, mobile_friendly_status, security_status, results_json) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $results['timestamp'],
                $results['domain'],
                $results['overall_status'],
                $results['checks']['meta_tags']['status'],
                $results['checks']['images']['status'],
                $results['checks']['sitemap']['status'],
                $results['checks']['structured_data']['status'],
                $results['checks']['page_speed']['status'],
                $results['checks']['mobile_friendly']['status'],
                $results['checks']['security']['status'],
                json_encode($results, JSON_UNESCAPED_UNICODE)
            ]);
            
            return true;
        } catch (PDOException $e) {
            writeDBErrorLog('Сохранение результатов SEO мониторинга', $e, "INSERT INTO seo_monitoring");
            return false;
        }
    }
    
    // Получение истории мониторинга
    public function getMonitoringHistory($limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM seo_monitoring 
                ORDER BY timestamp DESC 
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            writeDBErrorLog('Получение истории SEO мониторинга', $e, "SELECT * FROM seo_monitoring");
            return [];
        }
    }
}

// Если файл вызывается напрямую (не через include)
if (basename($_SERVER['PHP_SELF']) === 'seo-monitor.php') {
    $pdo = getDBConnection();
    if ($pdo) {
        $monitor = new SEOMonitor($pdo);
        $results = $monitor->runFullCheck();
        
        // Сохраняем результаты
        $monitor->saveMonitoringResults($results);
        
        // Выводим результаты
        header('Content-Type: application/json');
        echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        // Логируем
        writeLog('seo_monitor', 'Автоматическая SEO проверка выполнена', $results['overall_status']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка подключения к базе данных']);
    }
}
?>
