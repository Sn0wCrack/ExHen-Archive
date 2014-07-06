<?php

chdir('../');
require 'common.php';

$api = new ApiHandler();
$api->handle($_REQUEST);

?>