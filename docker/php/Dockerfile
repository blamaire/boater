FROM php:8.4-fpm-alpine

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
    pdo_mysql \
    mbstring \
    bcmath \
    intl \
    pcntl \
    opcache \
    zip \
    gd \
    exif

# ffmpeg voor video-thumbnails in de mediabibliotheek.
# git voor `git describe` (versie-info in de sidebar, zie BuildInfoComposer).
RUN apk add --no-cache ffmpeg git

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

# Run php-fpm as root in dev (matches root-owned files from Windows bind-mounts).
# Not for production.
RUN sed -i 's/^user = www-data/user = root/; s/^group = www-data/group = root/' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

# Zorg dat Laravel's cache- en session-directories bestaan in de image, zodat
# de bijbehorende docker-named-volumes bij eerste mount deze structuur
# overnemen. Zonder deze mkdir gooit de view-compiler bij package:discover
# een "Please provide a valid cache path".
RUN mkdir -p \
    /var/www/html/bootstrap/cache \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/testing \
    /var/www/html/storage/framework/views \
    /var/www/html/vendor \
    && chmod -R 777 /var/www/html/bootstrap/cache /var/www/html/storage/framework

EXPOSE 9000

CMD ["php-fpm", "--allow-to-run-as-root"]
