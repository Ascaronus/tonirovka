<?php
require_once __DIR__.'/admin_runtime.php';
/**
 * Language Files Generator
 * Генерирует JSON файлы локализации из базы данных
 */

// Function to generate language JSON files from database
function generateLangFiles($pdo) {
    $langs_dir = defined('LANGS_DIR') ? LANGS_DIR : (__DIR__ . '/../langs');
    
    // Создаем папку langs если её нет
    if (!is_dir($langs_dir)) {
        mkdir($langs_dir, 0755, true);
    }
    
    $translations = [
        'uk' => [],
        'ru' => []
    ];
    
    try {
        // 1. Читаем контент из таблицы content
        $stmt = $pdo->query("SELECT * FROM content ORDER BY section, language");
        $content_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($content_rows as $row) {
            $section = $row['section'];
            $lang = $row['language'];
            
            if ($section === 'hero') {
                $translations[$lang]['hero'] = [
                    'title' => $row['title'] ?? '',
                    'description' => $row['content'] ?? ''
                ];
            } else if ($section === 'contacts') {
                $contacts_data = json_decode(str_replace('+3 (050) 850-20-40', '+38 (050) 850-20-40', $row['content']), true);
                if ($contacts_data) {
                    $translations[$lang]['contacts'] = $contacts_data;
                }
            } else if ($section === 'footer') {
                $footer_data = json_decode(str_replace('+3 (050) 850-20-40', '+38 (050) 850-20-40', $row['content']), true);
                if ($footer_data) {
                    $translations[$lang]['footer'] = $footer_data;
                }
            } else if (strpos($section, 'faq_') === 0) {
                if (!isset($translations[$lang]['faq'])) {
                    $translations[$lang]['faq'] = [];
                }
                if (strpos($section, '_answer') !== false) {
                    $question_num = str_replace('_answer', '', $section);
                    if (!isset($translations[$lang]['faq'][$question_num])) {
                        $translations[$lang]['faq'][$question_num] = [];
                    }
                    $translations[$lang]['faq'][$question_num]['answer'] = $row['content'];
                } else {
                    if (!isset($translations[$lang]['faq'][$section])) {
                        $translations[$lang]['faq'][$section] = [];
                    }
                    $translations[$lang]['faq'][$section]['question'] = $row['content'];
                }
            }
        }
        
        // 2. Читаем цены из таблицы prices
        $stmt = $pdo->query("SELECT * FROM prices ORDER BY sort_order");
        $prices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $translations['uk']['prices'] = [];
        $translations['ru']['prices'] = [];
        foreach ($prices as $price) {
            $translations['uk']['prices'][] = [
                'id' => $price['id'],
                'name' => $price['service_uk'] ?? '',
                'price' => $price['price_uk'] ?? ''
            ];
            $translations['ru']['prices'][] = [
                'id' => $price['id'],
                'name' => $price['service_ru'] ?? '',
                'price' => !empty($price['price_ru']) ? $price['price_ru'] : ($price['price_uk'] ?? '')
            ];
        }
        
        // 3. Читаем пленки из таблицы films
        $stmt = $pdo->query("SELECT * FROM films ORDER BY sort_order");
        $films = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $translations['uk']['films'] = [];
        $translations['ru']['films'] = [];
        foreach ($films as $film) {
            $translations['uk']['films'][] = [
                'id' => $film['id'],
                'title' => $film['title_uk'] ?? $film['name_uk'] ?? '',
                'description' => $film['description_uk'] ?? '',
                'alt' => $film['alt_uk'] ?? '',
                'features' => json_decode($film['features_uk'] ?? '[]', true) ?: []
            ];
            $translations['ru']['films'][] = [
                'id' => $film['id'],
                'title' => $film['title_ru'] ?? $film['name_ru'] ?? '',
                'description' => $film['description_ru'] ?? '',
                'alt' => $film['alt_ru'] ?? '',
                'features' => json_decode($film['features_ru'] ?? '[]', true) ?: []
            ];
        }
        
        // 4. Читаем галерею из таблицы gallery
        $stmt = $pdo->query("SELECT * FROM gallery ORDER BY sort_order");
        $gallery = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $translations['uk']['gallery'] = [];
        $translations['ru']['gallery'] = [];
        foreach ($gallery as $item) {
            $translations['uk']['gallery'][] = [
                'id' => $item['id'],
                'title' => $item['title_uk'] ?? '',
                'alt' => $item['alt_uk'] ?? ''
            ];
            $translations['ru']['gallery'][] = [
                'id' => $item['id'],
                'title' => $item['title_ru'] ?? '',
                'alt' => $item['alt_ru'] ?? ''
            ];
        }
        
        // 5. Добавляем навигацию и общие элементы
        $translations['uk']['nav'] = [
            'films' => 'Плівки',
            'pricing' => 'Ціни',
            'gallery' => 'Галерея',
            'cities' => 'Міста',
            'faq' => 'FAQ',
            'contacts' => 'Контакти'
        ];
        $translations['ru']['nav'] = [
            'films' => 'Пленки',
            'pricing' => 'Цены',
            'gallery' => 'Галерея',
            'cities' => 'Города',
            'faq' => 'FAQ',
            'contacts' => 'Контакты'
        ];
        
        $translations['uk']['common'] = [
            'phone_label' => 'Телефон:',
            'email_label' => 'Email:',
            'address_label' => 'Адреса:'
        ];
        $translations['ru']['common'] = [
            'phone_label' => 'Телефон:',
            'email_label' => 'Email:',
            'address_label' => 'Адрес:'
        ];
        
        // 6. Сохраняем JSON файлы
        $uk_json = json_encode($translations['uk'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $ru_json = json_encode($translations['ru'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        $uk_file = $langs_dir . '/uk.json';
        $ru_file = $langs_dir . '/ru.json';
        
        $uk_saved = adminAtomicWrite($uk_file, $uk_json) !== false;
        $ru_saved = adminAtomicWrite($ru_file, $ru_json) !== false;
        
        if ($uk_saved && $ru_saved) {
            if (function_exists('writeLog')) {
                writeLog('generate_lang_files', "JSON файлы локализации успешно сгенерированы (uk.json, ru.json)", 'success');
            }
            return true;
        } else {
            if (function_exists('writeLog')) {
                writeLog('generate_lang_files', "Ошибка сохранения JSON файлов локализации", 'error');
            }
            return false;
        }
        
    } catch (PDOException $e) {
        if (function_exists('writeLog')) {
            writeLog('generate_lang_files', "Ошибка БД при генерации JSON: " . $e->getMessage(), 'error');
        }
        return false;
    } catch (Exception $e) {
        if (function_exists('writeLog')) {
            writeLog('generate_lang_files', "Ошибка при генерации JSON: " . $e->getMessage(), 'error');
        }
        return false;
    }
}

