<?php
require_once __DIR__ . '/bootstrap.php';

// Функция для загрузки настроек из БД
function loadSettingsFromDB($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM settings ORDER BY id DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // Убираем логирование при загрузке настроек - это происходит при каждом открытии страницы
            
            return [
                'site' => [
                    'title_uk' => $row['site_title_uk'] ?? '',
                    'title_ru' => $row['site_title_ru'] ?? '',
                    'description_uk' => $row['site_description_uk'] ?? '',
                    'description_ru' => $row['site_description_ru'] ?? '',
                    'keywords_uk' => $row['site_keywords_uk'] ?? '',
                    'keywords_ru' => $row['site_keywords_ru'] ?? ''
                ],
                'admin' => [
                    'username' => $row['admin_username'] ?? 'admin',
                    'email' => $row['admin_email'] ?? 'admin@tonirovka.kh.ua'
                ],
                'seo' => [
                    'google_analytics' => $row['google_analytics'] ?? '',
                    'google_verification' => $row['google_verification'] ?? ''
                ],
                'version' => $row['version'] ?? '3.0'
            ];
        } else {
            // Убираем логирование при использовании значений по умолчанию - это нормальная ситуация
        }
    } catch (PDOException $e) {
        // Логируем только критические ошибки БД при загрузке настроек
        writeDBErrorLog('Загрузка настроек из БД', $e, "SELECT * FROM settings ORDER BY id DESC LIMIT 1");
    }
    
    return [
        'site' => [
            'title_uk' => 'Тонування вікон у Харкові — архітектурні, захисні й бронювальні плівки',
            'title_ru' => 'Тонировка окон Харьков | Архитектурные, защитные и бронирующие плёнки',
            'description_uk' => 'Професійне встановлення архітектурних та бронювальних плівок у Харкові. Захист від сонця, енергозбереження, безпека. Гарантія якості.',
            'description_ru' => 'Профессиональная установка архитектурных и бронирующих пленок в Харькове. Защита от солнца, энергосбережение, безопасность. Гарантия качества.',
            'keywords_uk' => 'тоніровка вікон, архітектурні плівки, бронювальні плівки, Харків, встановлення плівок, захисні плівки, дзеркальні плівки',
            'keywords_ru' => 'тонировка окон, архитектурные пленки, бронирующие пленки, Харьков, установка пленок, защитные пленки, зеркальные пленки'
        ],
        'admin' => [
            'username' => 'admin',
            'email' => 'admin@tonirovka.kh.ua'
        ],
        'seo' => [
            'google_analytics' => '',
            'google_verification' => ''
        ],
        'version' => '2.2'
    ];
}

// Функция для сохранения настроек в БД
function saveSettingsToDB($pdo, $settings) {
    try {
        // Очищаем таблицу перед сохранением (оставляем только одну запись)
        $pdo->beginTransaction();
        $pdo->exec("DELETE FROM settings");
        
        $stmt = $pdo->prepare("INSERT INTO settings (site_title_uk, site_title_ru, site_description_uk, site_description_ru, site_keywords_uk, site_keywords_ru, admin_username, admin_email, google_analytics, google_verification, version) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $params = [
            $settings['site']['title_uk'],
            $settings['site']['title_ru'],
            $settings['site']['description_uk'],
            $settings['site']['description_ru'],
            $settings['site']['keywords_uk'],
            $settings['site']['keywords_ru'],
            $settings['admin']['username'],
            $settings['admin']['email'],
            $settings['seo']['google_analytics'],
            $settings['seo']['google_verification'],
            $settings['version'] ?? '3.0'
        ];
        
        $result = $stmt->execute($params);
        $pdo->commit();
        
        if ($result) {
            // Убираем логирование из функции - теперь логирование происходит в основной логике
        } else {
            // Логируем только критические ошибки SQL
            writeWarningLog('Сохранение настроек в БД', 'Ошибка выполнения SQL запроса', [
                'table' => 'settings',
                'function' => 'saveSettingsToDB',
                'sql_error' => $stmt->errorInfo()
            ]);
        }
        
        return $result;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        writeDBErrorLog('Сохранение настроек в БД', $e, "INSERT INTO settings", $params ?? []);
        return false;
    }
}

// Загружаем настройки из БД
$pdo = getDBConnection();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !$pdo && !in_array($_POST['action'] ?? '', ['change_password','delete_backup'], true)) adminFail('База данных недоступна.',503);
if ($pdo) {
    $settings = loadSettingsFromDB($pdo);
} else {
    // Показываем ошибку подключения к БД
    $error = 'Ошибка подключения к базе данных';
    $settings = [
        'site' => [
            'title_uk' => 'Тонування вікон у Харкові — архітектурні, захисні й бронювальні плівки',
            'title_ru' => 'Тонировка окон Харьков | Архитектурные, защитные и бронирующие плёнки',
            'description_uk' => 'Професійне встановлення архітектурних та бронювальних плівок у Харкові. Захист від сонця, енергозбереження, безпека. Гарантія якості.',
            'description_ru' => 'Профессиональная установка архитектурных и бронирующих пленок в Харькове. Защита от солнца, энергосбережение, безопасность. Гарантия качества.',
            'keywords_uk' => 'тоніровка вікон, архітектурні плівки, бронювальні плівки, Харків, встановлення плівок, захисні плівки, дзеркальні плівки',
            'keywords_ru' => 'тонировка окон, архитектурные пленки, бронирующие пленки, Харьков, установка пленок, защитные пленки, зеркальные пленки'
        ],
        'admin' => [
            'username' => 'admin',
            'email' => 'admin@tonirovka.kh.ua'
        ],
        'seo' => [
            'google_analytics' => '',
            'google_verification' => ''
        ],
        'version' => '3.0'
    ];
}

// Функция для обновления index.html из настроек
function updateIndexHtmlFromSettings($settings) {
    require_once __DIR__.'/seo_tools.php';
    try {
        $meta=[];foreach(['uk','ru'] as $lang)$meta[$lang]=['title'=>$settings['site']['title_'.$lang],'description'=>$settings['site']['description_'.$lang]];
        $html=seoApplyMeta(file_get_contents(INDEX_HTML_PATH),$meta);
        $verification=trim($settings['seo']['google_verification']??'');
        $html=preg_replace('~<meta\b[^>]*name="google-site-verification"[^>]*>~','',$html);
        if($verification!=='')$html=str_replace('</head>','<meta name="google-site-verification" content="'.adminEscape($verification).'">'."\n</head>",$html);
        $ga=trim($settings['seo']['google_analytics']??'');
        if($ga!==''&&!preg_match('/^G-[A-Z0-9]+$/D',$ga))throw new RuntimeException('Укажите идентификатор GA4 в формате G-XXXXXXXX.');
        $html=preg_replace('~<script\b[^>]*src="https://www\.googletagmanager\.com/gtag/js\?id=[^"]*"[^>]*>\s*</script>~','',$html);
        $html=preg_replace('~<script>\s*window\.dataLayer.*?</script>~s','',$html);
        if($ga!=='')$html=str_replace('</head>','<script async src="https://www.googletagmanager.com/gtag/js?id='.$ga.'"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","'.$ga.'");</script></head>',$html);
        adminAtomicWrite(INDEX_HTML_PATH,$html);return true;
    } catch(Throwable $e){error_log($e->getMessage());return false;}
}

// Функция для извлечения данных из index.html
function extractSettingsFromIndex() {
    $index_file = INDEX_HTML_PATH;
    if (!file_exists($index_file)) {
        return false;
    }
    
    $content = file_get_contents($index_file);
    $settings = [];
    
    // Извлекаем title
    if (preg_match('/<title[^>]*data-lang-uk="([^"]*)"[^>]*data-lang-ru="([^"]*)"[^>]*>/', $content, $matches)) {
        $settings['site']['title_uk'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $settings['site']['title_ru'] = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    // Извлекаем description
    if (preg_match('/<meta name="description"[^>]*data-lang-uk="([^"]*)"[^>]*data-lang-ru="([^"]*)"[^>]*>/', $content, $matches)) {
        $settings['site']['description_uk'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $settings['site']['description_ru'] = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    // Извлекаем keywords
    if (preg_match('/<meta name="keywords"[^>]*content="([^"]*)"[^>]*>/', $content, $matches)) {
        $settings['site']['keywords_uk'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    // Извлекаем русские keywords
    if (preg_match('/<meta name="keywords"[^>]*lang="ru"[^>]*content="([^"]*)"[^>]*>/', $content, $matches)) {
        $settings['site']['keywords_ru'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    // Извлекаем Google verification
    if (preg_match('/<meta name="google-site-verification"[^>]*content="([^"]*)"[^>]*>/', $content, $matches)) {
        $settings['seo']['google_verification'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    return $settings;
}

// Функция для ограничения количества бэкапов (оставляет только последние 5)
function limitBackups($max_backups = 5) {
    $backup_dir = '../data/backups';
    if (!is_dir($backup_dir)) {
        return;
    }
    
    $files = glob($backup_dir . '/backup_*.json');
    if (count($files) <= $max_backups) {
        return;
    }
    
    // Сортируем файлы по времени создания (старые сначала)
    usort($files, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    
    // Удаляем старые файлы, оставляя только последние $max_backups
    $files_to_delete = array_slice($files, 0, count($files) - $max_backups);
    foreach ($files_to_delete as $file) {
        unlink($file);
    }
}

// Функция для проверки, нужно ли создавать автоматический бэкап
function shouldCreateAutoBackup() {
    $backup_dir = '../data/backups';
    $last_backup_file = '../data/last_auto_backup.txt';
    
    // Если файл с датой последнего бэкапа не существует, создаем бэкап
    if (!file_exists($last_backup_file)) {
        return true;
    }
    
    $last_backup_date = file_get_contents($last_backup_file);
    $current_date = date('Y-m-d');
    
    // Если прошло больше суток с последнего бэкапа, создаем новый
    return $last_backup_date !== $current_date;
}

// Функция для обновления даты последнего автоматического бэкапа
function updateLastAutoBackupDate() {
    $last_backup_file = '../data/last_auto_backup.txt';
    file_put_contents($last_backup_file, date('Y-m-d'));
}

// Обновленная функция создания бэкапа базы данных
function createBackup($is_manual = false) {
    $backup_dir = '../data/backups';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $backup_data = [];
    $saved_tables = [];
    
    // Получаем информацию о базе данных из конфигурации
    $database = DB_DATABASE;
    $host = DB_HOST;
    
    $pdo = getDBConnection();
    if ($pdo) {
        
        // Список таблиц для бэкапа
        $tables = ['settings', 'content', 'films', 'gallery', 'prices'];
        
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("SELECT * FROM `$table`");
                $table_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($table_data)) {
                    $backup_data[$table] = $table_data;
                    $saved_tables[] = $table;
                }
            } catch (PDOException $e) {
                // Логируем ошибку, но продолжаем с другими таблицами
                error_log("Ошибка при бэкапе таблицы $table: " . $e->getMessage());
            }
        }
        
        // Добавляем информацию о бэкапе
        $backup_data['backup_info'] = [
            'created_at' => date('Y-m-d H:i:s'),
            'version' => '3.3',
            'tables_count' => count($saved_tables),
            'saved_tables' => $saved_tables,
            'total_records' => 0,
            'backup_type' => $is_manual ? 'manual' : 'auto',
            'source' => 'database',
            'database' => $database,
            'host' => $host
        ];
        
        // Подсчитываем общее количество записей
        foreach ($saved_tables as $table) {
            $backup_data['backup_info']['total_records'] += count($backup_data[$table]);
        }
        
        $backup_file = $backup_dir . '/backup_' . $timestamp . '.json';
        $result = file_put_contents($backup_file, json_encode($backup_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        
        if ($result !== false) {
            // Ограничиваем количество бэкапов
            limitBackups(5);
            
            // Если это автоматический бэкап, обновляем дату
            if (!$is_manual) {
                updateLastAutoBackupDate();
            }
            
            return ['file' => $backup_file, 'saved_tables' => $saved_tables];
        }
    } else {
        error_log("Ошибка подключения к БД при создании бэкапа: не удалось подключиться");
        return false;
    }
    
    return false;
}

// Функция для создания бэкапа кода (PHP файлов)
function createCodeBackup() {
    $backup_dir = '../data/backups';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $code_backup_data = [];
    $saved_files = [];
    
    // Список PHP файлов для бэкапа (только код, без данных)
    $code_files = [
        'settings' => 'settings.php',
        'index' => 'index.php',
        'prices' => 'prices.php',
        'gallery' => 'gallery.php',
        'films' => 'films.php',
        'content' => 'content.php',
        'changelog' => 'changelog.php'
    ];
    
    foreach ($code_files as $type => $file) {
        if (file_exists($file)) {
            $content = file_get_contents($file);
            if ($content !== false) {
                // Сохраняем только код, без данных
                // Удаляем возможные встроенные данные из PHP файлов
                $clean_content = $content;
                
                // Щадящая очистка - удаляем только конкретные строки загрузки данных
                // Удаляем строки с загрузкой JSON файлов, но оставляем весь остальной код
                $clean_content = preg_replace('/\$.*?=.*?json_decode.*?file_get_contents.*?\.json.*?;/s', '', $clean_content);
                $clean_content = preg_replace('/\$.*?=.*?file_get_contents.*?\.json.*?;/s', '', $clean_content);
                
                // Удаляем только строки с json_decode, но не все подряд
                $clean_content = preg_replace('/\$.*?=.*?json_decode.*?file_get_contents.*?;/s', '', $clean_content);
                
                // Если после очистки файл стал пустым, возвращаем оригинальное содержимое
                if (trim($clean_content) === '') {
                    $clean_content = $content;
                }
                
                $code_backup_data[$type] = $content; // A backup must preserve the exact source.
                $saved_files[] = $type;
            } else {
                // Логируем ошибку чтения файла
                error_log("Ошибка чтения файла: {$file}");
            }
        } else {
            // Логируем отсутствие файла
            error_log("Файл не найден: {$file}");
        }
    }
    
    // Добавляем информацию о бэкапе кода
    $code_backup_data['backup_info'] = [
        'created_at' => date('Y-m-d H:i:s'),
        'version' => '3.3',
        'last_updated' => '2025-10-29',
        'files_count' => count($saved_files),
        'saved_files' => $saved_files,
        'backup_type' => 'code_backup',
        'description' => 'Резервная копия PHP файлов админ-панели (только код)',
        'note' => 'Этот бэкап содержит только PHP код, данные сохраняются в обычных бэкапах',
        'cleaned' => true,
        'data_removed' => 'Встроенные JSON данные удалены из кода',
        'cleaning_method' => 'Безопасная очистка - удаляются только строки загрузки JSON, если файл становится пустым, возвращается оригинал'
    ];
    
    $backup_file = $backup_dir . '/code_backup_' . $timestamp . '.json';
    $result = file_put_contents($backup_file, json_encode($code_backup_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    return $result !== false ? ['file' => $backup_file, 'saved_files' => $saved_files] : false;
}

function getBackupsList() {
    $backup_dir = '../data/backups';
    if (!is_dir($backup_dir)) {
        return [];
    }
    
    $backups = [];
    
    // Получаем бэкапы данных из БД
    $data_files = glob($backup_dir . '/backup_*.json');
    foreach ($data_files as $file) {
        $content = json_decode(file_get_contents($file), true);
        if ($content && isset($content['backup_info'])) {
            $backups[] = [
                'filename' => basename($file),
                'created_at' => $content['backup_info']['created_at'] ?? 'Неизвестно',
                'version' => $content['backup_info']['version'] ?? 'Неизвестно',
                'tables_count' => $content['backup_info']['tables_count'] ?? 0,
                'saved_tables' => $content['backup_info']['saved_tables'] ?? [],
                'total_records' => $content['backup_info']['total_records'] ?? 0,
                'size' => filesize($file),
                'backup_type' => $content['backup_info']['backup_type'] ?? 'auto',
                'category' => 'database',
                'source' => $content['backup_info']['source'] ?? 'database',
                'database' => $content['backup_info']['database'] ?? 'Неизвестно'
            ];
        }
    }
    
    // Получаем бэкапы кода
    $code_files = glob($backup_dir . '/code_backup_*.json');
    foreach ($code_files as $file) {
        $content = json_decode(file_get_contents($file), true);
        if ($content && isset($content['backup_info'])) {
            $backups[] = [
                'filename' => basename($file),
                'created_at' => $content['backup_info']['created_at'] ?? 'Неизвестно',
                'version' => $content['backup_info']['version'] ?? 'Неизвестно',
                'files_count' => $content['backup_info']['files_count'] ?? 0,
                'saved_files' => $content['backup_info']['saved_files'] ?? [],
                'total_size' => 0, // Для кода не считаем размер исходных файлов
                'size' => filesize($file),
                'backup_type' => $content['backup_info']['backup_type'] ?? 'code_backup',
                'category' => 'code',
                'description' => $content['backup_info']['description'] ?? 'Резервная копия кода'
            ];
        }
    }
    
    // Сортируем по дате создания (новые сначала)
    usort($backups, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    return $backups;
}

function restoreFromBackup($backup_filename) {
    $pdo = getDBConnection(); if (!$pdo) return false;
    try {
        $backup_data=json_decode(file_get_contents(adminBackupPath($backup_filename)),true,512,JSON_THROW_ON_ERROR);
        $tables=['settings','content','films','gallery','prices']; $validated=[];
        foreach($tables as $table) {
            if(!array_key_exists($table,$backup_data)) continue;
            if(!is_array($backup_data[$table])) throw new RuntimeException('Некорректная таблица в копии.');
            $columns=$pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
            foreach($backup_data[$table] as $row) {
                if(!is_array($row)||array_diff(array_keys($row),$columns)) throw new RuntimeException('Структура копии не соответствует БД.');
                foreach($row as $value) if(!is_scalar($value)&&$value!==null) throw new RuntimeException('Некорректные данные копии.');
            }
            $validated[$table]=$backup_data[$table];
        }
        if(!$validated) throw new RuntimeException('В копии нет таблиц данных.');
        $pdo->beginTransaction();
        foreach($validated as $table=>$rows){
            $pdo->exec("DELETE FROM `$table`");
            foreach($rows as $row){$cols=array_keys($row);$sql="INSERT INTO `$table` (`".implode('`,`',$cols)."`) VALUES (".implode(',',array_fill(0,count($cols),'?')).")";$pdo->prepare($sql)->execute(array_values($row));}
        }
        $pdo->commit();return array_keys($validated);
    } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Backup restore failed: '.$e->getMessage());return false;}
}

function deleteBackup($backup_filename) {
    try { return unlink(adminBackupPath($backup_filename)); } catch(Throwable $e) { return false; }
}

$published_settings = extractSettingsFromIndex();
if ($published_settings) $settings = array_replace_recursive($settings, $published_settings);

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $error = 'Недействительный запрос (CSRF). Обновите страницу и попробуйте снова.';
    } elseif (isset($_POST['action']) && $_POST['action'] === 'save') {
        $settings = [
            'site' => [
                'title_uk' => $_POST['site_title_uk'],
                'title_ru' => $_POST['site_title_ru'],
                'description_uk' => $_POST['site_description_uk'],
                'description_ru' => $_POST['site_description_ru'],
                'keywords_uk' => $_POST['site_keywords_uk'],
                'keywords_ru' => $_POST['site_keywords_ru']
            ],
            'admin' => [
                'username' => $_POST['admin_username'],
                'email' => $_POST['admin_email']
            ],
            'seo' => [
                'google_analytics' => $_POST['google_analytics'],
                'google_verification' => $_POST['google_verification']
            ]
        ];
        
        // Сохраняем в БД
        $db_saved = false;
        $pdo = getDBConnection();
        if ($pdo) {
            $db_saved = saveSettingsToDB($pdo, $settings);
        }
        
        // Обновляем index.html
        $html_updated = $db_saved && updateIndexHtmlFromSettings($settings);
        if ($db_saved && $settings['admin']['username'] !== ($_ENV['ADMIN_USERNAME'] ?? 'admin')) adminUpdateEnv('ADMIN_USERNAME', $settings['admin']['username']);
        
        // Создаем автоматический бэкап только раз в сутки
        $backup_created = false;
        if ($db_saved && shouldCreateAutoBackup()) {
            $backup_file = createBackup(false); // false = автоматический бэкап
            $backup_created = $backup_file !== false;
        }
        
        // Логируем результат сохранения в едином стиле как в content.php
        if ($db_saved && $html_updated) {
            $success = 'Настройки сохранены успешно! Сайт обновлен.' . ($backup_created ? ' Резервная копия создана автоматически.' : '');
            $log_message = "Сохранены настройки сайта (БД + HTML обновлен)";
            if ($backup_created) {
                $log_message .= " + создан автоматический бэкап";
            }
            writeLog('save_settings', $log_message, 'success');
        } elseif ($db_saved) {
            $success = 'Настройки сохранены! Ошибка обновления сайта.' . ($backup_created ? ' Резервная копия создана автоматически.' : '');
            writeLog('save_settings', "Сохранены настройки в БД, но не удалось обновить HTML", 'warning');
        } else {
            $error = 'Ошибка сохранения настроек!';
            writeLog('save_settings', "Ошибка сохранения настроек в базу данных", 'error');
        }
    }
}

// Обработка синхронизации из index.html
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrf() && isset($_POST['action']) && $_POST['action'] === 'sync_from_index') {
    $extracted_settings = extractSettingsFromIndex();
    if ($extracted_settings) {
        // Объединяем с существующими настройками
        $settings = array_merge($settings, $extracted_settings);
        
        // Сохраняем в БД
        $sync_saved = false;
        $pdo = getDBConnection();
        if ($pdo) {
            $sync_saved = saveSettingsToDB($pdo, $settings);
        }
        
        if ($sync_saved) {
            $success = 'Данные синхронизированы из index.html!';
            writeLog('Синхронизация из index.html', 'Данные успешно извлечены и сохранены в БД', 'success');
        } else {
            $error = 'Ошибка сохранения синхронизированных данных!';
            writeLog('Синхронизация из index.html', 'Ошибка сохранения данных в БД', 'error');
        }
    } else {
        $error = 'Ошибка извлечения данных из index.html!';
        writeLog('Синхронизация из index.html', 'Ошибка извлечения данных из index.html', 'error');
    }
}



// Обработка смены пароля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!validateCsrf()) {
        $error = 'Недействительный запрос (CSRF). Обновите страницу и попробуйте снова.';
    } else {
    $admin_password_hash = $_ENV['ADMIN_PASSWORD_HASH'] ?? getenv('ADMIN_PASSWORD_HASH') ?: '';
    if ($admin_password_hash && password_verify($_POST['current_password'] ?? '', $admin_password_hash)) {
        if ($_POST['new_password'] === $_POST['confirm_password'] && strlen($_POST['new_password']) >= 8) {
            $new_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            adminUpdateEnv('ADMIN_PASSWORD_HASH', $new_hash);
            session_regenerate_id(true);
            $success = 'Пароль изменён и сохранён. Для следующего входа используйте новый пароль.';
            writeLog('Смена пароля', 'Пароль администратора изменён', 'success');
        } else {
            $error = strlen($_POST['new_password'] ?? '') < 8 ? 'Новый пароль не менее 8 символов.' : 'Новые пароли не совпадают';
            writeLog('Смена пароля', 'Ошибка смены пароля', 'error');
        }
    } else {
        $error = 'Неверный текущий пароль или .env не настроен (ADMIN_PASSWORD_HASH).';
        writeLog('Смена пароля', 'Ошибка: неверный текущий пароль', 'error');
    }
    }
}

// Обработка резервного копирования
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && validateCsrf()) {
    switch ($_POST['action']) {
        case 'create_backup':
            $backup_result = createBackup(true); // true = ручной бэкап
            if ($backup_result) {
                $saved_tables = implode(', ', $backup_result['saved_tables']);
                $success = 'Резервная копия данных создана успешно! Файл: ' . basename($backup_result['file']) . 
                          ' (Сохранены таблицы: ' . $saved_tables . ')';
                writeLog('Создание бэкапа данных', 'Создан ручной бэкап: ' . basename($backup_result['file']) . ' (таблицы: ' . $saved_tables . ')', 'success');
            } else {
                $error = 'Ошибка создания резервной копии данных!';
                writeLog('Создание бэкапа данных', 'Ошибка создания ручного бэкапа', 'error');
            }
            break;
            
        case 'create_code_backup':
            $code_backup_result = createCodeBackup();
            if ($code_backup_result) {
                $saved_files = implode(', ', $code_backup_result['saved_files']);
                $success = 'Резервная копия кода создана успешно! Файл: ' . basename($code_backup_result['file']) . 
                          ' (Сохранены: ' . $saved_files . ')';
                writeLog('Создание бэкапа кода', 'Создан бэкап кода: ' . basename($code_backup_result['file']) . ' (файлы: ' . $saved_files . ')', 'success');
            } else {
                $error = 'Ошибка создания резервной копии кода!';
                writeLog('Создание бэкапа кода', 'Ошибка создания бэкапа кода', 'error');
            }
            break;
            
        case 'restore_backup':
            if (isset($_POST['backup_filename'])) {
                $restored_tables = restoreFromBackup($_POST['backup_filename']);
                if ($restored_tables) {
                    $success = 'БД восстановлена. Для публикации сохраните контент и нажмите «Обновить сайт» в ценах, плёнках и галерее. Таблицы: ' . implode(', ', $restored_tables);
                    writeLog('Восстановление бэкапа', 'Восстановлен бэкап: ' . $_POST['backup_filename'] . ' (таблицы: ' . implode(', ', $restored_tables) . ')', 'success');
                } else {
                    $error = 'Ошибка восстановления из резервной копии!';
                    writeLog('Восстановление бэкапа', 'Ошибка восстановления из: ' . $_POST['backup_filename'], 'error');
                }
            } else {
                $error = 'Не указан файл для восстановления!';
                writeLog('Восстановление бэкапа', 'Ошибка: не указан файл для восстановления', 'error');
            }
            break;
            
        case 'delete_backup':
            if (isset($_POST['backup_filename'])) {
                if (deleteBackup($_POST['backup_filename'])) {
                    $success = 'Резервная копия удалена успешно!';
                    writeLog('Удаление бэкапа', 'Удален бэкап: ' . $_POST['backup_filename'], 'success');
                } else {
                    $error = 'Ошибка удаления резервной копии!';
                    writeLog('Удаление бэкапа', 'Ошибка удаления бэкапа: ' . $_POST['backup_filename'], 'error');
                }
            } else {
                $error = 'Не указан файл для удаления!';
                writeLog('Удаление бэкапа', 'Ошибка: не указан файл для удаления', 'error');
            }
            break;
            

    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройки - Админ-панель</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
        .header { background: #333; color: white; padding: 20px; }
        .header h1 { margin: 0; }
        .back { float: right; color: white; text-decoration: none; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .nav { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
        .nav a { display: inline-block; padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; flex: 0 0 auto; white-space: nowrap; }
        .nav a:hover { background: #0056b3; }

        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; }

        input[type="text"], input[type="email"], input[type="password"], textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; }
        textarea { height: 100px; resize: vertical; }
        button { 
            background: #28a745; 
            color: white; 
            padding: 15px 25px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-size: 16px; 
            margin-top: 10px;
        }
        button:hover { background: #218838; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }

        .info-box { background: #e7f3ff; border: 1px solid #b3d9ff; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .info-box h4 { margin-top: 0; color: #0056b3; }
        .info-box-toggle { background: #17a2b8; color: white; padding: 8px 12px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; margin-bottom: 10px; }
        .info-box-toggle:hover { background: #138496; }
        .info-box-content { display: block; }
        .info-box-content.collapsed { display: none; }
        .info-box-toggle .toggle-icon { transition: transform 0.3s ease; }
        .info-box-toggle.collapsed .toggle-icon { transform: rotate(-90deg); }
        h2 { 
            margin-top: 0; 
            margin-bottom: 30px; 
            color: #333; 
            font-size: 24px; 
        }
        h3 { 
            margin-top: 0; 
            margin-bottom: 20px; 
            color: #333; 
            font-size: 20px; 
        }
        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: bold; 
            color: #495057;
        }
        small { 
            display: block; 
            margin-top: 5px; 
            color: #666; 
            font-size: 12px; 
        }
        .section { 
            background: #f8f9fa; 
            padding: 25px; 
            border-radius: 10px; 
            margin-bottom: 25px; 
            border: 1px solid #e9ecef;
        }
        .section h3 { 
            margin-top: 0; 
            margin-bottom: 20px;
            color: #333; 
            border-bottom: 2px solid #007bff; 
            padding-bottom: 15px; 
        }
        .form-group { 
            margin-bottom: 25px; 
        }
        .form-group:last-child { 
            margin-bottom: 0; 
        }
        .grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 30px; 
        }
        .content { 
            background: white; 
            padding: 40px; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>⚙️ Настройки</h1>
        <a href="index.php" class="back">← Назад</a>
    </div>
    
    <div class="container">
        <div class="nav">
            <?php include __DIR__ . '/nav.php'; ?>
        </div>
        
        <div class="content">
            <?php if (isset($success)): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <h2>⚙️ Настройки системы</h2>
            
            <div class="info-box">
                <button class="info-box-toggle collapsed" onclick="toggleInfoBox()">
                    <span class="toggle-icon">▶</span> ℹ️ Информация о настройках
                </button>
                <div class="info-box-content collapsed" id="infoBoxContent">
                    <strong>ℹ️ Информация:</strong> Версия админ-панели: <a href="changelog.php" target="_blank" style="color: #007bff; text-decoration: underline;">3.3</a>
                    <br>Последнее обновление: 29 Октября 2025
                    <br><strong>🆕 Что нового:</strong> Полная система локализации на JSON, динамическое управление FAQ (до 20 вопросов), перестановка вопросов, обновление списка городов, <strong>SEO-совместимая локализация</strong>
                    <br><strong>🌐 Локализация:</strong> Централизованная генерация JSON файлов переводов (uk.json, ru.json) из базы данных с гибридным подходом для SEO
                    <br><strong>🗄️ База данных:</strong> Все данные и логи теперь хранятся в MySQL с автоматическим резервным копированием
                    <br><strong>🔒 Безопасность:</strong> Защищенные конфигурационные файлы, удаление hardcoded credentials, детальное логирование ошибок
                    <br><br><strong>🔑 Доступ к админ-панели:</strong> <a href="index.php" style="color: #007bff; text-decoration: underline;">tonirovka.kh.ua/admin/</a>
                    <br><strong>👤 Логин по умолчанию:</strong> admin
                    <br><strong>🔒 Пароль по умолчанию:</strong> tonirovka2025
                    <br><strong>📋 Логи администратора:</strong> <a href="logs.php" target="_blank" style="color: #007bff; text-decoration: underline;">Просмотр логов</a>
                    <br><br>
                    <form method="POST" style="display: inline;">
                        <?php echo getCsrfField(); ?>
                        <input type="hidden" name="action" value="sync_from_index">
                        <button type="submit" class="btn-warning" style="margin: 0;">🔄 Синхронизировать из index.html</button>
                    </form>
                </div>
            </div>
            
            <h2>⚙️ Настройки сайта</h2>
            
            <form method="POST">
                <?php echo getCsrfField(); ?>
                <input type="hidden" name="action" value="save">
                
                <div class="section">
                    <h3>🌐 Основные настройки сайта</h3>
                    <div class="grid">
                        <div>
                            <div class="form-group">
                                <label>Название сайта (украинский):</label>
                                <input type="text" name="site_title_uk" value="<?php echo adminEscape($settings['site']['title_uk'] ?? 'Тонування вікон у Харкові — архітектурні, захисні й бронювальні плівки'); ?>">
                                <small style="color: #666; font-size: 12px;">Можно добавить описание и домен через |</small>
                            </div>
                            <div class="form-group">
                                <label>Описание (украинский):</label>
                                <textarea name="site_description_uk"><?php echo adminEscape($settings['site']['description_uk'] ?? 'Професійне встановлення архітектурних та бронювальних плівок у Харкові. Захист від сонця, енергозбереження, безпека. Гарантія якості. ☎ +3 (050) 850-20-40'); ?></textarea>
                                <small style="color: #666; font-size: 12px;">Можно добавить телефон и дополнительные преимущества</small>
                            </div>
                            <div class="form-group">
                                <label>Ключевые слова (украинский):</label>
                                <textarea name="site_keywords_uk"><?php echo adminEscape($settings['site']['keywords_uk'] ?? 'тоніровка вікон, архітектурні плівки, бронювальні плівки, Харків, встановлення плівок, захисні плівки, дзеркальні плівки, пленка'); ?></textarea>
                                <small style="color: #666; font-size: 12px;">Через запятую, включая синонимы и связанные термины</small>
                            </div>
                        </div>
                        <div>
                            <div class="form-group">
                                <label>Название сайта (русский):</label>
                                <input type="text" name="site_title_ru" value="<?php echo adminEscape($settings['site']['title_ru'] ?? 'Тонировка окон Харьков | Архитектурные, защитные и бронирующие плёнки'); ?>">
                                <small style="color: #666; font-size: 12px;">Можно добавить описание и домен через |</small>
                            </div>
                            <div class="form-group">
                                <label>Описание (русский):</label>
                                <textarea name="site_description_ru"><?php echo adminEscape($settings['site']['description_ru'] ?? 'Профессиональная установка архитектурных и бронирующих пленок в Харькове. Защита от солнца, энергосбережение, безопасность. Гарантия качества. ☎ +3 (050) 850-20-40'); ?></textarea>
                                <small style="color: #666; font-size: 12px;">Можно добавить телефон и дополнительные преимущества</small>
                            </div>
                            <div class="form-group">
                                <label>Ключевые слова (русский):</label>
                                <textarea name="site_keywords_ru"><?php echo adminEscape($settings['site']['keywords_ru'] ?? 'тонировка окон, архитектурные пленки, бронирующие пленки, Харьков, установка пленок, защитные пленки, зеркальные пленки'); ?></textarea>
                                <small style="color: #666; font-size: 12px;">Через запятую, включая синонимы и связанные термины</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <h3>🔧 Настройки администратора</h3>
                    <div class="grid">
                        <div>
                            <div class="form-group">
                                <label>Имя пользователя:</label>
                                <input type="text" name="admin_username" value="<?php echo adminEscape($settings['admin']['username'] ?? 'admin'); ?>">
                            </div>
                        </div>
                        <div>
                            <div class="form-group">
                                <label>Email администратора:</label>
                                <input type="email" name="admin_email" value="<?php echo adminEscape($settings['admin']['email'] ?? 'admin@tonirovka.kh.ua'); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <h3>📊 SEO и аналитика</h3>
                    <div class="grid">
                        <div>
                            <div class="form-group">
                                <label>Google Analytics ID:</label>
                                <input type="text" name="google_analytics" value="<?php echo adminEscape($settings['seo']['google_analytics'] ?? ''); ?>" placeholder="G-XXXXXXXXXX">
                            </div>
                        </div>
                        <div>
                            <div class="form-group">
                                <label>Google Search Console:</label>
                                <input type="text" name="google_verification" value="<?php echo adminEscape($settings['seo']['google_verification'] ?? ''); ?>" placeholder="verification-code">
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="submit">💾 Сохранить настройки</button>
            </form>
            
            <div class="section">
                <h3>🔐 Смена пароля администратора</h3>
                <form method="POST">
                    <?php echo getCsrfField(); ?>
                    <input type="hidden" name="action" value="change_password">
                    <div class="grid">
                        <div>
                            <div class="form-group">
                                <label>Текущий пароль:</label>
                                <input type="password" name="current_password" required>
                            </div>
                        </div>
                        <div>
                            <div class="form-group">
                                <label>Новый пароль:</label>
                                <input type="password" name="new_password" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Подтвердите новый пароль:</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn-warning">🔐 Сменить пароль</button>
                </form>
            </div>
            
            <div class="section">
                <h3>📋 Системная информация</h3>
                <div class="grid">
                    <div>
                        <p><strong>PHP версия:</strong> <?php echo phpversion(); ?></p>
                        <p><strong>Сервер:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Неизвестно'; ?></p>
                        <p><strong>Время сервера:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                    </div>
                    <div>
                        <p><strong>Папка данных:</strong> <?php echo is_dir('../data') ? '✅ Создана' : '❌ Не создана'; ?></p>
                        <p><strong>Права записи:</strong> <?php echo is_writable('../data') ? '✅ Есть' : '❌ Нет'; ?></p>
                        <p><strong>Папка изображений:</strong> <?php echo is_dir('../images') ? '✅ Готова к использованию' : '❌ Недоступна'; ?></p>
                    </div>
                    <div>
                        <?php
                        // Проверка подключения к БД
                        $db_info = checkDBConnection();
                        $db_tables = [];
                        
                        if ($db_info['status']) {
                            $pdo = getDBConnection();
                            // Проверяем наличие таблиц
                            $tables_stmt = $pdo->query("SHOW TABLES");
                            while ($row = $tables_stmt->fetch(PDO::FETCH_NUM)) {
                                $db_tables[] = $row[0];
                            }
                        }
                        ?>
                        <p><strong>База данных:</strong> <?php echo $db_info['status'] ? '✅ Подключено' : '❌ ' . $db_info['message']; ?></p>
                        <p><strong>MySQL версия:</strong> <?php echo $db_info['version']; ?></p>
                        <p><strong>Таблицы БД:</strong> <?php echo !empty($db_tables) ? '✅ ' . count($db_tables) . ' таблиц' : '❌ Таблицы не найдены'; ?></p>
                        <?php if (!empty($db_tables)): ?>
                            <small style="color: #666; font-size: 11px;"><?php echo implode(', ', $db_tables); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <h3>💾 Резервное копирование</h3>
                
                <div class="form-group">
                    <form method="POST" style="display: inline;">
                        <?php echo getCsrfField(); ?>
                        <input type="hidden" name="action" value="create_backup">
                        <button type="submit" style="background: #28a745; margin-right: 10px;">🔄 Создать бэкап данных</button>
                    </form>
                    <form method="POST" style="display: inline;">
                        <?php echo getCsrfField(); ?>
                        <input type="hidden" name="action" value="create_code_backup">
                        <button type="submit" style="background: #6f42c1; margin-right: 10px;">💻 Создать бэкап кода</button>
                    </form>
                                            <small style="color: #666; display: block; margin-top: 5px;">
                            🤖 <strong>Автоматические бэкапы:</strong> создаются раз в сутки при сохранении настроек<br>
                            🔧 <strong>Ручные бэкапы данных:</strong> таблицы БД (settings, content, films, gallery, prices)<br>
                            💻 <strong>Бэкапы кода:</strong> PHP файлы админ-панели (settings.php, index.php, prices.php, gallery.php, films.php, content.php, changelog.php)<br>
                            📊 <strong>Лимит хранения:</strong> только последние 5 бэкапов каждого типа<br>
                            🔄 <strong>Автоматическая очистка:</strong> старые бэкапы удаляются автоматически
                        </small>
                </div>
                
                <?php 
                $backups = getBackupsList();
                if (!empty($backups)): 
                ?>
                    <h4>📁 Существующие резервные копии:</h4>
                    <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; padding: 15px; background: white;">
                        <?php foreach ($backups as $backup): ?>
                            <div style="border-bottom: 1px solid #eee; padding: 10px 0; margin-bottom: 10px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong><?php echo htmlspecialchars($backup['filename']); ?></strong><br>
                                        <small style="color: #666;">
                                            Создан: <?php echo htmlspecialchars($backup['created_at']); ?><br>
                                            <?php if ($backup['category'] === 'code'): ?>
                                                <span style="color: #6f42c1; font-weight: bold;">💻 КОД</span> | 
                                                <?php echo htmlspecialchars($backup['description'] ?? 'Резервная копия кода'); ?><br>
                                            <?php else: ?>
                                                <span style="color: <?php echo $backup['backup_type'] === 'manual' ? '#28a745' : '#007bff'; ?>; font-weight: bold;">
                                                    <?php echo $backup['backup_type'] === 'manual' ? '🔧 РУЧНОЙ' : '🤖 АВТО'; ?>
                                                </span> | 
                                            <?php endif; ?>
                                            Версия: <?php echo htmlspecialchars($backup['version']); ?> | 
                                            <?php if ($backup['category'] === 'code'): ?>
                                                Файлов: <?php echo $backup['files_count']; ?> | 
                                            <?php else: ?>
                                                Таблиц: <?php echo $backup['tables_count']; ?> | 
                                                Записей: <?php echo $backup['total_records']; ?> | 
                                            <?php endif; ?>
                                            Размер: <?php echo round($backup['size'] / 1024, 2); ?> KB<br>
                                            <strong>Сохранены:</strong> 
                                            <?php if ($backup['category'] === 'code'): ?>
                                                <?php echo implode(', ', $backup['saved_files']); ?>
                                            <?php else: ?>
                                                <?php echo implode(', ', $backup['saved_tables']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div>
                                        <form method="POST" style="display: inline; margin-right: 5px;">
                                            <?php echo getCsrfField(); ?>
                                            <input type="hidden" name="action" value="restore_backup">
                                            <input type="hidden" name="backup_filename" value="<?php echo htmlspecialchars($backup['filename']); ?>">
                                            <button type="submit" onclick="return confirm('Восстановить данные из этой резервной копии? Текущие данные будут заменены.')" style="background: #17a2b8; padding: 5px 10px; font-size: 12px;">🔄 Восстановить</button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <?php echo getCsrfField(); ?>
                                            <input type="hidden" name="action" value="delete_backup">
                                            <input type="hidden" name="backup_filename" value="<?php echo htmlspecialchars($backup['filename']); ?>">
                                            <button type="submit" onclick="return confirm('Удалить эту резервную копию?')" style="background: #dc3545; padding: 5px 10px; font-size: 12px;">🗑️ Удалить</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #666; font-style: italic;">Резервные копии не найдены. Создайте первую резервную копию выше.</p>
                <?php endif; ?>
                
                <div style="margin-top: 20px; padding: 15px; background: #e7f3ff; border-radius: 5px; border: 1px solid #b3d9ff;">
                    <h4 style="margin-top: 0; color: #0056b3;">ℹ️ Информация о резервном копировании:</h4>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li><strong>Бэкапы данных:</strong> сохраняются в папке <code>data/backups/</code> с префиксом <code>backup_</code> (таблицы БД)</li>
                        <li><strong>Бэкапы кода:</strong> сохраняются с префиксом <code>code_backup_</code> (только PHP код, без данных)</li>
                        <li><strong>Автоматические бэкапы:</strong> создаются раз в сутки при сохранении настроек</li>
                        <li><strong>Ручные бэкапы:</strong> создаются по кнопке в любое время</li>
                        <li><strong>Лимит хранения:</strong> максимум 5 бэкапов каждого типа (старые удаляются автоматически)</li>
                        <li><strong>Восстановление:</strong> заменяет текущие данные в БД данными из выбранной копии</li>
                    </ul>
                </div>
            </div>
            

        </div>
    </div>

    <script>
        function toggleInfoBox() {
            const infoBoxContent = document.getElementById('infoBoxContent');
            const toggleIcon = document.querySelector('.info-box-toggle .toggle-icon');
            
            if (infoBoxContent.classList.contains('collapsed')) {
                infoBoxContent.classList.remove('collapsed');
                toggleIcon.textContent = '▼'; // Change icon to down arrow
            } else {
                infoBoxContent.classList.add('collapsed');
                toggleIcon.textContent = '▶'; // Change icon to right arrow
            }
        }
    </script>
</body>
</html> 