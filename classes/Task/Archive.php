<?php

class Task_Archive extends Task_Abstract {

	public function run($options = array()) {
		$archiver = new Archiver_ExHentai();
		$archiver->start();
	}
	
}


?>
