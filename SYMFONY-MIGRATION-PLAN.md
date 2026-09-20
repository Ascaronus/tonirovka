# 🚀 План миграции проекта на Symfony Framework

## ✅ Анализ текущего проекта

Ваш проект представляет собой PHP веб-сайт без фреймворка со следующими компонентами:

- **Админ-панель** (множество PHP файлов для управления контентом)
- **Многоязычность** (украинский/русский) с JSON файлами переводов
- **SEO функциональность** (автоматизация мета-тегов, мониторинг)
- **Управление контентом** (цены, галерея, пленки)
- **База данных MySQL** (PDO подключение)
- **Логирование действий**
- **Docker окружение** (PHP 8.2 + Apache)

## 🎯 Преимущества перехода на Symfony

1. **Архитектура MVC** - разделение логики, представления и данных
2. **Безопасность** - встроенная защита от CSRF, XSS, SQL-инъекций
3. **Роутинг** - красивые URL, RESTful API
4. **Doctrine ORM** - удобная работа с БД
5. **Шаблонизатор Twig** - безопасные и мощные шаблоны
6. **Компоненты** - переиспользуемые части кода
7. **Тестирование** - встроенная поддержка тестов
8. **Интернационализация** - встроенная поддержка i18n
9. **Кэширование** - улучшенная производительность
10. **Расширяемость** - легко добавлять новый функционал

## 📋 План миграции (пошагово)

### Этап 1: Подготовка и установка Symfony

```bash
# 1. Создать новый Symfony проект
composer create-project symfony/skeleton:"6.4.*" symfony-project
cd symfony-project

# 2. Установить необходимые компоненты
composer require symfony/orm-pack
composer require symfony/maker-bundle --dev
composer require symfony/security-bundle
composer require symfony/form
composer require symfony/translation
composer require symfony/validator
composer require symfony/twig-bundle
composer require symfony/asset-mapper
composer require symfony/webpack-encore-bundle

# 3. Настроить Doctrine
php bin/console doctrine:database:create
```

### Этап 2: Миграция структуры БД

1. Создать Entity классы для существующих таблиц:
   - `Price` (цены)
   - `Gallery` (галерея)
   - `Film` (пленки)
   - `Content` (контент)
   - `SeoSettings` (SEO настройки)
   - `Log` (логи)

2. Использовать миграции Doctrine:
   ```bash
   php bin/console make:migration
   php bin/console doctrine:migrations:migrate
   ```

### Этап 3: Структура Symfony приложения

```
symfony-project/
├── config/
│   ├── packages/
│   │   ├── doctrine.yaml
│   │   ├── security.yaml
│   │   └── translation.yaml
│   └── routes.yaml
├── src/
│   ├── Controller/
│   │   ├── Admin/
│   │   │   ├── DashboardController.php
│   │   │   ├── PriceController.php
│   │   │   ├── GalleryController.php
│   │   │   ├── FilmController.php
│   │   │   ├── ContentController.php
│   │   │   └── SeoController.php
│   │   └── MainController.php
│   ├── Entity/
│   │   ├── Price.php
│   │   ├── Gallery.php
│   │   ├── Film.php
│   │   └── ...
│   ├── Repository/
│   │   ├── PriceRepository.php
│   │   ├── GalleryRepository.php
│   │   └── ...
│   ├── Form/
│   │   ├── PriceType.php
│   │   ├── GalleryType.php
│   │   └── ...
│   └── Service/
│       ├── ContentService.php
│       ├── SeoService.php
│       └── LoggingService.php
├── templates/
│   ├── admin/
│   │   ├── dashboard.html.twig
│   │   ├── prices/
│   │   ├── gallery/
│   │   └── ...
│   └── main/
│       └── index.html.twig
└── translations/
    ├── messages.uk.yaml
    └── messages.ru.yaml
```

### Этап 4: Миграция функционала

#### 4.1. Роутинг

**Было:**
```php
// admin/prices.php?action=add
```

**Станет:**
```yaml
# config/routes.yaml
admin_prices_list:
    path: /admin/prices
    controller: App\Controller\Admin\PriceController::index
    methods: [GET]

admin_prices_add:
    path: /admin/prices/add
    controller: App\Controller\Admin\PriceController::add
    methods: [GET, POST]
```

#### 4.2. Безопасность

**Было:**
```php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    // редирект
}
```

**Станет:**
```yaml
# config/packages/security.yaml
security:
    firewalls:
        admin:
            pattern: ^/admin
            form_login:
                login_path: admin_login
                check_path: admin_login_check
            logout:
                path: /admin/logout
```

#### 4.3. Работа с БД

**Было:**
```php
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT * FROM prices");
```

**Станет:**
```php
// В Controller или Service
public function __construct(private PriceRepository $priceRepository) {}

public function index(): Response
{
    $prices = $this->priceRepository->findAll();
    return $this->render('admin/prices/index.html.twig', [
        'prices' => $prices,
    ]);
}
```

#### 4.4. Формы

**Было:**
```php
<form method="POST">
    <input type="text" name="title">
    <input type="number" name="price">
</form>
```

**Станет:**
```php
// PriceType.php
$builder
    ->add('title', TextType::class)
    ->add('price', NumberType::class);

// В Controller
$form = $this->createForm(PriceType::class, $price);
$form->handleRequest($request);
```

#### 4.5. Интернационализация

**Было:**
```php
// langs/uk.json, langs/ru.json
// JavaScript обработка на клиенте
```

**Станет:**
```yaml
# translations/messages.uk.yaml
site.title: "Тонування вікон Харків"
site.description: "Професійне тонування..."

# translations/messages.ru.yaml
site.title: "Тонировка окон Харьков"
site.description: "Профессиональная тонировка..."
```

```twig
{# В шаблоне #}
{{ 'site.title'|trans }}
```

### Этап 5: Миграция админ-панели

Преобразовать каждый PHP файл админ-панели:

1. `admin/index.php` → `Admin/DashboardController.php`
2. `admin/prices.php` → `Admin/PriceController.php`
3. `admin/gallery.php` → `Admin/GalleryController.php`
4. `admin/films.php` → `Admin/FilmController.php`
5. `admin/content.php` → `Admin/ContentController.php`
6. `admin/seo-auto.php` → `Admin/SeoController.php`
7. `admin/settings.php` → `Admin/SettingsController.php`

### Этап 6: Миграция главной страницы

Конвертировать `index.html` в Twig шаблон:
- Разделить на компоненты (блоки)
- Перенести SEO мета-теги в контроллер
- Заменить JavaScript локализацию на Symfony Translation

### Этап 7: Обновление Docker

Создать новый `Dockerfile` для Symfony:
```dockerfile
FROM php:8.2-fpm

# Установка зависимостей
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    zip unzip git

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
RUN composer install --no-dev --optimize-autoloader
```

## ⚠️ Важные моменты

### Что нужно учесть:

1. **Совместимость данных** - убедиться, что все данные из БД корректно мигрируют
2. **SEO** - сохранить все существующие URL или настроить редиректы
3. **Файлы** - мигрировать загруженные изображения
4. **Сессии** - пользователи будут разлогинены при миграции
5. **Конфигурация** - перенести настройки из `config.php` в `.env`

### Риски и решения:

| Риск | Решение |
|------|---------|
| Долгий процесс | Постепенная миграция, параллельная работа старой/новой версии |
| Потеря функционала | Тестирование каждого компонента |
| Падение производительности | Кэширование, оптимизация запросов |
| Несовместимость | Проверка версий PHP и расширений |

## 📊 Оценка времени

- **Подготовка и настройка Symfony**: 1-2 дня
- **Миграция Entity и БД**: 2-3 дня
- **Миграция контроллеров админ-панели**: 5-7 дней
- **Миграция фронтенда**: 2-3 дня
- **Тестирование и отладка**: 3-5 дней
- **Итого**: **13-20 дней** (при работе одного разработчика)

## 🚀 Быстрый старт (минимальная миграция)

Если нужен быстрый результат, можно начать с минимальной миграции:

1. Установить Symfony в подпапку `/symfony`
2. Мигрировать один модуль (например, цены)
3. Проверить работу
4. Постепенно мигрировать остальные модули

## 🔧 Команды для начала работы

```bash
# 1. Создать проект (в отдельной директории)
composer create-project symfony/skeleton:"6.4.*" ../symfony-migration

# 2. Установить компоненты
cd ../symfony-migration
composer require symfony/orm-pack
composer require symfony/twig-bundle
composer require symfony/security-bundle
composer require symfony/maker-bundle --dev

# 3. Настроить .env
# Отредактировать DATABASE_URL

# 4. Создать первую Entity
php bin/console make:entity Price

# 5. Запустить сервер
symfony server:start
```

## 📚 Полезные ресурсы

- [Symfony Documentation](https://symfony.com/doc/current/index.html)
- [Doctrine ORM](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/index.html)
- [Twig Documentation](https://twig.symfony.com/doc/)

## ✅ Заключение

**Да, проект можно и нужно мигрировать на Symfony!** 

Это даст:
- Более чистую и поддерживаемую архитектуру
- Улучшенную безопасность
- Лучшую производительность
- Проще добавление нового функционала
- Соответствие современным стандартам PHP

Хотите, чтобы я начал создавать структуру Symfony проекта прямо сейчас?

