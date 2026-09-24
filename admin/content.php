<?php
ob_start();
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/sitemap_helper.php';

if (isset($_POST['active_tab'])) {
    $_SESSION['active_tab'] = $_POST['active_tab'];
}
$active_tab = $_GET['tab'] ?? $_SESSION['active_tab'] ?? 'hero';
if (!is_string($active_tab) || !in_array($active_tab, ['hero','contacts','faq','footer'], true)) $active_tab = 'hero';

$content_file = DATA_DIR . '/content.json';

// Function to force cache refresh for banner
function forceBannerCacheRefresh() {
    $html_file = INDEX_HTML_PATH;
    $banner_file = IMAGES_DIR . '/hero-bg.jpg';
    $html_content = file_get_contents($html_file);
    
    if ($html_content && file_exists($banner_file)) {
        // Принудительно обновляем время модификации файла баннера
        touch($banner_file);
        
        // Получаем время модификации файла для cache-busting
        $cache_buster = filemtime($banner_file);
        
        // Удаляем старые параметры кеша
        $html_content = preg_replace(
            '/url\(\'images\/hero-bg\.jpg\?v=\d+\'\)/',
            'url(\'images/hero-bg.jpg\')',
            $html_content
        );
        
        // Добавляем новый параметр кеша с временем модификации файла
        $html_content = preg_replace(
            '/url\(\'images\/hero-bg\.jpg\'\)/',
            'url(\'images/hero-bg.jpg?v=' . $cache_buster . '\')',
            $html_content
        );
        
        // Сохраняем обновленный HTML
        $result = file_put_contents($html_file, $html_content);
        
        // Принудительно обновляем время модификации index.html
        if ($result !== false) {
            touch($html_file);
        }
        
        return $result !== false;
    }
    
    return false;
}

// Function to extract current content from index.html
function extractCurrentContent() {
    $html_content = file_get_contents(INDEX_HTML_PATH);
    if (!$html_content) return [];
    
    // Extract hero content with language-specific data attributes
    $hero_title_pattern = '/<h1[^>]*data-lang-uk="([^"]*)"[^>]*data-lang-ru="([^"]*)"[^>]*>([^<]*)<\/h1>/s';
    $hero_desc_pattern = '/<p[^>]*data-lang-uk="([^"]*)"[^>]*data-lang-ru="([^"]*)"[^>]*>([^<]*)<\/p>/s';
    
    $hero_title_uk = '';
    $hero_title_ru = '';
    $hero_description_uk = '';
    $hero_description_ru = '';
    
    if (preg_match($hero_title_pattern, $html_content, $matches)) {
        $hero_title_uk = trim($matches[1]);
        $hero_title_ru = trim($matches[2]);
    }
    
    if (preg_match($hero_desc_pattern, $html_content, $matches)) {
        $hero_description_uk = trim($matches[1]);
        $hero_description_ru = trim($matches[2]);
    }
    
    // Extract contact information
    $phone_pattern = '/Телефон:\s*(\+[^<"\r\n]+)/u';
    $email_pattern = '/tonirovka\.kh\.ua@gmail\.com/';
    $address_pattern = '/Адреса: Харків, проспект Перемоги, 89/';
    
    $phone = preg_match($phone_pattern, $html_content, $phone_match) ? trim($phone_match[1]) : '';
    $email = preg_match($email_pattern, $html_content) ? 'tonirovka.kh.ua@gmail.com' : '';
    $address_uk = preg_match($address_pattern, $html_content) ? 'Харків, проспект Перемоги, 89' : '';
    $address_ru = 'Харьков, проспект Победы, 89';
    
    // Extract social links - ищем в секции контактов и в футере
    $facebook = '';
    $instagram = '';
    $viber = '';
    $telegram = '';
    
    // Сначала ищем в секции контактов (с изображениями)
    $contacts_section_pattern = '/<section id="contacts">(.*?)<\/section>/s';
    if (preg_match($contacts_section_pattern, $html_content, $matches)) {
        $contacts_content = $matches[1];
        
        // Facebook в секции контактов
        $facebook_contacts_pattern = '/<a href="([^"]*)"[^>]*>\s*<div[^>]*>\s*<img[^>]*alt="Facebook"[^>]*>/s';
        if (preg_match($facebook_contacts_pattern, $contacts_content, $matches)) {
            $facebook = $matches[1];
        }
        
        // Instagram в секции контактов
        $instagram_contacts_pattern = '/<a href="([^"]*)"[^>]*>\s*<div[^>]*>\s*<img[^>]*alt="Instagram"[^>]*>/s';
        if (preg_match($instagram_contacts_pattern, $contacts_content, $matches)) {
            $instagram = $matches[1];
        }
        
        // Viber в секции контактов
        $viber_contacts_pattern = '/<a href="([^"]*)"[^>]*>\s*<div[^>]*>\s*<img[^>]*alt="Viber"[^>]*>/s';
        if (preg_match($viber_contacts_pattern, $contacts_content, $matches)) {
            $viber = $matches[1];
        }
        
        // Telegram в секции контактов
        $telegram_contacts_pattern = '/<a href="([^"]*)"[^>]*>\s*<div[^>]*>\s*<img[^>]*alt="Telegram"[^>]*>/s';
        if (preg_match($telegram_contacts_pattern, $contacts_content, $matches)) {
            $telegram = $matches[1];
        }
    }
    
    // Если не нашли в секции контактов, ищем в футере
    if (empty($facebook) || empty($instagram) || empty($viber) || empty($telegram)) {
        $social_links_pattern = '/<div class="social-links">\s*(.*?)\s*<\/div>/s';
        if (preg_match($social_links_pattern, $html_content, $matches)) {
            $social_content = $matches[1];
            
            $facebook_pattern = '/<a href="([^"]*)"[^>]*>\s*<i>FB<\/i>\s*<\/a>/s';
            $instagram_pattern = '/<a href="([^"]*)"[^>]*>\s*<i>IG<\/i>\s*<\/a>/s';
            $viber_pattern = '/<a href="([^"]*)"[^>]*>\s*<i>VB<\/i>\s*<\/a>/s';
            $telegram_pattern = '/<a href="([^"]*)"[^>]*>\s*<i>TG<\/i>\s*<\/a>/s';
            
            // Ищем ссылки в футере только если не нашли в секции контактов
            if (empty($facebook) && preg_match($facebook_pattern, $social_content, $matches)) {
                $facebook = $matches[1];
            }
            if (empty($instagram) && preg_match($instagram_pattern, $social_content, $matches)) {
                $instagram = $matches[1];
            }
            if (empty($viber) && preg_match($viber_pattern, $social_content, $matches)) {
                $viber = $matches[1];
            }
            if (empty($telegram) && preg_match($telegram_pattern, $social_content, $matches)) {
                $telegram = $matches[1];
            }
        }
    }
    
    // Extract FAQ content
    $faq_questions = [];
    $faq_section_pattern = '/<div class="faq-container"[^>]*>(.*?)<\/div>\s*<\/div>\s*<\/section>/s';
    if (preg_match($faq_section_pattern, $html_content, $matches)) {
        $faq_content = $matches[1];
        
        // Извлекаем каждый FAQ элемент
        $faq_item_pattern = '/<div class="faq-item"[^>]*>(.*?)<\/div>\s*<\/div>/s';
        preg_match_all($faq_item_pattern, $faq_content, $faq_items, PREG_SET_ORDER);
        
        foreach ($faq_items as $index => $faq_item) {
            $item_content = $faq_item[1];
            $question_number = $index + 1;
            $faq_key = "faq_{$question_number}";
            
            // Извлекаем вопрос
            $question_pattern = '/<div class="faq-question"[^>]*data-lang-uk="([^"]*)"[^>]*data-lang-ru="([^"]*)"[^>]*>([^<]*)<\/div>/s';
            if (preg_match($question_pattern, $item_content, $matches)) {
                $faq_questions[$faq_key]['question_uk'] = trim($matches[1]);
                $faq_questions[$faq_key]['question_ru'] = trim($matches[2]);
            }
            
            // Извлекаем ответ
            $answer_pattern = '/<div[^>]*data-lang-uk="([^"]*)"[^>]*data-lang-ru="([^"]*)"[^>]*>([^<]*)<\/div>/s';
            if (preg_match($answer_pattern, $item_content, $matches)) {
                $faq_questions[$faq_key]['answer_uk'] = trim($matches[1]);
                $faq_questions[$faq_key]['answer_ru'] = trim($matches[2]);
            }
        }
    }
    
    // Extract footer content with improved patterns
    $footer_desc_pattern_uk = '/<p[^>]*data-lang-uk="([^"]*)"[^>]*data-lang-ru="([^"]*)"[^>]*>/s';
    $footer_desc_pattern_simple = '/Професійне встановлення та продаж[^<]*архітектурних і захисних плівок[^<]*Якість, надійність, гарантія/';
    
    $footer_description_uk = '';
    $footer_description_ru = '';
    
    // Try to extract with data attributes first - keep <br> tags as they are
    if (preg_match($footer_desc_pattern_uk, $html_content, $matches)) {
        // Keep original text with <br> tags
        $footer_description_uk = trim($matches[1]);
        $footer_description_ru = trim($matches[2]);
    } 
    // Fallback to simple pattern
    elseif (preg_match($footer_desc_pattern_simple, $html_content)) {
        $footer_description_uk = 'Професійне встановлення та продаж<br> архітектурних і захисних плівок.<br> Якість, надійність, гарантія.';
        $footer_description_ru = 'Профессиональная установка и продажа<br> архитектурных и защитных пленок.<br> Качество, надежность, гарантия.';
    }
    
    // If still empty, set default values
    if (empty($footer_description_uk)) {
        $footer_description_uk = 'Професійне встановлення та продаж<br> архітектурних і захисних плівок.<br> Якість, надійність, гарантія.';
    }
    if (empty($footer_description_ru)) {
        $footer_description_ru = 'Профессиональная установка и продажа<br> архитектурных и защитных пленок.<br> Качество, надежность, гарантия.';
    }
    
    return [
        'hero' => [
            'title_uk' => $hero_title_uk,
            'title_ru' => $hero_title_ru,
            'description_uk' => $hero_description_uk,
            'description_ru' => $hero_description_ru
        ],
        'contacts' => [
            'phone' => $phone,
            'email' => $email,
            'address_uk' => $address_uk,
            'address_ru' => $address_ru,
            'facebook' => $facebook,
            'instagram' => $instagram,
            'viber' => $viber,
            'telegram' => $telegram
        ],
        'faq' => $faq_questions,
        'footer' => [
            'description_uk' => $footer_description_uk,
            'description_ru' => $footer_description_ru,
            'description_uk_display' => $footer_description_uk,
            'description_ru_display' => $footer_description_ru,
            'working_hours_uk' => [
                'weekdays' => 'Пн-Пт: 9:00 - 18:00',
                'saturday' => 'Сб: 10:00 - 15:00',
                'sunday' => 'Нд: Вихідний'
            ],
            'working_hours_ru' => [
                'weekdays' => 'Пн-Пт: 9:00 - 18:00',
                'saturday' => 'Сб: 10:00 - 15:00',
                'sunday' => 'Вс: Выходной'
            ]
        ]
    ];
}

// Function to update index.html with new content
function updateIndexHtml($content) {
    foreach ($content['contacts'] as &$value) $value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    unset($value);

    $html_content = file_get_contents(INDEX_HTML_PATH);
    if (!$html_content) {
        error_log("DEBUG: Failed to read index.html");
        return false;
    }
    
    // Update hero section - more specific patterns
    $hero_title_pattern = '/<h1[^>]*data-lang-uk="([^"]*)"[^>]*data-lang-ru="([^"]*)"[^>]*>([^<]*)<\/h1>/s';
    
    // Replace hero title
    $new_hero_title = '<h1 data-lang-uk="' . htmlspecialchars($content['hero']['title_uk']) . '" data-lang-ru="' . htmlspecialchars($content['hero']['title_ru']) . '">' . htmlspecialchars($content['hero']['title_uk']) . '</h1>';
    $html_content = preg_replace_callback($hero_title_pattern, fn() => $new_hero_title, $html_content);
    
    // Replace hero description - look for the first paragraph with data attributes in hero section
    $hero_desc_pattern = '/<div class="hero-content">[^<]*<h1[^>]*>[^<]*<\/h1>[^<]*<p[^>]*data-lang-uk="([^"]*)"[^>]*data-lang-ru="([^"]*)"[^>]*>([^<]*)<\/p>/s';
    $new_hero_desc = '<div class="hero-content">' . "\n" . 
                     '                <h1 data-lang-uk="' . htmlspecialchars($content['hero']['title_uk']) . '" data-lang-ru="' . htmlspecialchars($content['hero']['title_ru']) . '">' . htmlspecialchars($content['hero']['title_uk']) . '</h1>' . "\n" .
                     '                <p data-lang-uk="' . htmlspecialchars($content['hero']['description_uk']) . '" data-lang-ru="' . htmlspecialchars($content['hero']['description_ru']) . '">' . htmlspecialchars($content['hero']['description_uk']) . '</p>';
    $html_content = preg_replace_callback($hero_desc_pattern, fn() => $new_hero_desc, $html_content);
    
    // Update footer description - more specific pattern for footer
    // This pattern now captures the parts around the <p> tag to reconstruct the block
    $footer_desc_pattern = '/(<div class="footer-info">\s*<h3>tonirovka\.kh\.ua<\/h3>\s*)(<p[^>]*data-lang-uk="[^"]*"[^>]*data-lang-ru="[^"]*"[^>]*>.*?<\/p>)(\s*<div class="social-links">.*?<\/div>\s*<\/div>)/s';
    
    // Use the descriptions as they are - they should already contain <br> tags
    $uk_desc_display = htmlspecialchars($content['footer']['description_uk']);
    $ru_desc_display = htmlspecialchars($content['footer']['description_ru']);
    
    $new_footer_desc = '<p data-lang-uk="' . htmlspecialchars($content['footer']['description_uk']) . '" data-lang-ru="' . htmlspecialchars($content['footer']['description_ru']) . '">' . $uk_desc_display . '</p>';
    $html_content = preg_replace_callback($footer_desc_pattern, fn($m) => $m[1] . $new_footer_desc . $m[3], $html_content);
    
    // Update contact information - more specific patterns to target correct elements
    // Target only the main contacts section, not footer
    $phone_pattern = '/<div class="contact-details">[^<]*<p[^>]*data-lang-uk="Телефон: [^"]*"[^>]*data-lang-ru="Телефон: [^"]*"[^>]*>Телефон: [^<]*<\/p>/s';
    $email_pattern = '/<div class="contact-details">[^<]*<p[^>]*data-lang-uk="Телефон: [^"]*"[^>]*data-lang-ru="Телефон: [^"]*"[^>]*>Телефон: [^<]*<\/p>[^<]*<p[^>]*data-lang-uk="Email: [^"]*"[^>]*data-lang-ru="Email: [^"]*"[^>]*>Email: <a[^>]*href="mailto:[^"]*">(?:[^<]*|<img[^>]*>)<\/a><\/p>/s';
    $address_pattern = '~<div class="contact-details">.*?</div>~s';
    
    // Replace the entire contacts section with new data
    $new_contacts_section = '<div class="contact-details">' . "\n" .
                           '                <p data-lang-uk="Телефон: ' . $content['contacts']['phone'] . '" data-lang-ru="Телефон: ' . $content['contacts']['phone'] . '">Телефон: ' . $content['contacts']['phone'] . '</p>' . "\n" .
                           '                <p data-lang-uk="Адреса: ' . $content['contacts']['address_uk'] . '" data-lang-ru="Адрес: ' . $content['contacts']['address_ru'] . '">Адреса: ' . $content['contacts']['address_uk'] . '</p>' . "\n" .
                           '            </div>';
    
    $html_content = preg_replace_callback($address_pattern, fn() => $new_contacts_section, $html_content);
    
    // Update footer contacts section - more precise pattern that preserves structure
    $footer_contacts_pattern = '~<div class="footer-info">\s*<h3[^>]*data-lang-uk="Контакти"[^>]*>.*?</div>~s';
    
    // Replace only the contact information, preserving the div structure
    $new_footer_contacts = '<div class="footer-info">' . "\n" .
                          '                    <h3 data-lang-uk="Контакти" data-lang-ru="Контакты">Контакти</h3>' . "\n" .
                          '                    <p data-lang-uk="Телефон: ' . $content['contacts']['phone'] . '" data-lang-ru="Телефон: ' . $content['contacts']['phone'] . '">Телефон: ' . $content['contacts']['phone'] . '</p>' . "\n" .
                          '                    <p data-lang-uk="Адреса: ' . $content['contacts']['address_uk'] . '" data-lang-ru="Адрес: ' . $content['contacts']['address_ru'] . '">Адреса: ' . $content['contacts']['address_uk'] . '</p>' . "\n" .
                          '                </div>';
    
    $html_content = preg_replace_callback($footer_contacts_pattern, fn() => $new_footer_contacts, $html_content);
    
    // Update footer working hours section
    $footer_hours_pattern = '/<div class="footer-info">\s*<h3[^>]*data-lang-uk="Час роботи"[^>]*data-lang-ru="Время работы"[^>]*>Час роботи<\/h3>\s*<p[^>]*data-lang-uk="[^"]*"[^>]*data-lang-ru="[^"]*"[^>]*>[^<]*<\/p>\s*<p[^>]*data-lang-uk="[^"]*"[^>]*data-lang-ru="[^"]*"[^>]*>[^<]*<\/p>\s*<p[^>]*data-lang-uk="[^"]*"[^>]*data-lang-ru="[^"]*"[^>]*>[^<]*<\/p>\s*<\/div>/s';
    
    $new_footer_hours = '<div class="footer-info">' . "\n" .
                        '                    <h3 data-lang-uk="Час роботи" data-lang-ru="Время работы">Час роботи</h3>' . "\n" .
                        '                    <p data-lang-uk="' . $content['footer']['working_hours_uk']['weekdays'] . '" data-lang-ru="' . $content['footer']['working_hours_ru']['weekdays'] . '">' . $content['footer']['working_hours_uk']['weekdays'] . '</p>' . "\n" .
                        '                    <p data-lang-uk="' . $content['footer']['working_hours_uk']['saturday'] . '" data-lang-ru="' . $content['footer']['working_hours_ru']['saturday'] . '">' . $content['footer']['working_hours_uk']['saturday'] . '</p>' . "\n" .
                        '                    <p data-lang-uk="' . $content['footer']['working_hours_uk']['sunday'] . '" data-lang-ru="' . $content['footer']['working_hours_ru']['sunday'] . '">' . $content['footer']['working_hours_uk']['sunday'] . '</p>' . "\n" .
                        '                </div>';
    
    $html_content = preg_replace_callback($footer_hours_pattern, fn() => $new_footer_hours, $html_content);
    
    // Update social links in footer
    $social_links_pattern = '/<div class="social-links">\s*.*?<\/div>/s';
    $new_social_links = '<div class="social-links">' . "\n";
    if (!empty($content['contacts']['facebook'])) {
        $new_social_links .= '                        <a href="' . htmlspecialchars($content['contacts']['facebook']) . '" target="_blank" rel="noopener noreferrer"><i>FB</i></a>' . "\n";
    }
    if (!empty($content['contacts']['instagram'])) {
        $new_social_links .= '                        <a href="' . htmlspecialchars($content['contacts']['instagram']) . '" target="_blank" rel="noopener noreferrer"><i>IG</i></a>' . "\n";
    }
    if (!empty($content['contacts']['viber'])) {
        $new_social_links .= '                        <a href="' . htmlspecialchars($content['contacts']['viber']) . '" target="_blank" rel="noopener noreferrer"><i>VB</i></a>' . "\n";
    }
    if (!empty($content['contacts']['telegram'])) {
        $new_social_links .= '                        <a href="' . htmlspecialchars($content['contacts']['telegram']) . '" target="_blank" rel="noopener noreferrer"><i>TG</i></a>' . "\n";
    }
    $phone_number = preg_replace('/[^+0-9]/', '', $content['contacts']['phone'] ?? '');
    if ($phone_number !== '') {
        $new_social_links .= '<a href="tel:' . htmlspecialchars($phone_number, ENT_QUOTES, 'UTF-8') . '" class="contact-phone-action" data-lang-uk="Зателефонувати" data-lang-ru="Позвонить">Зателефонувати</a>' . "\n";
    }
    $new_social_links .= '                    </div>';
    $html_content = preg_replace_callback($social_links_pattern, fn() => $new_social_links, $html_content);

    // Update social links in contacts section
    $contacts_section_pattern = '/<section id="contacts">(.*?)<\/section>/s';
    if (preg_match($contacts_section_pattern, $html_content, $matches)) {
        $contacts_content = $matches[1];
        
        // Update Facebook in contacts section - replace entire link block
        $facebook_contacts_pattern = '/<a href="[^"]*"[^>]*>\s*<div[^>]*>\s*<img[^>]*alt="Facebook"[^>]*>\s*<p>Facebook<\/p>\s*<\/div>\s*<\/a>/s';
        if (!empty($content['contacts']['facebook'])) {
            $new_facebook_contacts = '<a href="' . htmlspecialchars($content['contacts']['facebook']) . '" target="_blank" rel="noopener noreferrer" class="contact-social-link">' . "\n" .
                                    '                    <div class="text-center">' . "\n" .
                                    '                        <img src="images/facebook.png" alt="Facebook" class="contact-social-icon">' . "\n" .
                                    '                        <p>Facebook</p>' . "\n" .
                                    '                    </div>' . "\n" .
                                    '                </a>';
            $contacts_content = preg_replace_callback($facebook_contacts_pattern, fn() => $new_facebook_contacts, $contacts_content);
        }
        
        // Update Instagram in contacts section - replace entire link block
        $instagram_contacts_pattern = '/<a href="[^"]*"[^>]*>\s*<div[^>]*>\s*<img[^>]*alt="Instagram"[^>]*>\s*<p>Instagram<\/p>\s*<\/div>\s*<\/a>/s';
        if (!empty($content['contacts']['instagram'])) {
            $new_instagram_contacts = '<a href="' . htmlspecialchars($content['contacts']['instagram']) . '" target="_blank" rel="noopener noreferrer" class="contact-social-link">' . "\n" .
                                     '                    <div class="text-center">' . "\n" .
                                     '                        <img src="images/instagram.png" alt="Instagram" class="contact-social-icon">' . "\n" .
                                     '                        <p>Instagram</p>' . "\n" .
                                     '                    </div>' . "\n" .
                                     '                </a>';
            $contacts_content = preg_replace_callback($instagram_contacts_pattern, fn() => $new_instagram_contacts, $contacts_content);
        }
        
        // Update Viber in contacts section - replace entire link block
        $viber_contacts_pattern = '/<a href="[^"]*"[^>]*>\s*<div[^>]*>\s*<img[^>]*alt="Viber"[^>]*>\s*<p>Viber<\/p>\s*<\/div>\s*<\/a>/s';
        if (!empty($content['contacts']['viber'])) {
            $new_viber_contacts = '<a href="' . htmlspecialchars($content['contacts']['viber']) . '" target="_blank" rel="noopener noreferrer" class="contact-social-link">' . "\n" .
                                  '                    <div class="text-center">' . "\n" .
                                  '                        <img src="images/viber.png" alt="Viber" class="contact-social-icon">' . "\n" .
                                  '                        <p>Viber</p>' . "\n" .
                                  '                    </div>' . "\n" .
                                  '                </a>';
            $contacts_content = preg_replace_callback($viber_contacts_pattern, fn() => $new_viber_contacts, $contacts_content);
        }
        
        // Update Telegram in contacts section - replace entire link block
        $telegram_contacts_pattern = '/<a href="[^"]*"[^>]*>\s*<div[^>]*>\s*<img[^>]*alt="Telegram"[^>]*>\s*<p>Telegram<\/p>\s*<\/div>\s*<\/a>/s';
        if (!empty($content['contacts']['telegram'])) {
            $new_telegram_contacts = '<a href="' . htmlspecialchars($content['contacts']['telegram']) . '" target="_blank" rel="noopener noreferrer" class="contact-social-link">' . "\n" .
                                     '                    <div class="text-center">' . "\n" .
                                     '                        <img src="images/telegram.png" alt="Telegram" class="contact-social-icon">' . "\n" .
                                     '                        <p>Telegram</p>' . "\n" .
                                     '                    </div>' . "\n" .
                                     '                </a>';
            $contacts_content = preg_replace_callback($telegram_contacts_pattern, fn() => $new_telegram_contacts, $contacts_content);
        }
        
        // Replace the entire contacts section with updated content
        $new_contacts_section = '<section id="contacts">' . $contacts_content . '</section>';
        $html_content = preg_replace_callback($contacts_section_pattern, fn() => $new_contacts_section, $html_content);
    }
    
    // Update banner cache-busting if banner file exists
    $banner_file = IMAGES_DIR . '/hero-bg.jpg';
    if (file_exists($banner_file)) {
        // Принудительно обновляем время модификации файла баннера
        touch($banner_file);
        
        // Получаем время модификации файла для cache-busting
        $cache_buster = filemtime($banner_file);
        
        // Удаляем старые параметры кеша
        $html_content = preg_replace(
            '/url\(\'images\/hero-bg\.jpg\?v=\d+\'\)/',
            'url(\'images/hero-bg.jpg\')',
            $html_content
        );
        
        // Добавляем новый параметр кеша с временем модификации файла
        $html_content = preg_replace(
            '/url\(\'images\/hero-bg\.jpg\'\)/',
            'url(\'images/hero-bg.jpg?v=' . $cache_buster . '\')',
            $html_content
        );
    }
    
    // Update FAQ section
    if (isset($content['faq'])) {
        $faq_section_pattern = '/<div class="faq-container"[^>]*>(.*?)<\/div>\s*<\/div>\s*<\/section>/s';
        
        $new_faq_content = '<div class="faq-container">';
        
        foreach ($content['faq'] as $faq_key => $question_data) {
            if (strpos($faq_key, 'faq_') === 0 && !empty($question_data['question_uk']) && !empty($question_data['answer_uk'])) {
                $new_faq_content .= "\n" .
                    '                <div class="faq-item">' . "\n" .
                    '                    <div class="faq-question" data-lang-uk="' . htmlspecialchars($question_data['question_uk']) . '" data-lang-ru="' . htmlspecialchars($question_data['question_ru']) . '">' . htmlspecialchars($question_data['question_uk']) . '</div>' . "\n" .
                    '                    <div class="faq-answer">' . "\n" .
                    '                        <div data-lang-uk="' . htmlspecialchars($question_data['answer_uk']) . '" data-lang-ru="' . htmlspecialchars($question_data['answer_ru']) . '">' . htmlspecialchars($question_data['answer_uk']) . '</div>' . "\n" .
                    '                    </div>' . "\n" .
                    '                </div>';
            }
        }
        
        $new_faq_content .= "\n            </div>";
        
        $new_faq_section = $new_faq_content . "\n        </div>\n    </section>";
        $html_content = preg_replace_callback($faq_section_pattern, fn() => $new_faq_section, $html_content);
    }
    
    // Save updated HTML
    $result = adminAtomicWrite(INDEX_HTML_PATH, $html_content) !== false;
    return $result;
}

// Include language functions (config уже загружен через bootstrap)
include __DIR__ . '/lang_functions.php';

// Load existing content from DB only
$content = [];
$pdo = getDBConnection();
if ($pdo) {
    
    // Читаем данные из БД
    $stmt = $pdo->query("SELECT * FROM content ORDER BY section, language");
    $db_content = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Преобразуем в формат JSON
    foreach ($db_content as $row) {
        $section = $row['section'];
        $lang = $row['language'];
        
        if ($section === 'contacts' || $section === 'footer') {
            // Для contacts и footer используем JSON данные из поля content
            if (!isset($content[$section])) {
                $json_data = json_decode($row['content'], true);
                if ($json_data) {
                    $content[$section] = $json_data;
                }
            }
        } else if (strpos($section, 'faq_') === 0) {
            // Для FAQ используем отдельные поля
            if (!isset($content['faq'])) {
                $content['faq'] = [];
            }
            if (strpos($section, '_answer') !== false) {
                // Это ответ
                $question_num = str_replace('_answer', '', $section);
                $content['faq'][$question_num]['answer_' . $lang] = $row['content'];
            } else {
                // Это вопрос
                $content['faq'][$section]['question_' . $lang] = $row['content'];
            }
        } else {
            // Для остальных секций используем стандартную структуру
            if (!isset($content[$section])) {
                $content[$section] = [];
            }
            $content[$section]["title_$lang"] = $row['title'];
            $content[$section]["description_$lang"] = $row['content'];
        }
    }
} else {
    // Если БД недоступна, показываем ошибку
    $error_message = '❌ Ошибка подключения к базе данных';
    writeLog('content_load', 'Ошибка БД: не удалось подключиться', 'error');
}

// Handle form submissions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !getDBConnection()) adminFail('База данных недоступна. Контент не изменён.',503);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    if (!validateCsrf()) {
        $error_message = 'Недействительный запрос (CSRF). Обновите страницу и попробуйте снова.';
    } else {
    // Handle banner upload
    $banner_uploaded = false;
    if (isset($_FILES['hero_banner']) && $_FILES['hero_banner']['error'] === UPLOAD_ERR_OK) {
        $uploaded_file = $_FILES['hero_banner'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        // Check file type
        if (in_array($uploaded_file['type'], $allowed_types)) {
            // Check file size
            if ($uploaded_file['size'] <= $max_size) {
                $target_path = IMAGES_DIR . '/hero-bg.jpg';
                
                // Create backup of current banner
                if (file_exists($target_path)) {
                    $backup_path = IMAGES_DIR . '/hero-bg-backup-' . date('Y-m-d-H-i-s') . '.jpg';
                    copy($target_path, $backup_path);
                }
                
                // Upload new banner
                if (move_uploaded_file($uploaded_file['tmp_name'], $target_path)) {
                    $banner_uploaded = true;
                    
                    // Force cache refresh by updating file modification time
                    touch($target_path);
                    
                    // Force browser cache refresh by updating CSS with filemtime
                    forceBannerCacheRefresh();
                    
                    // Force preview update by adding a session flag
                    $_SESSION['banner_just_uploaded'] = true;
                    
                    writeLog('upload_banner', "Загружен новый баннер: {$uploaded_file['name']}", 'success');
                } else {
                    writeLog('upload_banner', "Ошибка загрузки баннера: {$uploaded_file['name']}", 'error');
                }
            } else {
                writeLog('upload_banner', "Файл слишком большой: {$uploaded_file['name']} ({$uploaded_file['size']} байт)", 'error');
            }
        } else {
            writeLog('upload_banner', "Неподдерживаемый тип файла: {$uploaded_file['name']} ({$uploaded_file['type']})", 'error');
        }
    }
    
    // Load current content from DB
    error_log("DEBUG: Getting DB connection for content loading");
    $pdo = getDBConnection();
    if ($pdo) {
        error_log("DEBUG: DB connection successful for content loading");
        
        // Читаем данные из БД
        $stmt = $pdo->query("SELECT * FROM content ORDER BY section, language");
        $db_content = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Преобразуем в формат JSON
        foreach ($db_content as $row) {
            $section = $row['section'];
            $lang = $row['language'];
            
            if ($section === 'contacts' || $section === 'footer') {
                // Для contacts и footer используем JSON данные из поля content
                if (!isset($content[$section])) {
                    $json_data = json_decode($row['content'], true);
                    if ($json_data) {
                        $content[$section] = $json_data;
                    }
                }
            } else if (strpos($section, 'faq_') === 0) {
                // Для FAQ используем отдельные поля
                if (!isset($content['faq'])) {
                    $content['faq'] = [];
                }
                if (strpos($section, '_answer') !== false) {
                    // Это ответ
                    $question_num = str_replace('_answer', '', $section);
                    $content['faq'][$question_num]['answer_' . $lang] = $row['content'];
                } else {
                    // Это вопрос
                    $content['faq'][$section]['question_' . $lang] = $row['content'];
                }
            } else {
                // Для остальных секций используем стандартную структуру
                if (!isset($content[$section])) {
                    $content[$section] = [];
                }
                $content[$section]["title_$lang"] = $row['title'];
                $content[$section]["description_$lang"] = $row['content'];
            }
        }
    } else {
        writeLog('save_content', 'Ошибка загрузки из БД: не удалось подключиться', 'error');
        $error_message = '❌ Ошибка загрузки данных из базы данных';
    }
    
    // Update content with form data
    $content['hero'] = [
        'title_uk' => $_POST['hero_title_uk'],
        'title_ru' => $_POST['hero_title_ru'],
        'description_uk' => $_POST['hero_description_uk'],
        'description_ru' => $_POST['hero_description_ru']
    ];
    
    // Update FAQ data
    $content['faq'] = [];
    
    // Support up to 20 FAQ items to allow dynamic addition
    for ($i = 1; $i <= 20; $i++) {
        $question_uk = $_POST["faq_question_{$i}_uk"] ?? '';
        $question_ru = $_POST["faq_question_{$i}_ru"] ?? '';
        $answer_uk = $_POST["faq_answer_{$i}_uk"] ?? '';
        $answer_ru = $_POST["faq_answer_{$i}_ru"] ?? '';
        
        // Only add FAQ if at least question_uk is provided
        if (!empty($question_uk)) {
            $content['faq']["faq_{$i}"] = [
                'question_uk' => $question_uk,
                'question_ru' => $question_ru,
                'answer_uk' => $answer_uk,
                'answer_ru' => $answer_ru
            ];
        }
    }
    
    $content['contacts'] = [
        'phone' => str_replace('+3 (050) 850-20-40', '+38 (050) 850-20-40', $_POST['phone']),
        'email' => $_POST['email'],
        'address_uk' => $_POST['address_uk'],
        'address_ru' => $_POST['address_ru'],
        'facebook' => $_POST['facebook'] ?? '',
        'instagram' => $_POST['instagram'] ?? '',
        'viber' => $_POST['viber'] ?? '',
        'telegram' => $_POST['telegram'] ?? ''
    ];
    
    $content['footer'] = [
        'description_uk' => $_POST['footer_description_uk'],
        'description_ru' => $_POST['footer_description_ru'],
        'description_uk_display' => $_POST['footer_description_uk'],
        'description_ru_display' => $_POST['footer_description_ru'],
        'working_hours_uk' => [
            'weekdays' => $_POST['working_hours_weekdays_uk'],
            'saturday' => $_POST['working_hours_saturday_uk'],
            'sunday' => $_POST['working_hours_sunday_uk']
        ],
        'working_hours_ru' => [
            'weekdays' => $_POST['working_hours_weekdays_ru'],
            'saturday' => $_POST['working_hours_saturday_ru'],
            'sunday' => $_POST['working_hours_sunday_ru']
        ]
    ];
    
    // Save to DB only
    error_log("DEBUG: Starting DB save process");
    $db_saved = false;
    
    $pdo = getDBConnection();
    if ($pdo) {
        error_log("DEBUG: DB connection successful for saving");
        
        // Очищаем старые данные
        $pdo->beginTransaction();
        $pdo->exec("DELETE FROM content");
        
        // Сохраняем новые данные
        $stmt = $pdo->prepare("INSERT INTO content (section, language, title, content) VALUES (?, ?, ?, ?)");
        
        foreach ($content as $section => $data) {
            if ($section === 'hero') {
                // Hero секция
                if (isset($data['title_uk'])) {
                    $stmt->execute([$section, 'uk', $data['title_uk'], $data['description_uk'] ?? '']);
                }
                if (isset($data['title_ru'])) {
                    $stmt->execute([$section, 'ru', $data['title_ru'], $data['description_ru'] ?? '']);
                }
            } else if ($section === 'contacts') {
                // Contacts секция - сохраняем как JSON
                $contacts_json = json_encode($data, JSON_UNESCAPED_UNICODE);
                $stmt->execute([$section, 'uk', 'Контакти', $contacts_json]);
                $stmt->execute([$section, 'ru', 'Контакты', $contacts_json]);
            } else if ($section === 'footer') {
                // Footer секция - сохраняем как JSON
                $footer_json = json_encode($data, JSON_UNESCAPED_UNICODE);
                $stmt->execute([$section, 'uk', 'Підвал', $footer_json]);
                $stmt->execute([$section, 'ru', 'Подвал', $footer_json]);
            } else if ($section === 'faq') {
                // FAQ секция - сохраняем как отдельные поля
                foreach ($data as $faq_key => $faq_data) {
                    if (is_array($faq_data)) {
                        // Сохраняем вопросы
                        if (isset($faq_data['question_uk'])) {
                            $stmt->execute([$faq_key, 'uk', 'FAQ ' . substr($faq_key, -1), $faq_data['question_uk']]);
                        }
                        if (isset($faq_data['question_ru'])) {
                            $stmt->execute([$faq_key, 'ru', 'FAQ ' . substr($faq_key, -1), $faq_data['question_ru']]);
                        }
                        // Сохраняем ответы
                        if (isset($faq_data['answer_uk'])) {
                            $stmt->execute([$faq_key . '_answer', 'uk', 'FAQ ' . substr($faq_key, -1) . ' Answer', $faq_data['answer_uk']]);
                        }
                        if (isset($faq_data['answer_ru'])) {
                            $stmt->execute([$faq_key . '_answer', 'ru', 'FAQ ' . substr($faq_key, -1) . ' Answer', $faq_data['answer_ru']]);
                        }
                    }
                }
            } else {
                // Остальные секции
                if (isset($data['title_uk'])) {
                    $stmt->execute([$section, 'uk', $data['title_uk'], $data['description_uk'] ?? '']);
                }
                if (isset($data['title_ru'])) {
                    $stmt->execute([$section, 'ru', $data['title_ru'], $data['description_ru'] ?? '']);
                }
            }
        }
        $pdo->commit();
        $db_saved = true;
    } else {
        writeLog('save_content', 'Ошибка БД: не удалось подключиться', 'error');
    }
    

    
    $html_updated = $db_saved && updateIndexHtml($content);
    
    $json_generated = false;
    if ($pdo && $db_saved) {
        $json_generated = generateLangFiles($pdo);
    }
    
    if ($db_saved && $html_updated) {
        updateSitemapLastmod();
        $log_message = "Сохранен контент сайта (БД + HTML обновлен)";
        if ($json_generated) {
            $log_message .= " + JSON файлы локализации сгенерированы";
        }
        if ($banner_uploaded) {
            $log_message .= " + загружен новый баннер";
        }
        writeLog('save_content', $log_message, 'success');
        
        // Redirect with success message and active tab
        $active_tab = $_POST['active_tab'] ?? 'hero';
        $success_param = $banner_uploaded ? 'banner_uploaded' : 'saved';
        ob_clean();
        header("Location: content.php?success=" . $success_param . "&tab=" . $active_tab);
        exit;
    } elseif ($db_saved) {
        writeLog('save_content', "Сохранен контент в БД, но не удалось обновить HTML", 'warning');
        
        // Redirect with warning message and active tab
        $active_tab = $_POST['active_tab'] ?? 'hero';
        ob_clean();
        header("Location: content.php?warning=html_update&tab=" . $active_tab);
        exit;
    } else {
        writeLog('save_content', "Ошибка сохранения контента в базу данных", 'error');
        
        // Redirect with error message and active tab
        $active_tab = $_POST['active_tab'] ?? 'hero';
        ob_clean();
        header("Location: content.php?error=save&tab=" . $active_tab);
        exit;
    }
    }
}

// Handle messages from refresh-content.php and form submission
$success_message = '';
$error_message = '';
$warning_message = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'saved':
            $success_message = '✅ Контент успешно сохранен и применен к сайту!';
            break;
        case 'refresh':
            $success_message = '✅ Контент успешно обновлен! Данные извлечены с текущего сайта.';
            break;
        case 'banner_uploaded':
            $success_message = '✅ Баннер успешно загружен и применен к сайту!';
            break;
        case 'cache_refreshed':
            $success_message = '✅ Кеш баннера успешно обновлен! Изображение должно обновиться на сайте.';
            break;
    }
}

if (isset($_GET['warning'])) {
    switch ($_GET['warning']) {
        case 'html_update':
            $warning_message = '⚠️ Контент сохранен в БД, но не удалось обновить сайт';
            break;
        case 'db_error':
            $warning_message = '⚠️ Ошибка подключения к базе данных';
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'save':
            $error_message = '❌ Ошибка при сохранении в базу данных';
            break;
        case 'extract':
            $error_message = '❌ Ошибка при извлечении данных с сайта';
            break;
        case 'html_update':
            $error_message = '❌ Ошибка при обновлении сайта (index.html)';
            break;
        case 'cache_refresh':
            $error_message = '❌ Ошибка при обновлении кеша баннера';
            break;
        case 'invalid_request':
            $error_message = '❌ Неверный запрос';
            break;
        default:
            $error_message = '❌ Произошла неизвестная ошибка';
    }
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование контента - Админ-панель</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
        .header { background: #333; color: white; padding: 20px; }
        .header h1 { margin: 0; }
        .back { float: right; color: white; text-decoration: none; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .nav { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
        .nav a { display: inline-block; padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; flex: 0 0 auto; white-space: nowrap; }
        .nav a:hover { background: #0056b3; }
        .content { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; }
        textarea { height: 100px; resize: vertical; }
        button { background: #28a745; color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #218838; }
        .tabs { display: flex; border-bottom: 1px solid #ddd; margin-bottom: 20px; }
        .tab { padding: 10px 20px; cursor: pointer; border: 1px solid transparent; border-bottom: none; background: #f8f9fa; }
        .tab.active { background: white; border-color: #ddd; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        /* Сворачиваемый info-box */
        .info-box { 
            background: #d1ecf1; 
            color: #0c5460; 
            border-radius: 5px; 
            margin-bottom: 20px; 
            border: 1px solid #bee5eb;
        }
        .info-box-toggle {
            background: none;
            border: none;
            width: 100%;
            text-align: left;
            padding: 15px;
            cursor: pointer;
            font-weight: bold;
            color: #0c5460;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .info-box-toggle:hover {
            background: #c1e7e7;
        }
        .toggle-icon {
            transition: transform 0.3s ease;
        }
        .info-box-toggle.collapsed .toggle-icon {
            transform: rotate(-90deg);
        }
        .info-box-content {
            padding: 15px;
            border-top: 1px solid #bee5eb;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        .info-box-content.collapsed {
            max-height: 0;
            padding-top: 0;
            padding-bottom: 0;
        }
        
        /* Улучшенные отступы для всех секций */
        .tab-content {
            padding: 20px 0;
        }
        .tab-content h3 {
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        .tab-content h4 {
            margin: 20px 0 15px 0;
            color: #495057;
        }
        .form-group {
            margin-bottom: 25px;
            padding: 0 10px;
        }
        .grid > div {
            padding: 0 10px;
        }
        .grid {
            gap: 30px;
        }
        
        /* Banner section styles */
        .banner-section {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .banner-section h4 {
            margin: 0 0 20px 0;
            color: #495057;
            font-size: 18px;
        }
        
        .current-banner {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .current-banner p {
            margin: 0 0 15px 0;
            font-weight: bold;
            color: #495057;
        }
        
        .banner-preview {
            text-align: center;
            overflow: hidden;
        }
        
        .banner-preview img {
            max-width: 300px;
            height: auto;
            border: 2px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .upload-section {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            overflow: hidden;
        }
        
        .upload-section h5 {
            margin: 0 0 15px 0;
            color: #495057;
            font-size: 16px;
        }
        
        .upload-section input[type="file"] {
            border: 2px dashed #007bff;
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            box-sizing: border-box;
            max-width: 100%;
        }
        
        .upload-section input[type="file"]:hover {
            border-color: #0056b3;
            background: #e7f3ff;
        }
        
        .upload-section small {
            color: #6c757d;
            display: block;
            margin-top: 10px;
            font-size: 13px;
            line-height: 1.4;
        }
        
        /* Improved form styles */
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        
        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        
        .form-group input[type="text"]:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
            font-family: inherit;
        }
        
        /* Grid improvements */
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        /* FAQ move buttons styles */
        .faq-move-btn:hover:not(:disabled) {
            background: #5a6268 !important;
            transform: scale(1.05);
        }
        
        .faq-move-btn:active:not(:disabled) {
            transform: scale(0.95);
        }
        
        .faq-item {
            transition: transform 0.2s ease;
        }
        
        .faq-item.moving {
            opacity: 0.7;
            transform: scale(0.98);
        }
        
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📝 Редактирование контента</h1>
        <a href="index.php" class="back">← Назад</a>
    </div>
    
    <div class="container">
        <div class="nav">
            <?php include __DIR__ . '/nav.php'; ?>
        </div>
        
        <div class="content">
            <h2>📝 Редактирование контента</h2>
            
            <div class="info-box">
                <button class="info-box-toggle collapsed" onclick="toggleInfoBox()">
                    <span class="toggle-icon">▶</span> ℹ️ Информация о контенте
                </button>
                <div class="info-box-content collapsed" id="infoBoxContent">
                    <strong>ℹ️ Информация:</strong> Данные загружаются исключительно из базы данных. 
                    Если база данных недоступна, будет показана ошибка подключения.
                    <br><strong>💡 Подсказка:</strong> Оба языка отображаются с метками "(UA)" и "(RU)" для удобства редактирования.
                </div>
            </div>
            
            <?php if (isset($success)): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($warning_message): ?>
                <div class="warning"><?php echo $warning_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <div class="tabs">
                <div class="tab <?php echo $active_tab === 'hero' ? 'active' : ''; ?>" onclick="showTab('hero')">🎯 Главная секция</div>
                <div class="tab <?php echo $active_tab === 'contacts' ? 'active' : ''; ?>" onclick="showTab('contacts')">📞 Контакты</div>
                <div class="tab <?php echo $active_tab === 'faq' ? 'active' : ''; ?>" onclick="showTab('faq')">❓ FAQ</div>
                <div class="tab <?php echo $active_tab === 'footer' ? 'active' : ''; ?>" onclick="showTab('footer')">📄 Подвал</div>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <?php echo getCsrfField(); ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="active_tab" value="<?php echo $active_tab; ?>">
                
                <!-- Hero Section -->
                <div id="hero" class="tab-content <?php echo $active_tab === 'hero' ? 'active' : ''; ?>">
                    <h3>🎯 Главная секция (Hero)</h3>
                    
                    <!-- Banner Management -->
                    <div class="banner-section">
                        <h4>🖼️ Фоновый баннер</h4>
                        
                        <div class="current-banner">
                            <p><strong>Текущий баннер:</strong> hero-bg.jpg</p>
                            <div class="banner-preview">
                                <?php 
                                $banner_file = IMAGES_DIR . '/hero-bg.jpg';
                                $cache_buster = file_exists($banner_file) ? '?v=' . filemtime($banner_file) : '';
                                ?>
                                <img src="../images/hero-bg.jpg<?php echo $cache_buster; ?>" alt="Текущий баннер">
                                <?php if (!file_exists($banner_file)): ?>
                                <p style="color: red; margin-top: 10px;">⚠️ Файл баннера не найден!</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="upload-section">
                            <h5>📤 Заменить баннер</h5>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Выберите новое изображение:</label>
                                <input type="file" name="hero_banner" accept="image/*" onchange="previewBanner(this)">
                                <small>
                                    Рекомендуемый размер: 1920x1080px или больше.<br>
                                    Поддерживаемые форматы: JPG, PNG, WebP.
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">
                    
                    <div class="grid">
                        <div>
                            <div class="form-group">
                                <label>Заголовок (UA):</label>
                                <input type="text" name="hero_title_uk" value="<?php echo htmlspecialchars($content['hero']['title_uk'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Описание (UA):</label>
                                <textarea name="hero_description_uk" required placeholder="Рекомендуется включить: 'Працюємо в Києві, Львові, Дніпрі, Одесі, Чернігові, Вінниці, Хмельницькому, Тернополі та інших містах України'"><?php echo htmlspecialchars($content['hero']['description_uk'] ?? ''); ?></textarea>
                                <small style="color: #666; font-size: 12px;">💡 Рекомендуется включить список городов для SEO</small>
                            </div>
                        </div>
                        <div>
                            <div class="form-group">
                                <label>Заголовок (RU):</label>
                                <input type="text" name="hero_title_ru" value="<?php echo htmlspecialchars($content['hero']['title_ru'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Описание (RU):</label>
                                <textarea name="hero_description_ru" required placeholder="Рекомендуется включить: 'Работаем в Киеве, Львове, Днепре, Одессе, Чернигове, Виннице, Хмельницком, Тернополе и других городах Украины'"><?php echo htmlspecialchars($content['hero']['description_ru'] ?? ''); ?></textarea>
                                <small style="color: #666; font-size: 12px;">💡 Рекомендуется включить список городов для SEO</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Contacts Section -->
                <div id="contacts" class="tab-content <?php echo $active_tab === 'contacts' ? 'active' : ''; ?>">
                    <h3>📞 Контактная информация</h3>
                    <div class="grid">
                        <div>
                            <div class="form-group">
                                <label>Телефон:</label>
                                <input type="text" name="phone" value="<?php echo htmlspecialchars($content['contacts']['phone'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Служебный email (на сайте не публикуется):</label>
                                <input type="text" name="email" value="<?php echo htmlspecialchars($content['contacts']['email'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Адрес (UA):</label>
                                <input type="text" name="address_uk" value="<?php echo htmlspecialchars($content['contacts']['address_uk'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Адрес (RU):</label>
                                <input type="text" name="address_ru" value="<?php echo htmlspecialchars($content['contacts']['address_ru'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div>
                            <h4>🌐 Социальные сети:</h4>
                            <div class="form-group">
                                <label>Facebook:</label>
                                <input type="url" name="facebook" value="<?php echo htmlspecialchars($content['contacts']['facebook'] ?? ''); ?>" placeholder="https://www.facebook.com/...">
                            </div>
                            <div class="form-group">
                                <label>Instagram:</label>
                                <input type="url" name="instagram" value="<?php echo htmlspecialchars($content['contacts']['instagram'] ?? ''); ?>" placeholder="https://www.instagram.com/...">
                            </div>
                            <div class="form-group">
                                <label>Viber:</label>
                                <input type="text" name="viber" value="<?php echo htmlspecialchars($content['contacts']['viber'] ?? ''); ?>" placeholder="viber://chat?number=+380...">
                            </div>
                            <div class="form-group">
                                <label>Telegram:</label>
                                <input type="url" name="telegram" value="<?php echo htmlspecialchars($content['contacts']['telegram'] ?? ''); ?>" placeholder="https://t.me/...">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- FAQ Section -->
                <div id="faq" class="tab-content <?php echo $active_tab === 'faq' ? 'active' : ''; ?>">
                    <h3>❓ Часто задаваемые вопросы (FAQ)</h3>
                    <p><em>Управление вопросами и ответами для раздела FAQ. Оставьте вопрос пустым, чтобы удалить FAQ.</em></p>
                    <p style="color: #666; font-size: 14px;">💡 <strong>Подсказка:</strong> Можете добавлять новые FAQ, просто заполнив пустые поля ниже. Максимум 20 вопросов.</p>
                    
                    <div class="faq-items" id="faq-items">
                        <?php 
                        // Count existing FAQ items
                        $faq_count = 0;
                        if (isset($content['faq'])) {
                            foreach ($content['faq'] as $key => $faq) {
                                if (strpos($key, 'faq_') === 0 && !empty($faq['question_uk'])) {
                                    $faq_count++;
                                }
                            }
                        }
                        // Show at least 5 FAQ slots, or more if there are existing ones
                        $max_slots = max(5, $faq_count + 2);
                        
                        for ($i = 1; $i <= min(20, $max_slots); $i++): 
                            $faq_data = $content['faq']["faq_{$i}"] ?? [];
                        ?>
                        <div class="faq-item" data-faq-index="<?php echo $i; ?>" style="border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; border-radius: 8px; background: <?php echo !empty($faq_data['question_uk'] ?? '') ? '#f9f9f9' : '#fff'; ?>; position: relative;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <h4 style="margin: 0;">Вопрос <?php echo $i; ?> <?php if (!empty($faq_data['question_uk'] ?? '')): ?><span style="color: green; font-size: 12px;">✓ Заполнен</span><?php endif; ?></h4>
                                <div style="display: flex; gap: 5px;">
                                    <button type="button" onclick="moveFaqUp(<?php echo $i; ?>)" class="faq-move-btn" title="Переместить вверх" <?php echo $i === 1 ? 'disabled' : ''; ?> style="padding: 5px 10px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 14px;">↑</button>
                                    <button type="button" onclick="moveFaqDown(<?php echo $i; ?>)" class="faq-move-btn" title="Переместить вниз" style="padding: 5px 10px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 14px;">↓</button>
                                </div>
                            </div>
                            <div class="grid">
                                <div>
                                    <div class="form-group">
                                        <label>Вопрос (UA):</label>
                                        <input type="text" name="faq_question_<?php echo $i; ?>_uk" value="<?php echo htmlspecialchars($faq_data['question_uk'] ?? ''); ?>" placeholder="Введите вопрос на украинском...">
                                    </div>
                                    <div class="form-group">
                                        <label>Ответ (UA):</label>
                                        <textarea name="faq_answer_<?php echo $i; ?>_uk" placeholder="Введите ответ на украинском..."><?php echo htmlspecialchars($faq_data['answer_uk'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                <div>
                                    <div class="form-group">
                                        <label>Вопрос (RU):</label>
                                        <input type="text" name="faq_question_<?php echo $i; ?>_ru" value="<?php echo htmlspecialchars($faq_data['question_ru'] ?? ''); ?>" placeholder="Введите вопрос на русском...">
                                    </div>
                                    <div class="form-group">
                                        <label>Ответ (RU):</label>
                                        <textarea name="faq_answer_<?php echo $i; ?>_ru" placeholder="Введите ответ на русском..."><?php echo htmlspecialchars($faq_data['answer_ru'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                    
                    <button type="button" onclick="addFaqSlot()" style="background: #17a2b8; margin-top: 20px;">➕ Добавить еще один FAQ</button>
                </div>
                
                <!-- Footer Section -->
                <div id="footer" class="tab-content <?php echo $active_tab === 'footer' ? 'active' : ''; ?>">
                    <h3>📄 Подвал (Footer)</h3>
                    <div class="grid">
                        <div>
                            <div class="form-group">
                                <label>Описание компании (UA):</label>
                                <textarea name="footer_description_uk" required><?php echo htmlspecialchars($content['footer']['description_uk'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Описание компании (RU):</label>
                                <textarea name="footer_description_ru" required><?php echo htmlspecialchars($content['footer']['description_ru'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div>
                            <h4>Время работы (UA):</h4>
                            <div class="form-group">
                                <label>Будни:</label>
                                <input type="text" name="working_hours_weekdays_uk" value="<?php echo htmlspecialchars($content['footer']['working_hours_uk']['weekdays'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Суббота:</label>
                                <input type="text" name="working_hours_saturday_uk" value="<?php echo htmlspecialchars($content['footer']['working_hours_uk']['saturday'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Воскресенье:</label>
                                <input type="text" name="working_hours_sunday_uk" value="<?php echo htmlspecialchars($content['footer']['working_hours_uk']['sunday'] ?? ''); ?>" required>
                            </div>
                            
                            <h4>Время работы (RU):</h4>
                            <div class="form-group">
                                <label>Будни:</label>
                                <input type="text" name="working_hours_weekdays_ru" value="<?php echo htmlspecialchars($content['footer']['working_hours_ru']['weekdays'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Суббота:</label>
                                <input type="text" name="working_hours_saturday_ru" value="<?php echo htmlspecialchars($content['footer']['working_hours_ru']['saturday'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Воскресенье:</label>
                                <input type="text" name="working_hours_sunday_ru" value="<?php echo htmlspecialchars($content['footer']['working_hours_ru']['sunday'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="submit">💾 Сохранить все изменения</button>
            </form>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            console.log('showTab called with:', tabName);
            
            // Hide all tab contents
            var tabContents = document.getElementsByClassName('tab-content');
            for (var i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }
            
            // Remove active class from all tabs
            var tabs = document.getElementsByClassName('tab');
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to selected tab
            event.target.classList.add('active');
            
            // Update hidden input
            document.querySelector('input[name="active_tab"]').value = tabName;
        }
        
        function toggleInfoBox() {
            var toggle = document.querySelector('.info-box-toggle');
            var content = document.getElementById('infoBoxContent');
            
            toggle.classList.toggle('collapsed');
            content.classList.toggle('collapsed');
        }
        
        // Function to preview selected banner file
        function previewBanner(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    var bannerImg = document.querySelector('.banner-preview img');
                    if (bannerImg) {
                        bannerImg.src = e.target.result;
                        console.log('Banner preview updated with selected file');
                    }
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Single handler for banner preview update after upload
        <?php if ((isset($_GET['success']) && $_GET['success'] === 'banner_uploaded') || (isset($_SESSION['banner_just_uploaded']) && $_SESSION['banner_just_uploaded'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                var bannerImg = document.querySelector('.banner-preview img');
                if (bannerImg) {
                    var timestamp = new Date().getTime();
                    bannerImg.src = '../images/hero-bg.jpg?v=' + timestamp;
                    console.log('Banner preview updated after upload');
                }
            }, 800);
        });
        <?php 
        // Clear the session flag
        if (isset($_SESSION['banner_just_uploaded'])) {
            unset($_SESSION['banner_just_uploaded']);
        }
        endif; 
        ?>
        
        // Function to add new FAQ slot
        function addFaqSlot() {
            var faqItems = document.getElementById('faq-items');
            var currentCount = faqItems.children.length;
            
            if (currentCount >= 20) {
                alert('Достигнут максимум 20 FAQ вопросов');
                return;
            }
            
            var newSlot = document.createElement('div');
            newSlot.className = 'faq-item';
            newSlot.setAttribute('data-faq-index', currentCount + 1);
            newSlot.style.cssText = 'border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; border-radius: 8px; background: #fff; position: relative;';
            
            var slotNumber = currentCount + 1;
            newSlot.innerHTML = 
                '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">' +
                    '<h4 style="margin: 0;">Вопрос ' + slotNumber + '</h4>' +
                    '<div style="display: flex; gap: 5px;">' +
                        '<button type="button" onclick="moveFaqUp(' + slotNumber + ')" class="faq-move-btn" title="Переместить вверх" style="padding: 5px 10px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 14px;">↑</button>' +
                        '<button type="button" onclick="moveFaqDown(' + slotNumber + ')" class="faq-move-btn" title="Переместить вниз" style="padding: 5px 10px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 14px;">↓</button>' +
                    '</div>' +
                '</div>' +
                '<div class="grid">' +
                    '<div>' +
                        '<div class="form-group">' +
                            '<label>Вопрос (UA):</label>' +
                            '<input type="text" name="faq_question_' + slotNumber + '_uk" placeholder="Введите вопрос на украинском...">' +
                        '</div>' +
                        '<div class="form-group">' +
                            '<label>Ответ (UA):</label>' +
                            '<textarea name="faq_answer_' + slotNumber + '_uk" placeholder="Введите ответ на украинском..."></textarea>' +
                        '</div>' +
                    '</div>' +
                    '<div>' +
                        '<div class="form-group">' +
                            '<label>Вопрос (RU):</label>' +
                            '<input type="text" name="faq_question_' + slotNumber + '_ru" placeholder="Введите вопрос на русском...">' +
                        '</div>' +
                        '<div class="form-group">' +
                            '<label>Ответ (RU):</label>' +
                            '<textarea name="faq_answer_' + slotNumber + '_ru" placeholder="Введите ответ на русском..."></textarea>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            
            faqItems.appendChild(newSlot);
            
            // Scroll to new slot
            newSlot.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            
            // Update move buttons state
            updateMoveButtons();
        }
        
        // Function to move FAQ up
        function moveFaqUp(index) {
            var faqItems = document.getElementById('faq-items');
            var items = Array.from(faqItems.children);
            var currentItem = items.find(function(item) {
                return parseInt(item.getAttribute('data-faq-index')) === index;
            });
            
            if (!currentItem || index === 1) {
                return;
            }
            
            var prevItem = items[items.indexOf(currentItem) - 1];
            if (prevItem) {
                currentItem.classList.add('moving');
                setTimeout(function() {
                    faqItems.insertBefore(currentItem, prevItem);
                    renumberFaqItems();
                    updateMoveButtons();
                    currentItem.classList.remove('moving');
                    
                    // Smooth scroll to new position
                    currentItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }, 100);
            }
        }
        
        // Function to move FAQ down
        function moveFaqDown(index) {
            var faqItems = document.getElementById('faq-items');
            var items = Array.from(faqItems.children);
            var currentItem = items.find(function(item) {
                return parseInt(item.getAttribute('data-faq-index')) === index;
            });
            
            if (!currentItem) {
                return;
            }
            
            var nextItem = items[items.indexOf(currentItem) + 1];
            if (nextItem) {
                currentItem.classList.add('moving');
                setTimeout(function() {
                    faqItems.insertBefore(nextItem, currentItem);
                    renumberFaqItems();
                    updateMoveButtons();
                    currentItem.classList.remove('moving');
                    
                    // Smooth scroll to new position
                    currentItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }, 100);
            }
        }
        
        // Function to renumber FAQ items and update input names
        function renumberFaqItems() {
            var faqItems = document.getElementById('faq-items');
            var items = Array.from(faqItems.children);
            
            items.forEach(function(item, newIndex) {
                var oldIndex = parseInt(item.getAttribute('data-faq-index'));
                var newNumber = newIndex + 1;
                
                // Update data attribute
                item.setAttribute('data-faq-index', newNumber);
                
                // Update heading
                var heading = item.querySelector('h4');
                if (heading) {
                    var headingText = heading.innerHTML;
                    headingText = headingText.replace(/Вопрос \d+/, 'Вопрос ' + newNumber);
                    heading.innerHTML = headingText;
                }
                
                // Update input names if index changed
                if (oldIndex !== newNumber) {
                    var inputs = item.querySelectorAll('input, textarea');
                    inputs.forEach(function(input) {
                        if (input.name) {
                            input.name = input.name.replace(/_(\d+)_/, '_' + newNumber + '_');
                        }
                    });
                }
                
                // Update button onclick handlers
                var upBtn = item.querySelector('button[onclick*="moveFaqUp"]');
                var downBtn = item.querySelector('button[onclick*="moveFaqDown"]');
                if (upBtn) {
                    upBtn.setAttribute('onclick', 'moveFaqUp(' + newNumber + ')');
                }
                if (downBtn) {
                    downBtn.setAttribute('onclick', 'moveFaqDown(' + newNumber + ')');
                }
            });
        }
        
        // Function to update move buttons disabled state
        function updateMoveButtons() {
            var faqItems = document.getElementById('faq-items');
            var items = Array.from(faqItems.children);
            
            items.forEach(function(item, index) {
                var upBtn = item.querySelector('button[onclick*="moveFaqUp"]');
                var downBtn = item.querySelector('button[onclick*="moveFaqDown"]');
                
                if (upBtn) {
                    upBtn.disabled = (index === 0);
                    upBtn.style.opacity = upBtn.disabled ? '0.5' : '1';
                    upBtn.style.cursor = upBtn.disabled ? 'not-allowed' : 'pointer';
                }
                
                if (downBtn) {
                    downBtn.disabled = (index === items.length - 1);
                    downBtn.style.opacity = downBtn.disabled ? '0.5' : '1';
                    downBtn.style.cursor = downBtn.disabled ? 'not-allowed' : 'pointer';
                }
            });
        }
        
        // Initialize move buttons state on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateMoveButtons();
        });
    </script>
</body>
</html> 