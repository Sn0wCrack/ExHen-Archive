Windows Setup
---

### Requirements


* Apache or nginx (other servers are untested, but should work)
* PHP 5.4+
* MySQL 5.5+ or MariaDB 5.5+
* memcached
* Sphinx
* phpMyAdmin
* Either Firefox or Chrome with Greasemonkey or Tampermonkey installed.
* e-hentai account with ExHentai access
* GP to download galleries

Guide is still a work in progress, use the Linux one as a skeleton if you know what you're doing.

#### Inital setup

First, install dependencies:

* Apache: [http://www.apachelounge.com/download/](http://www.apachelounge.com/download/) - Get the **win32 version**
* PHP: [http://windows.php.net/download/](http://windows.php.net/download/) - Get the **Thread Safe x86 version**
* MariaDB / MySQL: [https://downloads.mariadb.org/mariadb/5.5.45/#file_type=zip&os_group=windows](https://downloads.mariadb.org/mariadb/5.5.45/#file_type=zip&os_group=windows)
* memcached: [http://code.jellycan.com/files/memcached-1.2.6-win32-bin.zip](http://code.jellycan.com/files/memcached-1.2.6-win32-bin.zip)
* Sphinx: [http://sphinxsearch.com/downloads/release/](http://sphinxsearch.com/downloads/release/)
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

Afterwards, rename `config.json.win32` to `config.json` and then copy it into the `www` directory as well, making sure to keep both copies.

#### Apache

Enter `apache\conf\` and open the `httpd.conf` file in your text editor.

Change any references of `c:/Apache##/` with the location of your Apache server's location.

Copy the following, you may need to change the /images alias and <Directory> directive to match the location of your images directory.

	DocumentRoot C:\path\to\apache\htdocs\exhen
	<Directory C:\path\to\apache\www\htcdocs\exhen>
    	Options FollowSymLinks ExecCGI
    	AllowOverride None
    	Order allow,deny
    	allow from all
 	</Directory>

	Alias /images C:\path\to\apache\htdocs\exhen\images
	<Directory C:\path\to\apache\htdocs\exhen\images>
    	AllowOverride None
    	Order allow,deny
    	allow from all
	</Directory>

	ErrorLog ${APACHE_LOG_DIR}/exhen-error.log
	CustomLog ${APACHE_LOG_DIR}/exhen-access.log combined

Restart Apache.

Add the appropriate hostname to your `C:\Windows\system32\drivers\etc\hosts` file.

Open it in the text editor of choice and add the line 

	127.0.0.1 exhen.localhost

Change exhen.localhost with whatever your server's name is. You don't have to do this if you just want to keep localhost or use 127.0.0.1.

Open up http://exhen.localhost/ in a browser and you should see the default layout with "Displaying 0 of 0 results".


#### phpMyAdmin and MariaDB / MySQL

Extract the phpmyAdmin-version#-all.7z file into the Apache folder `htdocs` under a folder named `phpMyAdmin` (so `apache\htdocs\phpMyAdmin\...`)

Run `httpd.exe` and `mysqld.exe`. and go to `http://localhost/phpMyAdmin` in your browser.

Follow the prompts given my phpMyAdmin and it should all be setup.

Go to the import tab and click the Browse button, and find the `db.sql` file from the repo files and press go.

This will setup the database for you.


#### Sphinx

To setup Sphinx you must first rename the file `sphinx.conf.win32` to `sphinx.conf` and then open it.
Under the `source connect` structure change the `sql_user`, `sql_pass` and any other value to suit your MySQL / MariaDB setup. 

On Windows you have the option to install Sphinx as a service, this way it will start with your computer whenever you turn it on.

This can be done by opening a command window in the `bin` directory of the Sphinx root folder and running this command:

	searchd.exe --install --config "C:\path\to\sphinx.conf"

Of course you can just open Sphinx manually every time you want to run it with this command:

	searchd.exe --config "C:\path\to\sphinx.conf"

After you have either setup a script to open Sphinx using the previous command or have installed it as a service, close it down / end the service and run this command from the `bin` directory

    indexer.exe --config "C:\path\to\sphinx.conf --all --rotate

This will setup all the needed indexes for Sphinx syncing with the data from the database. 

#### memcahced

Open a command window in the memcached folder and type in the command:

	memcached.exe -d start

This will install memcached as a service on Windows and will start with your computer from now on.

You can change the memory pool size (currently defaults to 64MB, which seems fine for me) with the command:

	memcached.exe -m SIZE_IN_MBS

#### Adding galleries

Adding galleries is done via a userscript. Open the provided `userscript.js` file in a text editor and change the two values at the top of the code. The `baseUrl` in this example would be http://exhen.localhost and `key` would be the `accessKey` value in the config.json file.

Once edited, add to your browser via Greasemonkey/Tampermonkey.

The script will add a "Send to archive" link to the search results page (in thumbnails view) and on the gallery detail page. Clicking it will send it to the archive to be marked for download. Galleries added to the database will be marked in green on the search results page.

After you have added a few galleries, you can now run the `Archive` task that will download them.

    cd  C:\path\to\apache\htdocs\exhen\
    php TaskRunner.php Archive

The task should download the galleries and reindex Sphinx.

Once completed, reload http://exhen.localhost and you should see your galleries appear!

#### Task Scheduler

You can setup a Task through Windows built in Task Scheduler.

First create a simple script using Batch or any language of your choice that will automate the download process

Below is an example:

	@echo off
	E:\NPMDB\php\php.exe TaskRunner.php Archive
	E:\NPMDB\php\php.exe TaskRunner.php Thumbnails
	E:\NPMDB\php\php.exe TaskRunner.php Audit
	exit

Then opening up the Task Scheduler and right click and go to `Create Basic Task...` and follow the prompts. When asked for a script or program open the script you've created.

There are 3 main tasks in use.

* **Archive** - downloads pending galleries in the database;
* **Audit** - updates meta data for added galleries. Will typically update each gallery periodically for new tags, or add newer versions of that gallery to the database.
* **Thumbnails** - generates thumbs for galleries instead of doing it on-the-fly on the frontend.

#### Extras

If you find the search results are out of sync with the database, you may need to reindex Sphinx:

	indexer --config "C:\path\to\sphinx.conf --rotate --all

##### Feeds

**NOTE**: I've never used this personally, so this is the same as the old guide.

Feeds are ways of adding galleries to the database automatically through search terms. The Archive task will run through all feeds in the database, adding new galleries, and optionally downloading these galleries. Support for managing these in a friendly way does not exist.

If you wish to play around with feeds, get a MySQL database admin tool (MySQL Workbench) and take a look at the feeds table. Create a new row with the `term` matching the search term you want (i.e "comic x-ero"), then `download` should be 1 or 0, depending on if you want the archiver to also download the gallery zips.
