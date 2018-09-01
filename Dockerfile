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

RUN apk add --no-cache --update libmemcached-libs zlib nginx libressl pcre zlib supervisor sed re2c m4 ca-certificates py-pip \
    && mkdir -p /run/nginx/ \
    && chmod ugo+w /run/nginx/ \
    && rm -f /etc/nginx/nginx.conf \
    && mkdir -p /etc/nginx/conf.d/ \
    && mkdir -p /etc/nginx/ssl/ \
    && mkdir -p /var/www/html/ \
    && mkdir -p /var/log/php/ \
    && chmod -R 755 /var/www/ \
    && chown -R nginx:nginx /var/www/ \
    && chmod -R 755 /var/log/php \
    && pip install --upgrade pip \
    && pip install supervisor-stdout

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

# Set logging to console output by creating symlinks
RUN ln -sf /dev/stderr /var/log/nginx/error.log \
    && ln -sf /dev/stderr /var/log/nginx/sf4_error.log \
    && ln -sf /dev/stdout /var/log/nginx/sf4_access.log \
    && ln -sf /dev/stderr /var/log/fpm-php.www.log

# install jq for manilupating config from bash easily
RUN apk add --update jq openssh-client\
    && rm -rf /var/cache/apk/*

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/bin --filename=composer \
    && rm composer-setup.php \
    && chmod +x /usr/bin/composer \
    && rm /usr/local/etc/php-fpm.d/zz-docker.conf

COPY init.d.sh /usr/local/bin/
COPY ./.manifest/ /
COPY . /var/www/html
WORKDIR /var/www/html

RUN composer install --no-dev --optimize-autoloader

#cleanup
RUN rm -rf .git/

CMD ["chmod +x init.d.sh && init.d.sh"]
