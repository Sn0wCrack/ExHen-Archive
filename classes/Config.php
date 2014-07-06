<?php

class Config {
	
	protected static $config;

	protected function __construct() {

	}

	public static function get() {
		if(!self::$config) {
			$data = json_decode(file_get_contents('config.json'));

			$config = $data->base;

			$host = gethostname();
			self::processEntry($config, $data, $host);

			self::$config = $config;
		}

		return self::$config;
	}

	protected static function processEntry(&$config, $data, $key) {
		if(property_exists($data, $key)) {
			if(property_exists($data->$key, 'inherits')) {
				self::processEntry($config, $data, $data->$key->inherits);
			}

			foreach($data->$key as $key => $value) {
				$config->$key = $value;
			}
		}
	}
}

?>
