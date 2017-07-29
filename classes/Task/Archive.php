<?php

class Task_Archive extends Task_Abstract {

	public function run($options = array()) {
		$archiver = new ExArchiver();
		if (isset($options[0])) {
			$opt = strtolower($options[0]);

			if ($opt == "feed") {
				$archiver->start((int)$options[1]);
			} else {
				$archiver->start();
			}
		} else {
			$archiver->start();
		}
		
	}
	
}


?>
