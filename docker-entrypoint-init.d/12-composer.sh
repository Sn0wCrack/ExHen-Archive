#!/usr/bin/env sh
if [ ! -f /usr/bin/composer ]; then
    echo "Installing composer"
    EXPECTED_SIGNATURE="$(wget -q -O - https://composer.github.io/installer.sig)"
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    ACTUAL_SIGNATURE="$(php -r "echo hash_file('SHA384', 'composer-setup.php');")"

    if [ "$EXPECTED_SIGNATURE" != "$ACTUAL_SIGNATURE" ]
    then
        >&2 echo 'ERROR: Invalid installer signature'
        rm composer-setup.php
        exit 1
    fi

    php composer-setup.php --install-dir=/usr/bin --filename=composer
    RESULT=$?
    rm composer-setup.php

    chmod +x /usr/bin/composer
else
    echo "Composer already installed"
fi

cd /var/www
php /usr/bin/composer install
