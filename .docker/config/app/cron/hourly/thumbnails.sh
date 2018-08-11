#!/usr/bin/env sh
cd /var/www/ && php TaskRunner.php Thumbnails 2>&1 | logger -st thumbnails
