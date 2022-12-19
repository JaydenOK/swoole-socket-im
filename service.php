<?php
/**
 *
 */

error_reporting(-1);
ini_set('display_errors', 1);

require 'bootstrap.php';

$manager = new module\server\SocketServerManager();
$manager->run($argv);
