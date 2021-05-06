<?php
error_reporting(0);
ini_set('display_errors', 'Off');
ini_set('display_startup_errors', 'Off');

require_once(__DIR__.'/protected/components/Tools.php');

echo Tools::getClientIp();
//print_r($_SERVER);
