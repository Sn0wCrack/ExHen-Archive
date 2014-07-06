<?php

class SphinxQL {

	private static $instance = false;
	
	//private constructr
	private function __construct() {
		$config = Config::get();

		R::addDatabase('sphinx', $config->sphinxql->dsn, $config->sphinxql->user, $config->sphinxql->pass, true);
	}
	
	public static function getInstance() {
		if(!self::$instance) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
	public static function query($sql, $params = array()) {
		self::getInstance();
	
		R::selectDatabase('sphinx');
		
		$result = R::getAll($sql, $params);
		
		R::selectDatabase('default');
		
		return $result;
	}

	public static function getMeta() {
		self::getInstance();
	
		R::selectDatabase('sphinx');
		
		$result = R::getAll('show meta');

		$obj = new stdClass();
		foreach($result as $param) {
			$name = $param['Variable_name'];
			$value = $param['Value'];

			if(preg_match('/^(.*)\[\d*\]$/', $name, $matches) === 1) {
				$name = $matches[1];

				if(!isset($obj->$name) || !is_array($obj->$name)) {
					$obj->$name = array();
				}

				array_push($obj->$name, $value);
			}
			else {
				$obj->$name = $value;
			}
		}

		R::selectDatabase('default');
		
		return $obj;
	}
	
	public static function getIds($result) {
		$ids = array();
		foreach($result as $row) {
			$ids[] = $row['id'];
		}
		
		return $ids;
	}

	public static function escape($input) {
		return strtr($input, array(
			'('=>'\\\\(',
			')'=>'\\\\)',
			'|'=>'\\\\|',
			'-'=>'\\\\-',
			'@'=>'\\\\@',
			'~'=>'\\\\~',
			'&'=>'\\\\&',
			'\''=>'\\\'',
			'<'=>'\\\\<',
			'!'=>'\\\\!',
			'"'=>'\\\\"',
			'/'=>'\\\\/',
			'*'=>'\\\\*',
			'$'=>'\\\\$',
			'^'=>'\\\\^',
			'\\'=>'\\\\\\\\')
		);
	}
}

?>