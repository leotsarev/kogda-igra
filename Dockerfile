FROM php:8.5-apache

# Системные библиотеки для PHP-расширений
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP-расширения (json, session, curl — встроены в PHP 8.x)
RUN docker-php-ext-install pgsql

# Apache-модули: rewrite для .htaccess, negotiation для MultiViews
RUN a2enmod rewrite negotiation

# Порт 8080 вместо стандартного 80 (совместимость с docker-compose и K8s)
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf

# Конфиг сайта (Debian-путь: sites-available + a2ensite)
COPY ./etc/apache2/sites/kogda.conf /etc/apache2/sites-available/kogda.conf
RUN a2dissite 000-default && a2ensite kogda

# Код приложения
COPY ./public_html /var/www/html

# Схема БД той же версии, что и код: compat-тесты kogda-igra-net берут её прямо из образа.
# Вне document root — Apache её не отдаёт.
COPY ./db/init /db-init

EXPOSE 8080
