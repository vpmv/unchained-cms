FROM php:8.4-fpm

ARG dir="/var/www/"
ARG timezone="Europe/Amsterdam"
ARG env="prod"

ENV COMPOSER_ALLOW_SUPERUSER 1
ENV TZ $timezone

ENV BASE_PKG="gnupg gnupg2 tzdata" \
    PHP_PKG="zlib1g-dev libicu-dev libzip-dev"

RUN set -xe \
    && apt-get update \
    && apt-get -y install \
        $BASE_PKG \
        $PHP_PKG

RUN curl -sL https://deb.nodesource.com/setup_22.x | bash - && \
    apt update && apt install -y nodejs && \
    npm i -g yarn
#RUN curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add - && \
#    echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list && \

RUN docker-php-ext-install intl \
    && docker-php-ext-install zip \
    && docker-php-ext-install pdo_mysql

RUN  php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
     php -r "if (hash_file('sha384', 'composer-setup.php') === 'dac665fdc30fdd8ec78b38b9800061b4150413ff2e3b6f88543c636f7cd84f6db9189d43a81e5503cda447da73c7e5b6') { echo 'Installer verified'.PHP_EOL; } else { echo 'Installer corrupt'.PHP_EOL; unlink('composer-setup.php'); exit(1); }" && \
     php composer-setup.php && \
     php -r "unlink('composer-setup.php');"



 # PROJECT
RUN mkdir -p $dir
WORKDIR $dir

COPY composer.* ./

COPY bin bin/
COPY webpack.config.js ./
COPY yarn.lock ./
COPY package.json ./

RUN mkdir -p var/cache \
    && mkdir -p var/logs \
    && chown -R www-data: var/

COPY assets assets/
COPY config config/
COPY public public/
COPY src src/
COPY user user/
COPY templates templates/

COPY .env* ./

RUN mkdir -p public/media && \
    chown -R www-data: public/media \
;

RUN yarn install && yarn encore $env

RUN mkdir vendor/ && \
    chown -R www-data: vendor \
;

#USER www-data:

RUN bin/composer.sh $env
