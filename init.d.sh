#!/usr/bin/env sh
echo
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
    echo
done

echo
echo 'init process complete; ready for start up.'
echo

exec php-fpm
