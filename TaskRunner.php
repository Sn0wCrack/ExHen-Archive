<?php

require_once 'common.php';

const LOG_TAG = 'TaskRunner';

if($argc < 2) {
	printf("Usage: TaskRunner.php <name of task>\n");
}

$name = 'Task_'.ucfirst(strtolower($argv[1]));
if(!class_exists($name)) {
	Log::error(LOG_TAG, 'Failed to load class: %s', $name);
	return;
}

$pidFile = Config::get()->tempDir.DS.$name.'.pid';

if(file_exists($pidFile)) {
	$pid = file_get_contents($pidFile);
	if(processExists($pid)) {
		Log::error(LOG_TAG, 'Task already running: %s', $name);
		exit;
	}
}

file_put_contents($pidFile, getmypid());

$opts = $argv;
$opts = array_slice($opts, 2);

Log::debug(LOG_TAG, 'Running task %s', $name);
$task = new $name();
$task->run($opts);
Log::debug(LOG_TAG, 'Finished running task %s', $name);

unlink($pidFile);

function processExists($pid) {
	if(function_exists('posix_kill')) {
		return posix_kill($pid, 0);
	}
	else {
		return false; //windows
	}
}

?>