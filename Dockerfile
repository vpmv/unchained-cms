FROM php:8.4-fpm-alpine

ARG dir="/var/www/"
ARG env="prod"

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV TZ="UTC"
ENV INCLUDE_EXAMPLES="false"

ENV BASE_PKG="gnupg tzdata nodejs npm" \
    PHP_PKG="zlib-dev icu-dev libzip-dev"

RUN apk add --update --no-cache \
    $BASE_PKG \
    $PHP_PKG
RUN npm i -g yarn

RUN docker-php-ext-install intl \
    && docker-php-ext-install zip \
    && docker-php-ext-install pdo_mysql

RUN  php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
     php -r "if (hash_file('sha384', 'composer-setup.php') === 'dac665fdc30fdd8ec78b38b9800061b4150413ff2e3b6f88543c636f7cd84f6db9189d43a81e5503cda447da73c7e5b6') { echo 'Installer verified'.PHP_EOL; } else { echo 'Installer corrupt'.PHP_EOL; unlink('composer-setup.php'); exit(1); }" && \
     php composer-setup.php && \
     php -r "unlink('composer-setup.php');"
RUN mv composer.phar /usr/local/bin/composer

 # PROJECT
RUN mkdir -p $dir
WORKDIR $dir

COPY .docker/php/bin bin/
RUN chmod +x bin/entrypoint.sh

COPY composer.* ./
COPY webpack.config.js ./
COPY package.json ./
COPY yarn.lock ./

COPY assets assets/
COPY config config/
COPY public public/
COPY src src/
COPY templates templates/
COPY user user/
COPY .env* ./

RUN mkdir -p var/cache \
    && mkdir -p var/logs \
    && mkdir vendor/ \
    && mkdir -p .cache/yarn \
    && mkdir node_modules \
    && mkdir -p public/media \
    && mkdir -p public/build \
    && chown -R www-data: var/  \
    && chown -R www-data: vendor \
    && chown -R www-data: node_modules \
    && chown -R www-data: .cache/yarn \
    && chown -R www-data: public/build \
    && chown -R www-data: public/media \
;

RUN [ ! -f user/assets/user.js ] && cp user/assets/user.js.dist user/assets/user.js;

USER www-data:

RUN bin/composer.sh $env
RUN yarn install && yarn encore $env


USER root

WORKDIR $dir/public

# PHP-FPM runs as www-data by default
ENTRYPOINT ["/var/www/bin/entrypoint.sh"]
CMD ["php-fpm", "-F"]
