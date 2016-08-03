Windows Setup
---

### Requirements

 * Vagrant ( [https://www.vagrantup.com/downloads.html]([https://www.vagrantup.com/downloads.html) )
 * VirtualBox ( [https://www.virtualbox.org/wiki/Downloads](https://www.virtualbox.org/wiki/Downloads) )
 * VirtualBox Extension Pack ( [https://www.virtualbox.org/wiki/Downloads]( [https://www.virtualbox.org/wiki/Downloads](https://www.virtualbox.org/wiki/Downloads) ) )
 * ExHentai-Archive ( [https://github.com/Sn0wCrack/ExHentai-Archive/archive/master.zip](https://github.com/Sn0wCrack/ExHentai-Archive/archive/master.zip) )
 

### How-To

Extract the contents of ExHentai-Archive in a safe place, this will be the program's home and will stay there and where it will download all your archives. So I reccomend a drive with a large storage space.

First we're going to edit some configuration files, the ones we want to focus on have the ```.linux``` extension, mainly the config.json.linux file.

```
{
	"base": {
		"accessKey": "changeme",
		"viewType": "mpv",
		"cookie": {
            "ipb_member_id": "changeme",
            "ipb_pass_hash": "changeme",
            "hath_perks": "m1.m2.m3.tf.t1.t2.t3.p1.p2.s-210aa44613",
            "uconfig": "tl_m-uh_y-tr_2-ts_m-prn_y-dm_l-ar_1-cats_350-xns_0-xl_1024x2048x1x2049x10x1034x2058x20x1044x2068x30x1054x2078x40x1064x2088x50x1074x2098x60x1084x2108x70x1094x2118x80x1104x2128x90x1114x2138x100x1124x2148x110x1134x2158x120x1144x2168x130x1154x2178x255x1279x2303-rc_2-rx_0-ry_0-ms_n-mt_n-sa_y-oi_n-qb_n-tf_n-hp_-hk_"
        },
		"tempDir": "../temp",
        "archiveDir": "../archive",
        "imagesDir": "../images"
	},
	"default": {
		"db": {
			"dsn": "mysql:host=localhost;dbname=exhen",
			"user": "root",
			"pass": "changeme"
		},
		"sphinxql": {
			"dsn": "mysql:host=127.0.0.1;port=9306;dbname=exhen",
			"user": "root",
			"pass": ""
		},
		"memcache": {
			"host": "localhost",
			"port": 11211
		},
		"indexer": {
			"full": "sudo indexer --rotate galleries suggested"
		}
	}
}
```

Inside the configuration file you'll see this, the three major things you need to edit are ```"accessKey"```, ```"ipb_member_id"``` and ```"ipb_pass_hash"```
accessKey will be password used to confirm you wish to delete archives as well as a passphrase between the browser and server to make sure you're sending a gallery to be archived.
So you can really change this to anything you want, or keep it as changeme, all other changeme instances, can remain the same if you wish. (This is extra setup, chaning all configuration files to make this password.)

To get ipb_member_id and ipb_pass_hash you have to find your e-hentai.org cookie, to find this you have to browse through your browsers cookies.

* On firefox you can access this under the options menu under the Privacy tab then clicking "remove individual cookies" and searching for e-hentai and copy pasting the "Content" section into the sections of the configuration file needed 

Now we need to install VirtualBox, download the binary specific to you under the **VirtualBox plaform packages** header.
After VirtualBox finishes installing, download the VirtualBox Extension Pack and open it, agreeing to any prompts VirtualBox gives to you.

After that, install Vagrant to your system, and make sure to have restarted your computer after installing it (this may or may not be an issue, just do it just in case.)

Open a command prompt window and type in ```vagrant plugin install vagrant-vbguest``` and then close the command prompt window after it has finished downloading the plugin.

If you've changed any other passwords in the config.json.linux file please change the following to the be the same:
 * sphinx.conf.linux under ```source connect``` option called ```sql_pass```
 * bootstrap.sh option called ```MYSQL_PASSWORD```

Open a new command prompt winodw in the directory where your extracted ExHentai-Archive too and type in ```vagrant up``` this will begin a lengthy process of installing and downloading updates so please give it some time.

Afterwards everything should be running smoothly and your vagrant box should be up and running, you can check this by going to ```https://localhost``` in your web browser of choice. **The https is highly important.**

After this you can proceed to [userscript setup](https://github.com/Sn0wCrack/ExHentai-Archive/blob/master/setup/Userscript-Setup.md) using the baseUrl as "https://localhost/" and key as whatever you set previously.

If you're on Windows (I assume most using this guide would be) then you can schedule a task to run to open the vagrant box at start up so you don't have to it manually by making a batch file called ```startup.bat``` in the ExHentai-Archive folder similar to the following:
```
@echo off
vagrant up
exit
```
Then opening "Task Scheduler" and pressing "Action" and "Create Basic Action" setting a name for it, then the rest is self explanatory, when it ask for a program file the .bat file you just made.\

Now you can access https://localhost without doing a thing.


### Archiving

After the initial setup is complete and you've setup your userscript in your browser of choice, to download your archives you'll want to goto ```https://localhost/vagrant/TaskRunner.php?task=Full``` in your browser, this will invoke the Archive, Thumbnails and Auit actions, however due to some limitations you won't be able to get amazingly formatted output.

After sending galleries to archive, you **NEED** to do this, this is what actually downloads and archives the files to your computer.

Enjoy it.
