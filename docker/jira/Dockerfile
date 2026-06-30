FROM docker.m.daocloud.io/library/php:7.4-fpm

RUN cp /etc/apt/sources.list /etc/apt/sources.list.bak \
  && echo "deb http://mirrors.aliyun.com/debian bullseye main" > /etc/apt/sources.list \
  && apt-get update

RUN apt-get install -y --no-install-recommends \
    nginx \
    libwebp-dev libjpeg-dev libpng-dev libfreetype6-dev libjpeg62-turbo-dev \
    libzip-dev wget git unzip \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql zip \
  && pecl install redis-5.3.7 igbinary-3.2.7 \
  && docker-php-ext-enable redis igbinary \
  && rm -rf /var/lib/apt/lists/*

RUN wget -q https://mirrors.aliyun.com/composer/composer.phar -O /usr/local/bin/composer \
  && chmod +x /usr/local/bin/composer \
  && composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/

COPY jira/nginx.conf /etc/nginx/sites-enabled/default
COPY jira/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /app
EXPOSE 8090

ENTRYPOINT ["/entrypoint.sh"]
