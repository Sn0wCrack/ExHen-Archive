#!/usr/bin/env sh
/var/www/TaskRunner.php Audit >> 2>&1 | logger -st audit
