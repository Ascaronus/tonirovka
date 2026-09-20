# Полный повторный аудит проекта (после всех улучшений)

**Дата:** 3 марта 2025  
**Состояние:** после внедрения P0–P3 из AUDIT-IMPROVEMENTS-NO-SYMFONY.md.

---

## 1. Структура проекта и файлы

### 1.1 Дерево (ключевые файлы)

```
src/
├── .htaccess              # Редиректы, защита, кеш, безопасность
├── .gitignore             # .env, admin/.env
├── .dockerignore          # Секреты, логи, бэкапы, .md (при сборке)
├── index.html             # Главная (статика), подключает css/main.css, js/main.js
├── css/main.css           # Стили, :root с переменными, a11y
├── js/main.js             # Языки (fetch langs/uk.json, ru.json), FAQ, галерея, модалка
├── robots.txt, sitemap.xml, image-sitemap.xml
├── Dockerfile             # PHP 8.2 + Apache, без хардкода секретов
├── docker-compose.yml     # env_file: admin/.env, environment
├── admin/
│   ├── .htaccess          # Запрет доступа к config, load_env, csrf_functions, logging_functions, lang_functions, bootstrap
│   ├── .env.example       # Шаблон переменных
│   ├── .env               # Не в git (секреты)
│   ├── load_env.php       # Парсит .env → $_ENV, putenv
│   ├── config.php         # load_env → DB_*, ROOT_DIR, DATA_DIR, IMAGES_DIR, LANGS_DIR, INDEX_HTML_PATH, getDBConnection, checkDBConnection
│   ├── bootstrap.php      # session_start, проверка admin_logged_in, config, csrf_functions, logging_functions
│   ├── csrf_functions.php # getCsrfToken, getCsrfField, validateCsrf
│   ├── logging_functions.php # writeLog, getLogs, clearAllLogs, cleanOldLogs, useDBLogs (logs_config.php)
│   ├── lang_functions.php # generateLangFiles($pdo) → langs/uk.json, ru.json
│   ├── header.php         # Шаблон: DOCTYPE, head, link assets/admin.css, шапка, навигация, открытие .content
│   ├── footer.php         # Закрытие .content, .container, body, html
│   ├── assets/admin.css   # Общие стили админки
│   ├── sitemap_helper.php # updateSitemapLastmod() — обновляет lastmod в sitemap.xml
│   ├── index.php          # Вход: config, csrf; форма логина; после входа — дашборд (без bootstrap до логина)
│   ├── logout.php         # Очистка сессии и cookie, session_destroy, редирект на index.php
│   ├── prices.php         # bootstrap, lang_functions, CRUD цен, updateIndexHtml, константы путей
│   ├── gallery.php        # bootstrap, lang_functions, CRUD галереи, updateIndexHtml, flash, header/footer, sitemap_helper
│   ├── films.php          # bootstrap, lang_functions, CRUD плёнок, updateIndexHtml, константы путей
│   ├── content.php        # bootstrap, контент из БД, extractCurrentContent, forceBannerCacheRefresh, update index.html
│   ├── settings.php       # bootstrap, настройки из БД, смена пароля (рекомендация обновить .env)
│   ├── logs.php           # bootstrap, просмотр/очистка логов
│   ├── seo-auto.php       # Своя обёртка: session, auth, config, csrf, logging; AutoSEO, getKeywordsFromDB(site_keywords_*)
│   ├── logs_config.php    # $use_db_logs = true
│   ├── export_logs.php    # session auth, include logging_functions, require config (без __DIR__)
│   ├── seo-monitor.php    # require config (без __DIR__), класс SEOMonitor
│   ├── setup-seo-monitoring.php # require config, include logging_functions (без __DIR__)
│   ├── check_gd.php       # Проверка GD
│   ├── changelog.php      # (проверить подключения)
│   └── *.sql, *.md        # Документация и миграции
├── langs/
│   ├── uk.json, ru.json   # Генерируются из БД (generateLangFiles)
│   └── README.md, SEO-COMPATIBILITY.md
├── data/
│   ├── logs/              # admin_actions.log (fallback при отсутствии БД логов)
│   └── backups/           # JSON-бэкапы
└── images/                # hero-bg.jpg, gallery/, загружаемые файлы
```

### 1.2 Связи включений (PHP)

| Файл | Подключает | Зависит от |
|------|------------|------------|
| **config.php** | load_env.php | .env в admin/ |
| **bootstrap.php** | config, csrf_functions, logging_functions | session, config (→ ROOT_DIR, DATA_DIR, …) |
| **logging_functions.php** | config.php | DATA_DIR (если определён), иначе __DIR__/../data |
| **lang_functions.php** | — (только функция) | LANGS_DIR или __DIR__/../langs, вызывающий должен передать $pdo |
| **gallery.php** | bootstrap, lang_functions; при обновлении сайта — sitemap_helper; вывод — header, footer | ROOT_DIR, DATA_DIR, IMAGES_DIR, INDEX_HTML_PATH |
| **films.php** | bootstrap, lang_functions | INDEX_HTML_PATH, IMAGES_DIR |
| **prices.php** | bootstrap, lang_functions | DATA_DIR, INDEX_HTML_PATH |
| **content.php** | bootstrap; далее include 'lang_functions.php', require_once 'config.php' | DATA_DIR, INDEX_HTML_PATH, IMAGES_DIR (config уже через bootstrap) |
| **settings.php** | bootstrap | getDBConnection |
| **logs.php** | bootstrap | getLogs, clearAllLogs, cleanOldLogs |
| **index.php** | config, csrf_functions (не bootstrap до входа) | ADMIN_USERNAME, ADMIN_PASSWORD_HASH из $_ENV/getenv |
| **seo-auto.php** | session_start, config, csrf_functions, logging_functions (через @include) | AutoSEO, getKeywordsFromDB(site_keywords_uk/ru) |
| **export_logs.php** | logging_functions, config (без __DIR__) | session auth вручную |
| **seo-monitor.php** | config (без __DIR__) | SEOMonitor($pdo) |
| **setup-seo-monitoring.php** | config, logging_functions (без __DIR__) | — |
| **sitemap_helper.php** | не подключает ничего | ROOT_DIR или dirname(__DIR__) |

---

## 2. Константы и пути

### 2.1 Определяются в config.php (после load_env)

- **DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD** — из .env.
- **ROOT_DIR** = `dirname(__DIR__)` (родитель каталога admin).
- **DATA_DIR** = ROOT_DIR . '/data'
- **IMAGES_DIR** = ROOT_DIR . '/images'
- **LANGS_DIR** = ROOT_DIR . '/langs'
- **INDEX_HTML_PATH** = ROOT_DIR . '/index.html'

### 2.2 Где используются

- **gallery.php:** DATA_DIR, INDEX_HTML_PATH, IMAGES_DIR (файловые операции и вывод путей в админке).
- **films.php:** INDEX_HTML_PATH, IMAGES_DIR.
- **prices.php:** DATA_DIR, INDEX_HTML_PATH.
- **content.php:** DATA_DIR, INDEX_HTML_PATH, IMAGES_DIR.
- **logging_functions.php:** DATA_DIR для каталога логов (с fallback на __DIR__/../data).
- **lang_functions.php:** LANGS_DIR с fallback на __DIR__/../langs.
- **sitemap_helper.php:** ROOT_DIR для sitemap.xml.

Все файловые операции к index.html, data/, images/, langs/ в перечисленных файлах идут через константы. Относительные пути вида `../images/` остались только в HTML/JS для URL (например, в админке превью картинок и в index.html для баннера), что корректно.

---

## 3. Безопасность

### 3.1 Секреты

- **.env:** в .gitignore и .dockerignore; парсится load_env.php → $_ENV и putenv.
- **config.php** читает DB_* и не хранит пароли в коде.
- **index.php** использует ADMIN_USERNAME и ADMIN_PASSWORD_HASH из окружения; вход через password_verify.

### 3.2 Сессия и выход

- **bootstrap.php:** session_start, проверка $_SESSION['admin_logged_in']; при отсутствии — редирект на index.php.
- **logout.php:** $_SESSION = [], удаление cookie сессии (session_get_cookie_params), session_destroy, редирект.

### 3.3 CSRF

- **csrf_functions.php:** getCsrfToken, getCsrfField, validateCsrf (hash_equals).
- Формы: index.php (логин), prices, gallery, films, content, settings, seo-auto, logs — везде вывод поля и проверка при POST.

### 3.4 Защита файлов

- **Корневой .htaccess:** `<Files "admin/config.php">`, `<Files "admin/logging_functions.php">` (в Apache могут сопоставляться по basename; дублирование с admin/.htaccess).
- **admin/.htaccess:** запрет прямого доступа к config, load_env, csrf_functions, logging_functions, lang_functions, bootstrap, sitemap_helper, header, footer по имени файла.

### 3.5 Загрузки файлов

- gallery, films: проверка getimagesize, whitelist расширений, уникальные имена (uniqid). В .htaccess SetHandler для .jpg/.png и т.д. — запрет выполнения как PHP.

---

## 4. Обнаруженные проблемы и рекомендации

### 4.1 Критично / важно

1. **prices.php — проверка подключения к БД**  
   Использовалось `if (isset($pdo))` при загрузке цен; при ошибке подключения `$pdo === false`, но isset($pdo) === true, что могло приводить к вызову loadPricesFromDB(false).  
   **Исправлено в этом аудите:** условие заменено на `if ($pdo)`.

2. **content.php — дублирование подключений и пути**  
   После bootstrap уже подключены config и (через него) load_env. Далее шли `include 'lang_functions.php';` и `require_once 'config.php';`.  
   **Исправлено в этом аудите:** удалён повторный `require_once 'config.php';`, подключение lang_functions заменено на `include __DIR__ . '/lang_functions.php';`.

3. **export_logs.php, seo-monitor.php, setup-seo-monitoring.php — пути без __DIR__**  
   **Исправлено:** везде используются `__DIR__ . '/config.php'` и `__DIR__ . '/logging_functions.php'`.

### 4.2 Единообразие

4. **Flash-сообщения**  
   Реализованы только в gallery.php (flash_success, flash_error, редирект после успешного POST). В prices, films, content, settings, logs после POST иногда остаётся вывод сообщения без редиректа.  
   **Рекомендация:** по желанию ввести такой же паттерн (редирект + flash) в остальных разделах для единообразия и избежания повторной отправки формы.

5. **Шаблон админки (header/footer)**  
   Header и footer подключены только в gallery.php; остальные страницы (prices, films, content, settings, logs, index после входа) по-прежнему содержат собственные блоки навигации и стилей.  
   **Рекомендация:** постепенно переводить остальные страницы на header.php/footer.php и assets/admin.css, чтобы убрать дублирование.

6. **Обновление sitemap lastmod**  
   **Исправлено:** updateSitemapLastmod() вызывается при любом успешном обновлении index.html в gallery, films, prices и content.

### 4.3 Мелкое

7. **Корневой .htaccess**  
   Директивы `<Files "admin/config.php">` и т.д. в корне могут не применяться к запросам в подкаталоге admin/ (зависит от конфигурации Apache). Защита в admin/.htaccess уже перекрывает эти файлы по имени — дублирование не мешает, но при необходимости можно оставить только admin/.htaccess для служебных файлов.

8. **admin/assets/admin.css**  
   Подключается в header.php как `href="assets/admin.css"` — относительный путь от текущего URL. При открытии, например, admin/gallery.php путь корректен (admin/assets/admin.css). При открытии admin/seo-auto.php или других страниц в admin/ — тоже. Зависит от того, что все страницы админки находятся в admin/.

---

## 5. Потоки данных

### 5.1 Вход и конфигурация

- Запрос к admin/* → (если не index.php и не seo-auto) bootstrap → session, проверка admin_logged_in → config → load_env → .env → константы и getDBConnection().
- index.php: только config + csrf; при POST проверка логина по ADMIN_USERNAME и password_verify(ADMIN_PASSWORD_HASH).

### 5.2 База данных

- **Источник истины:** MySQL (таблицы content, prices, gallery, films, settings, admin_logs при useDBLogs()).
- **Чтение:** prices, gallery, films, content, settings загружаются из БД через getDBConnection().
- **Запись:** при сохранении в админке — INSERT/UPDATE/DELETE в БД; затем при необходимости обновление index.html (функции updateIndexHtml в каждом модуле) и генерация langs/uk.json, ru.json через generateLangFiles($pdo).

### 5.3 Фронтенд (index.html)

- Статический HTML; подключает css/main.css и js/main.js.
- js/main.js загружает langs/uk.json и langs/ru.json через fetch, переключает язык и обновляет data-lang-* и мета-теги.
- Галерея и модалка работают по разметке из index.html (классы, id). Связь админки с фронтом: админка перезаписывает фрагменты index.html и генерирует langs/*.json; фронт только читает их.

### 5.4 Логи

- Если существует admin/logs_config.php и $use_db_logs = true — логи пишутся в БД (admin_logs). Иначе — в data/logs/admin_actions.log. logging_functions подключает config (для getDBConnection и DATA_DIR).

---

## 6. Сводка по файлам и связям

| Компонент | Файлы | Связи |
|-----------|--------|--------|
| Конфиг и окружение | load_env.php, config.php, .env | .env → $_ENV → DB_*, константы путей |
| Точка входа админки | bootstrap.php | session, auth, config, csrf, logging |
| Страницы с bootstrap | prices, gallery, films, content, settings, logs | bootstrap → config → пути и PDO |
| Страницы без bootstrap | index.php (логин), seo-auto.php (своя обёртка) | config, csrf (и при необходимости logging) |
| Вспомогательные | lang_functions, sitemap_helper | Вызываются из страниц; sitemap_helper использует ROOT_DIR |
| Шаблон вывода | header.php, footer.php, assets/admin.css | Подключены в gallery; остальные страницы — свои разметка/стили |
| Защита | admin/.htaccess, корневой .htaccess | Запрет прямого доступа к служебным PHP |
| Фронтенд | index.html, css/main.css, js/main.js, langs/*.json | Статика; JS читает langs; админка пишет index.html и langs |

---

## 7. Итог

После всех внесённых изменений:

- Секреты вынесены в .env, пути централизованы в config, загрузки и CSRF приведены в порядок, logout корректен, логи не чистятся случайно при каждом запросе.
- Оставшиеся замечания: единообразие путей (content, export_logs, seo-monitor, setup-seo-monitoring) через __DIR__, удаление дублирования config в content.php, при желании — расширение flash и header/footer на все страницы админки и вызов updateSitemapLastmod при любом обновлении сайта.

Повторный аудит зафиксирован в этом документе; правка по prices.php (isset($pdo) → $pdo) внесена в код.
