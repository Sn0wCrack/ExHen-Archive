#!/usr/bin/env sh
/var/www/TaskRunner.php Thumbnails >> 2>&1 | logger -st thumbnails
