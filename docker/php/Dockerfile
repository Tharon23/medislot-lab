# Pinned below the CVE-2026-109618/109619 fix (8.3.36). See docs/stage-2/02-scan-notes.md
FROM php:8.3.35-fpm

RUN apt-get update \
 && apt-get install -y --no-install-recommends curl \
 && rm -rf /var/lib/apt/lists/* \
 && docker-php-ext-install pdo_mysql \
 && pecl install redis \
 && docker-php-ext-enable redis

COPY php.ini /usr/local/etc/php/conf.d/zz-medislot.ini
