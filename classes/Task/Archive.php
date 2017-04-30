<?php

class Task_Archive extends Task_Abstract {

	public function run($options = array()) {
		$archiver = new ExArchiver();
		if ($options[0] == "Feed") {
			$archiver->start((int)$options[1]);
		} else {
			$archiver->start();
		}
		
	}
	
}


?>
