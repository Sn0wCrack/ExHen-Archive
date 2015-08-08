Windows Setup
---

### Requirements


* Apache or nginx (other servers are untested, but should work)
* PHP 5.4+
* MySQL 5.5+ or MariaDB 5.5+
* memcached
* Sphinx any version from 2.0 to 2.1.8 (anything above is untested and may not work due to now deprecated function)
* phpMyAdmin
* e-hentai account with ExHentai access
* GP to download galleries

Guide is still a work in progress, use the Linux one as a skeleton if you know what you're doing.

#### Inital setup

First, install dependencies:

* Apache: [http://www.apachelounge.com/download/](http://www.apachelounge.com/download/) - Get the **win32 version**
* PHP: [http://windows.php.net/download/](http://windows.php.net/download/) - Get the **Thread Safe x86 version**
* MariaDB / MySQL: [https://downloads.mariadb.org/mariadb/5.5.45/#file_type=zip&os_group=windows](https://downloads.mariadb.org/mariadb/5.5.45/#file_type=zip&os_group=windows)
* memcached: [http://code.jellycan.com/files/memcached-1.2.6-win32-bin.zip](http://code.jellycan.com/files/memcached-1.2.6-win32-bin.zip)
* Sphinx 2.1.8: [http://sphinxsearch.com/downloads/sphinx-2.1.8-release-win32.zip/thankyou.html](http://sphinxsearch.com/downloads/sphinx-2.1.8-release-win32.zip/thankyou.html)
* phpMyAdmin: [https://www.phpmyadmin.net/downloads/](https://www.phpmyadmin.net/downloads/)

Create a folder somewhere and call it whatever you want, for example `server` or `exhen`.
Extract Apache, PHP, MariaDB, memcached and Sphinx into their own folders with the same names.

Then extract the contents of this repo into the folder in the Apache folder `htdocs`

Open the `config.json.win32` in a text editor an edit the following values.

* The `accessKey` option is a password used for deleting and adding galleries.
* The `ipb_member_id` and `ipb_pass_hash` in the `cookie` block should be changed to match your ExHentai cookie.
* The `tempDir`, `archiveDir` and `imagesDir` all need to point to server-writable folders on your system.
* The `db` block should stay as it is in most cases, with the exception of `pass`, which you should input whatever password you used for the phpMyAdmin setup step.
* SphinxQL change the `full` option to point to where ever you installed Sphinx too.
* memcached should stay the same.


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


#### phpMyAdmin and MariaDB / MySQL

Extract the phpmyAdmin-version-all.7z file into the Apache folder `htdocs` under a folder named `phpMyAdmin` (so `apache\htdocs\phpMyAdmin\...`)

Run `httpd.exe` and `mysqld.exe`. and go to `http://localhost/phpMyAdmin` in your browser.

Follow the prompts given my phpMyAdmin and it should all be setup.

Go to the import tab and click the Browse button, and find the `db.sql` file from the repo files and press go.

This will setup the database for you.


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
