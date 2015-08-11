Windows Setup
---

### Editing

Opening up the `userscript.user.js` file from the root of the repository you'll be presented with the code for the userscript, the main two lines you need to edit are:

	var baseUrl = 'http://your.archive.url.com/';
	var key = 'changeme';

For `baseUrl` you'll want to change it to whatever URL your local server is addressed to, the case of all guides it's `http://exhen.localhost/`

For `key` this is the value you configured in your `config.json` file.


### Installing

Now to install the userscript just drag and drop it into your browser, this will open it prompting what ever userscript engine you have to ask if you want to install it.

Or you can click File -> Open and then open the file, either way the file will be open.