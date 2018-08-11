Docker Setup
---

###
* Docker
* Docker-compose
* e-hentai account with ExHentai access
* GP to download galleries

Docker is generally the easiest way to get a compatible environment started as it contains all the required packages and configurations to get started.

#### Getting started
The easiest way to get a local (dev/test) environment up and running is by copying the `docker-compose.override.yml` to `docker-compose.override.yml`.
This file will override the default values for configuration.

After copying the override YAML, make sure to replace the `environment` parameters under the `app` section.

* The `CONF_ACCESSKEY` option is a password used for deleting and adding galleries.
* The `CONF_MEMBERID` and `CONF_PASSHASH` should be changed to match your ExHentai cookie.
* The `db` block should stay as it is in most cases, with the exception of `pass`, which you should input whatever password you used for the MySQL setup step.
* On first run add `INIT_DB: 1` to the `environment:` list for `web`. This will run the sql script to create the database
* SphinxQL and memcache should stay as-is.

Run: `docker-compose up` 
Use `-d` flag to detach console and continue running in the background. Note, you will not get the log output by default if you run in detached mode. You'll have to use `docker logs` to check logs in that case, the instructions for that are out of scope for this guide.

Visit `http://localhost` to see the webpage.

#### Adding galleries

Adding galleries is done via a userscript. Open the provided `userscript.js` file in a text editor and change the two values at the top of the code. The `baseUrl` in this example would be http://exhen.localhost and `key` would be the `accessKey` value in the config.json file.

Once edited, add to your browser via Greasemonkey/Tampermonkey.

The script will add a "Send to archive" link to the search results page (in thumbnails view) and on the gallery detail page. Clicking it will send it to the archive to be marked for download. Galleries added to the database will be marked in green on the search results page.

After you have added a few galleries, you can now run the `Archive` task that will download them.

    cd /var/www/vhosts/exhen
    php TaskRunner.php Archive

The task should download the galleries and reindex Sphinx.

Once completed, reload http://exhen.localhost and you should see your galleries appear!

#### Cron

For ideal automation, tasks should be setup in the crontab of your system. Below is an example I use.

    */10 * * * * cd /var/www/vhosts/exhen/ && php TaskRunner.php Archive >> log.txt 2>&1
    0 4 * * * cd /var/www/vhosts/exhen/ && php TaskRunner.php Thumbnails >> log.txt 2>&1
    0 5 * * * cd /var/www/vhosts/exhen/ && php TaskRunner.php Audit >> log.txt 2>&1

There are 3 main tasks in use.

* **Archive** - downloads pending galleries in the database;
* **Audit** - updates meta data for added galleries. Will typically update each gallery periodically for new tags, or add newer versions of that gallery to the database.
* **Thumbnails** - generates thumbs for galleries instead of doing it on-the-fly on the frontend.

#### Extras

If you find the search results are out of sync with the database, you may need to connect to the sphinx container and reindex Sphinx:

    docker-compose run --rm sphinx "sudo -u sphinxsearch indexer --rotate --all"

##### Feeds

Feeds are ways of adding galleries to the database automatically through search terms. The Archive task will run through all feeds in the database, adding new galleries, and optionally downloading these galleries. Support for managing these in a friendly way does not exist.

If you wish to play around with feeds, get a MySQL database admin tool (MySQL Workbench) and take a look at the feeds table. Create a new row with the `term` matching the search term you want (i.e "comic x-ero"), then `download` should be 1 or 0, depending on if you want the archiver to also download the gallery zips.
