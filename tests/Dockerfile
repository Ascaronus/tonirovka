FROM php:8.2-apache
RUN a2enmod rewrite headers expires deflate && \
    sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html/data
