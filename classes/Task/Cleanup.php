<?php

class Task_Cleanup extends Task_Abstract {
	
	const LOG_TAG = 'Task_Cleanup';
	
	public function run($options = array()) {
		Log::debug(self::LOG_TAG, 'Checking for NULL galleryproperty entries');
		
		$entries = R::getAll('SELECT * FROM galleryproperty WHERE gallery_id IS NULL');
		if (count($entries) > 0) {
			$count = 0;
			Log::debug(self::LOG_TAG, 'NULL entries found, deleting them now.');
			foreach($entries as $entry) {
				$book = R::load('galleryproperty', $entry["id"]);
				R::trash($book);
				$count++;
			}
			Log::debug(self::LOG_TAG, 'All NULL entries deleted. Total deleted was %d', $count);
		}
		else {
			Log::debug(self::LOG_TAG, 'No NULL galleryproperty entries found.');
		}
	}
	
}

?>