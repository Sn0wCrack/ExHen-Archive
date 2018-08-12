#!/usr/bin/env sh
if [ -f "config.json.linux" ]; then
echo "Updating config.json"

jq '.base.cookie.ipb_member_id=env.CONF_MEMBERID | .base.cookie.ipb_pass_hash=env.CONF_PASSHASH | .base.cookie.ipb_accesskey=env.CONF_ACCESSKEY | .base.tempDir=env.CONF_TEMPDIR | .base.archiveDir=env.CONF_ARCHDIR | .base.imagesDir=env.CONF_IMGDIR | .default.db.dsn=env.CONF_SQLDSN | .default.db.user=env.DB_USER | .default.db.pass=env.DB_PASS | .default.sphinxql.dsn=env.CONF_SPHINXDSN | .default.memcache.host="memcache"' config.json.linux > config.json
cp config.json.linux www/config.json
echo "Updated config"
fi
