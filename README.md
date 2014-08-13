ExHentai-Archive
================

A system for crawling, downloading and viewing ExHentai galleries.

Screenshots
---
<img src="https://raw.githubusercontent.com/kimoi/ExHentai-Archive/screenshots/screenshots/screenshot1.jpg" alt="" />

Setup
---

### Requirements


* Apache (other servers are untested, but should work)
* PHP 5.4+
* MySQL 5.5+
* memcached
* Sphinx (2.0+)
* e-hentai account with ExHentai access
* GP to download galleries

This "guide" is a copy and paste-tier walkthough of setup on a vanilla Debian Wheezy system. This site uses standard software, and should run on almost anything. If you have experience in the technologies involved then you will not need to follow this word-for-word.

Windows is generally fine (minus memcache support), though it is outside the scope of this guide.

Most of the following commands will require running as root.

#### Inital setup

First, install dependencies.

    apt-get install apache2 php5 mysql-server php5-memcached php5-mysql php5-curl php5-gd git

If you don't already have MySQL setup, the configurator will ask you to create a password. Note this password for later.

Create the path to hold the site files.

    mkdir -p /var/www/vhosts/exhen
    cd /var/www/vhosts

Clone the directory from git.

    git clone https://github.com/kimoi/ExHentai-Archive.git exhen

Enter the directory and clone the example configuration.

    cd exhen
    cp config.json.example config.json

Open the `config.json` in a text editor an edit the following values.

* The `accessKey` option is a password used for deleting and adding galleries.
* The `ipb_member_id` and `ipb_pass_hash` in the `cookie` block should be changed to match your ExHentai cookie.
* The `tempDir`, `archiveDir` and `imagesDir` all need to point to server-writable folders on your system.
* The `db` block should stay as it is in most cases, with the exception of `pass`, which you should input whatever password you used for the MySQL setup step.
* SphinxQL and memcache should stay as-is.

#### MySQL

Open up the MySQL console.

    mysql -u root -h localhost -p

You should be prompted for your MySQL password, enter it and you will be dropped into the console.

Create the database.

    create database exhen;
    exit;

Import the structure into the created database.

    mysql -u root -h localhost -p exhen < /var/www/vhosts/exhen/db.sql

#### Sphinx

On Debian there are two ways of doing this. You can use the repo version, which doesn't support random ordering, or you can compile the latest release from source.

If you can live without random ordering, grab the repo version.

    apt-get install sphinxsearch

Enable Sphinx to start on boot, edit `/etc/default/sphinxsearch` and change `START=yes`.

Copy the example Sphinx configuration.

    cp sphinx.conf.example /etc/sphinxsearch/sphinx.conf
    cd /etc/sphinxsearch

Open `sphinx.conf` in an editor and change the `sql_pass` parameter at the top to match your MySQL password.

Now create the directories to hold the generated indexes.

    mkdir -p /var/lib/sphinxsearch/data/exhen
    chown sphinxsearch:sphinxsearch /var/lib/sphinxsearch/data/exhen

Build the initial indexes.

    sudo -u sphinxsearch indexer --all --rotate

#### Apache

Enter `/etc/apache2/sites-enabled` and create a `exhen.localhost` config file.

Copy the following, you may need to change the /images alias and <Directory> directive to match the location of your images dir.

    <VirtualHost *:80>
        ServerName exhen.localhost
        ServerAlias exhen.localhost
        ServerAlias exhen.debian

        DocumentRoot /var/www/vhosts/exhen/www
        <Directory /var/www/vhosts/exhen/www>
            Options FollowSymLinks ExecCGI
            AllowOverride None
            Order allow,deny
            allow from all
        </Directory>

        Alias /images /var/www/vhosts/exhen/images
        <Directory /var/www/vhosts/exhen/images>
            AllowOverride None
            Order allow,deny
            allow from all
        </Directory>

        ErrorLog ${APACHE_LOG_DIR}/exhen-error.log
        CustomLog ${APACHE_LOG_DIR}/exhen-access.log combined

    </VirtualHost>

Restart apache.

    sudo apache2ctl -k graceful

Add the appropriate hostname to your `/etc/hosts` file.

    sudo sh -c 'echo "127.0.0.1 exhen.localhost" >> /etc/hosts'

Open up http://exhen.localhost/ in a browser and you should see the default layout with "Displaying 0 of 0 results".

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

If you find the search results are out of sync with the database, you may need to reindex Sphinx:

    sudo -u sphinxsearch indexer --rotate --all

##### Feeds

Feeds are ways of adding galleries to the database automatically through search terms. The Archive task will run through all feeds in the database, adding new galleries, and optionally downloading these galleries. Support for managing these in a friendly way does not exist.

If you wish to play around with feeds, get a MySQL database admin tool (MySQL Workbench) and take a look at the feeds table. Create a new row with the `term` matching the search term you want (i.e "comic x-ero"), then `download` should be 1 or 0, depending on if you want the archiver to also download the gallery zips.