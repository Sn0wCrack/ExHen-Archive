#!/usr/bin/env sh
if [ ! -f "/etc/periodic/15min/archive.sh" ]; then
  cp ../.docker/config/app/cron/15min/archive.sh /etc/periodic/15min/archive.sh
  chmod +x /etc/periodic/15min/archive.sh
fi

if [ ! -f "/etc/periodic/hourly/audit.sh" ]; then
  cp ../.docker/config/app/cron/hourly/audit.sh /etc/periodic/hourly/audit.sh
  chmod +x /etc/periodic/hourly/audit.sh
fi

if [ ! -f "/etc/periodic/hourly/thumbnails.sh" ]; then
  cp ../.docker/config/app/cron/hourly/thumbnails.sh /etc/periodic/hourly/thumbnails.sh
  chmod +x /etc/periodic/hourly/thumbnails.sh
fi

