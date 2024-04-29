FROM php:7.4.33-fpm

ARG dir="/var/www/"
ARG timezone="Europe/Amsterdam"
ARG env="prod"

ENV COMPOSER_ALLOW_SUPERUSER 1

ENV BASE_PKG="gnupg gnupg2 tzdata nodejs yarn" \
    PHP_PKG="zlib1g-dev libicu-dev libzip-dev"

RUN set -xe \
    && apt-get update \
    && apt-get -y install \
        $BASE_PKG \
        $PHP_PKG

RUN curl -sL https://deb.nodesource.com/setup_20.x | bash -
RUN curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add - && \
    echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list && \
    apt update && apt install -y yarn

ENV TZ $timezone

#RUN a2enmod rewrite && a2enmod negotiation

RUN docker-php-ext-install intl \
    && docker-php-ext-install zip \
    && docker-php-ext-install pdo_mysql

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

USER www-data:

RUN bin/composer.sh $env
