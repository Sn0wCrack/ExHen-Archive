#!/usr/bin/env sh
cd /var/www/ && php TaskRunner.php Archive 2>&1 | logger -st archive
