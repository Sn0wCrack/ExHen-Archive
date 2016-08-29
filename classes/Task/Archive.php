<?php

class Task_Archive extends Task_Abstract {

	public function run($options = array()) {
		$archiver = new ExArchiver();
		$archiver->start();
	}
	
}


?>
