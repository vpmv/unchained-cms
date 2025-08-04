FROM php:8.4-fpm

ARG dir="/var/www/"
ARG env="prod"
ARG user="www-data"

ENV COMPOSER_ALLOW_SUPERUSER 1
ENV TZ "UTC"

ENV BASE_PKG="gnupg gnupg2 tzdata" \
    PHP_PKG="zlib1g-dev libicu-dev libzip-dev"

RUN set -xe \
    && apt-get update \
    && apt-get -y install \
        $BASE_PKG \
        $PHP_PKG

RUN docker-php-ext-install intl \
    && docker-php-ext-install zip \
    && docker-php-ext-install pdo_mysql

RUN curl -sL https://deb.nodesource.com/setup_22.x | bash - && \
    apt update && apt install -y nodejs && \
    npm i -g yarn

RUN  php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
     php -r "if (hash_file('sha384', 'composer-setup.php') === 'dac665fdc30fdd8ec78b38b9800061b4150413ff2e3b6f88543c636f7cd84f6db9189d43a81e5503cda447da73c7e5b6') { echo 'Installer verified'.PHP_EOL; } else { echo 'Installer corrupt'.PHP_EOL; unlink('composer-setup.php'); exit(1); }" && \
     php composer-setup.php && \
     php -r "unlink('composer-setup.php');"

 # PROJECT
RUN mkdir -p $dir
WORKDIR $dir

COPY composer.* ./

COPY .docker/php/bin bin/
COPY webpack.config.js ./
COPY yarn.lock ./
COPY package.json ./


COPY assets assets/
COPY config config/
COPY public public/
COPY src src/
COPY user user/
COPY templates templates/
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

RUN test ! -f user/assets/user.js && cp user/assets/user.js.dist user/assets/user.js
RUN test ! -f user/config.yaml && cp user/config/config.yaml.dist user/config/config.yaml
RUN test ! -f user/config/applications.yaml && cp user/config/applications.yaml.dist user/config/applications.yaml
RUN test ! -f user/config/framework/security.yaml && cp user/config/framework/security.yaml.dist user/config/framework/security.yaml

USER $user:

RUN bin/composer.sh $env
RUN yarn install && yarn encore $env
