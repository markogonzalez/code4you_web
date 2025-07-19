<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
include_once("config.inc.php");
include_once(core . "core.php");
$core = new Core;
$core->service();
?>