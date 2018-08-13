FROM php:fpm-alpine

MAINTAINER development@oguzhanuysal.eu

ENV CONF_MEMBERID=null
ENV CONF_PASSHASH=null
ENV CONF_ACCESSKEY=ChangeMeIAmNotSecure
ENV DB_HOST=mariadb
ENV DB_USER=dbuser
ENV DB_PASS=pass
ENV DB_NAME=exhen
ENV CONF_TEMPDIR="./tmp"
ENV CONF_ARCHDIR="./archive"
ENV CONF_IMGDIR="./images"
ENV CONF_SQLDSN="mysql:host=$DB_HOST;dbname=$DB_NAME"
ENV CONF_SPHINXDSN="msql:host=sphinx;port=9306;dbname=exhen"
ENV MEMCACHED_DEPS zlib-dev libmemcached-dev cyrus-sasl-dev
ENV GIT_BRANCH=dev

RUN apk add --no-cache --update libmemcached-libs zlib
RUN set -xe \
    && apk add --no-cache --update --virtual .phpize-deps $PHPIZE_DEPS \
    && apk add --no-cache --update --virtual .memcached-deps $MEMCACHED_DEPS \
    && pecl install memcached \
    && echo "extension=memcached.so" > /usr/local/etc/php/conf.d/20_memcached.ini \
    && rm -rf /usr/share/php7 \
    && rm -rf /tmp/* \
    && apk del .memcached-deps .phpize-deps

RUN apk add --no-cache --update curl-dev jpeg-dev freetype-dev mysql-client \
    && docker-php-ext-install -j$(nproc) mysqli \
    && docker-php-ext-install -j$(nproc) pdo_mysql \
    && docker-php-ext-install -j$(nproc) curl \
    && docker-php-ext-install -j$(nproc) zip \
    && docker-php-ext-install -j$(nproc) exif \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install -j$(nproc) gd

# install jq for manilupating config from bash easily
RUN apk add --update jq openssh-client\
    && rm -rf /var/cache/apk/*

COPY init.d.sh /usr/local/bin/
COPY . /var/www
WORKDIR /var/www

RUN chmod +x /var/www/init.d.sh

CMD ["init.d.sh"]
