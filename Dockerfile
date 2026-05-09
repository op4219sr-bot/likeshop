FROM php:7.2-fpm-buster

ENV DEBIAN_FRONTEND=noninteractive

# 系统依赖 + nginx + supervisor
RUN sed -i 's|deb.debian.org|archive.debian.org|g; s|security.debian.org|archive.debian.org/debian-security|g; /buster-updates/d' /etc/apt/sources.list \
    && echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99no-check-valid \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        default-mysql-client \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        libonig-dev \
        libxml2-dev \
        libcurl4-openssl-dev \
        libicu-dev \
        libssl-dev \
        zlib1g-dev \
        unzip \
        curl \
        ca-certificates \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include --with-jpeg-dir=/usr/include \
    && docker-php-ext-install -j"$(nproc)" \
        pdo \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        mbstring \
        bcmath \
        curl \
        intl \
        opcache \
        dom \
        iconv \
    && rm -rf /var/lib/apt/lists/* \
    && rm -rf /etc/nginx/sites-enabled/default

# PHP 配置
RUN { \
        echo 'memory_limit = 256M'; \
        echo 'upload_max_filesize = 32M'; \
        echo 'post_max_size = 32M'; \
        echo 'max_execution_time = 120'; \
        echo 'date.timezone = Asia/Shanghai'; \
        echo 'short_open_tag = On'; \
    } > /usr/local/etc/php/conf.d/likeshop.ini

# 项目代码
WORKDIR /server
COPY server/ /server/

# 写死可写目录权限
RUN mkdir -p /server/runtime /server/public/uploads /server/config \
    && chown -R www-data:www-data /server \
    && chmod -R 0755 /server \
    && chmod -R 0775 /server/runtime /server/public/uploads /server/config

# nginx + supervisor 配置 + entrypoint
COPY docker/image/nginx.conf       /etc/nginx/nginx.conf
COPY docker/image/site.conf        /etc/nginx/conf.d/likeshop.conf
COPY docker/image/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/image/auto_install.php /usr/local/bin/auto_install.php
COPY docker/image/entrypoint.sh    /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
