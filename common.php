<?php

define('DS', DIRECTORY_SEPARATOR);

set_include_path(realpath('classes').PATH_SEPARATOR.get_include_path());

spl_autoload_register(function($className) {
	$className = str_replace("\\", DS, $className);
	$filename = str_replace('_', DS, $className).'.php';

	if(stream_resolve_include_path($filename)) {
		include $filename;
	}
});

if(php_sapi_name() !== 'cli') {
	//Auth::doAuth();
}

require 'classes/rb.php';

$config = Config::get();

R::setup($config->db->dsn, $config->db->user, $config->db->pass);
R::freeze(true);


?>
