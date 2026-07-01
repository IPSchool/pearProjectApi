FROM docker.m.daocloud.io/library/php:8.2-fpm

# 使用基础镜像自带的 apt 源（PHP 8.2 为 Debian Bookworm+，勿强行换 bullseye/bookworm 镜像以免依赖冲突）
RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    nginx \
    libwebp-dev libjpeg-dev libpng-dev libfreetype6-dev libjpeg62-turbo-dev \
    libzip-dev wget git unzip \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql zip \
  && pecl install redis igbinary \
  && docker-php-ext-enable redis igbinary \
  && rm -rf /var/lib/apt/lists/*

RUN wget -q https://mirrors.aliyun.com/composer/composer.phar -O /usr/local/bin/composer \
  && chmod +x /usr/local/bin/composer \
  && composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/

COPY jira/nginx.conf /etc/nginx/sites-enabled/default
COPY jira/php-gateb.ini /usr/local/etc/php/conf.d/99-gateb.ini
COPY jira/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /app
EXPOSE 8090

ENTRYPOINT ["/entrypoint.sh"]
