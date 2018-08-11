#!/usr/bin/env sh
if [ "${INIT_DB:-}" ]; then
  if [ -f "/var/www/db.sql" ]; then
    mysql -u $DB_USER -h $DB_HOST -p$DB_PASS $DB_NAME < /var/www/db.sql
  fi
fi
