#!/usr/bin/env sh
echo "Starting up"
for f in docker-entrypoint-init.d/*; do
    case "$f" in
        *.sh)
            # https://github.com/docker-library/postgres/issues/450#issuecomment-393167936
            # https://github.com/docker-library/postgres/pull/452
            if [ -x "$f" ]; then
                echo "$0: running $f"
                "$f"
            else
                echo "$0: sourcing $f"
                . "$f"
            fi
            ;;
        *)        echo "$0: ignoring $f" ;;
    esac
done

echo
echo 'init process complete; ready for start up.'
echo

if [[ -z "$@" ]]; then
  # Start supervisord and services
  exec /usr/bin/supervisord --nodaemon -c /etc/supervisord.conf
else
  exec "$@"
fi

