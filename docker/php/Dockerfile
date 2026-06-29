FROM php:8.4-fpm-alpine

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
    pdo_mysql \
    mbstring \
    bcmath \
    intl \
    pcntl \
    opcache \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

# Run php-fpm as root in dev (matches root-owned files from Windows bind-mounts).
# Not for production.
RUN sed -i 's/^user = www-data/user = root/; s/^group = www-data/group = root/' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm", "--allow-to-run-as-root"]
