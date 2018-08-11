#!/usr/bin/env sh
cd /var/www/ && php TaskRunner.php Audit 2>&1 | logger -st audit
