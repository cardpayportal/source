<?php
define('YII_DEBUG', true);
define('DIR_ROOT', dirname(__FILE__).'/');

$config = DIR_ROOT.'protected/config/main.php';

require_once(realpath(DIR_ROOT.'../yii/framework/yii.php'));

require_once(DIR_ROOT.'protected/functions/yii.php');

/*
if(!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == ""){
$redirect = "https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
header("HTTP/1.1 301 Moved Permanently");
header("Location: $redirect");
}
*/
Yii::createWebApplication($config)->run();
