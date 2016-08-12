<?php

require_once 'common.php';

const LOG_TAG = 'TaskRunner';

$web = false;
$task = "";
$web_opts = array();

if (isset($_GET["task"])) {
    $task = $_GET["task"];
    if (isset($_GET["opts"])) {
        $web_opts = $_GET["opts"];
    }
    header('Content-Type: text/html; charset=utf-8');
    $web = true;
}

if($argc < 2 && !$web) {
	printf("Usage: TaskRunner.php <name of task>\n");
    printf("List of avaliable Tasks: \n");
    $tasks = array_diff(scandir("./classes/Task"), array("..", "."));
    for ($i = 2; $i < count($tasks) + 2; $i++) {
        printf(" * " . $tasks[$i] . "\n");
    }
    exit;
}

if ($web) {
    $name = 'Task_'.$task;
} else {
    $name = 'Task_'.$argv[1];
}
if(!class_exists($name) && $task != "Full") {
	Log::error(LOG_TAG, 'Failed to load class: %s', $name);
	return;
}

$pidFile = Config::get()->tempDir.DS.$name.'.pid';

if (!file_exists(Config::get()->tempDir)) {
    mkdir(Config::get()->tempDir);
}

if(file_exists($pidFile)) {
	$pid = file_get_contents($pidFile);
	if(processExists($pid)) {
		Log::error(LOG_TAG, 'Task already running: %s', $name);
		exit;
	}
}

file_put_contents($pidFile, getmypid());

if (!$web) {
    $opts = $argv;
    $opts = array_slice($opts, 2);
} else {
    $opts = $web_opts;
}

if ($web) {
    echo "Running Task: " . $name . "<br>";
} else {
    Log::debug(LOG_TAG, 'Running task %s', $name);
}

if ($task == "Full") {
    $archive = new Task_Archive();
    $archive->run($opts);
    
    echo "<br>";
    
    $thumbnails = new Task_Thumbnails();
    $thumbnails->run($opts);
    
    echo "<br>";
    
    $audit = new Task_Audit();
    $audit->run($opts);
    
    echo "<br>";
    
    $command = Config::get()->indexer->full;
    $output = "";
    exec($command . " 2>&1", $output, $ret);
    print_r($output);
    
    echo "<br>";
} else {
    $task = new $name();
    $task->run($opts);
}

if ($web) {
    echo "Finished Task ". $name . "<br>";
} else {
    Log::debug(LOG_TAG, 'Finished running task %s', $name);
}

unlink($pidFile);

if ($web) {
    echo "Finished running task.";
}
    
function processExists($pid) {
	if(function_exists('posix_kill')) {
		return posix_kill($pid, 0);
	}
	else {
		$output = array();
		exec('tasklist /FI "PID eq ' . $pid . '"', $output);
		return empty($output);
	}
}

?>