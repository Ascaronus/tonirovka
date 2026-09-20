FROM php:8.2-apache

# Включаем mod_rewrite и mod_ssl
RUN a2enmod rewrite ssl

# Разрешаем .htaccess использовать директивы
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Создаем самоподписанный SSL сертификат для localhost
RUN openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/ssl/private/localhost.key \
    -out /etc/ssl/certs/localhost.crt \
    -subj "/C=UA/ST=Kharkiv/L=Kharkiv/O=Development/CN=localhost" \
    -addext "subjectAltName=DNS:localhost,DNS:*.localhost,IP:127.0.0.1"

# Устанавливаем глобальный ServerName чтобы убрать предупреждение
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Создаем HTTP конфигурацию с редиректом на HTTPS
RUN cat > /etc/apache2/sites-available/000-default.conf <<EOF
<VirtualHost *:80>
    ServerName localhost
    ServerAlias *
    DocumentRoot /var/www/html
    
    <Directory /var/www/html>
        AllowOverride All
        Require all granted
    </Directory>
    
    # Редирект с HTTP на HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    # Для локальной разработки всегда редиректим на localhost:8443
    # (для production измените на стандартный HTTPS без порта)
    RewriteRule ^(.*)$ https://localhost:8443%{REQUEST_URI} [R=301,L]
    
    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# Создаем SSL конфигурацию для Apache
RUN cat > /etc/apache2/sites-available/000-default-ssl.conf <<EOF
<VirtualHost *:443>
    ServerName localhost
    ServerAlias *
    DocumentRoot /var/www/html
    
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/localhost.crt
    SSLCertificateKeyFile /etc/ssl/private/localhost.key
    
    <Directory /var/www/html>
        AllowOverride All
        Require all granted
    </Directory>
    
    # Разрешаем запросы без Host заголовка или с IP адресом
    UseCanonicalName Off
    
    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# Включаем оба сайта (HTTP и HTTPS)
RUN a2ensite 000-default.conf
RUN a2ensite 000-default-ssl.conf

# Устанавливаем необходимые пакеты и расширения
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    openssl \
    jpegoptim \
    optipng \
    zip \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install mysqli pdo pdo_mysql gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Создаем php.ini если его нет (используем production версию)
RUN if [ ! -f /usr/local/etc/php/php.ini ]; then \
        cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini 2>/dev/null || \
        cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini 2>/dev/null || true; \
    fi

# Секреты передаются через docker-compose (env_file или environment), не хардкодим в образ

# Копируем исходный код приложения сразу в /var/www/html (DocumentRoot Apache)
COPY ./ /var/www/html/

# Устанавливаем права доступа
RUN chown -R www-data:www-data /var/www/html/ \
    && chmod -R 755 /var/www/html/

RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
