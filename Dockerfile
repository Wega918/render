# Dockerfile (ФИНАЛЬНЫЙ ОБНОВЛЕННЫЙ)

# 1. Используем официальный образ PHP с веб-сервером Apache
FROM php:8.2-apache

# 2. Устанавливаем корневую директорию сервера
WORKDIR /var/www/html

# !!! КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Установка cURL, SSL и других зависимостей !!!
# Устанавливаем необходимые системные пакеты и PHP-расширения
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libxml2-dev \
    git \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install -j$(nproc) iconv pdo_mysql \
    && docker-php-ext-install curl \
    && docker-php-ext-enable curl 

# 3. Копируем ваш скрипт-прокси в корень веб-сервера
COPY gemini_proxy_external.php .

# 4. Включаем модуль mod_rewrite 
RUN a2enmod rewrite

# 5. Устанавливаем лимит на память для PHP
RUN echo "memory_limit = 256M" > /usr/local/etc/php/conf.d/render-php.ini

# 6. Apache по умолчанию слушает порт 80
EXPOSE 80
