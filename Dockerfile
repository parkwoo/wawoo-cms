# wawoo-cms production image
# Multi-stage not strictly needed (no build step), but kept lean.

FROM php:8.3-apache AS base

ENV DEBIAN_FRONTEND=noninteractive
ENV APACHE_DOCUMENT_ROOT=/var/www/html
ENV WAWOO_ENV=production

RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libonig-dev \
        zlib1g-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install -j$(nproc) opcache intl zip bcmath mbstring

# Apache modules + headers
RUN a2enmod rewrite headers deflate expires remoteip

# Production php.ini
RUN { \
        echo 'memory_limit = 128M'; \
        echo 'upload_max_filesize = 16M'; \
        echo 'post_max_size = 16M'; \
        echo 'max_execution_time = 30'; \
        echo 'default_charset = "UTF-8"'; \
        echo 'expose_php = Off'; \
        echo 'display_errors = Off'; \
        echo 'log_errors = On'; \
        echo 'date.timezone = UTC'; \
        echo 'opcache.enable = 1'; \
        echo 'opcache.memory_consumption = 128'; \
        echo 'opcache.max_accelerated_files = 4000'; \
        echo 'opcache.validate_timestamps = 0'; \
        echo 'opcache.revalidate_freq = 0'; \
        echo 'session.cookie_httponly = 1'; \
        echo 'session.cookie_samesite = "Lax"'; \
        echo 'session.cookie_secure = 1'; \
        echo 'session.use_strict_mode = 1'; \
        echo 'session.use_only_cookies = 1'; \
    } > /usr/local/etc/php/conf.d/wawoo.ini

# Document root
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Allow .htaccess overrides (we ship one for pretty URLs).
RUN echo '<Directory ${APACHE_DOCUMENT_ROOT}>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/wawoo.conf \
    && a2enconf wawoo

WORKDIR /var/www/html

# Copy app
COPY . /var/www/html/

# Cache dir writable
RUN mkdir -p /var/www/html/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/cache

# Healthcheck hits the gate page (200) or a post (200). We probe sitemap.
HEALTHCHECK --interval=30s --timeout=5s --retries=3 --start-period=5s \
    CMD curl -fsS http://localhost/sitemap.xml >/dev/null || exit 1

EXPOSE 80

# Apache in foreground
CMD ["apache2-foreground"]
