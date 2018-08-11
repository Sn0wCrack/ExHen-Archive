#!/usr/bin/env sh
/var/www/TaskRunner.php Archive >> 2>&1 | logger -st archive
