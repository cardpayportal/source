<?php
define('SESSION_DURATION', 604800);
setlocale(LC_ALL, 'ru_RU.utf8');
set_time_limit(0);

if(YII_DEBUG)
{
	error_reporting(E_ALL ^ E_NOTICE);
	ini_set('display_errors', 'On');
	ini_set('display_startup_errors', 'On');
	$config = require(dirname(__FILE__).'/prod.php');
}
else
{
	error_reporting(0);
	ini_set('display_errors', 'Off');
	ini_set('display_startup_errors', 'Off');
	$config = require(dirname(__FILE__).'/prod.php');
}

//ini_set('date.timezone', 'UTC+12'); //'Europe/Moscow');

session_start();
ini_set('session.gc_maxlifetime', SESSION_DURATION);
ini_set('session.cookie_lifetime', SESSION_DURATION);
header("Content-Type: text/html;charset=utf-8");



return array(
	'defaultController'=>'site',
	'basePath'=>dirname(__FILE__).DIRECTORY_SEPARATOR.'..',
	'name'=>'Cron',
	//'sourceLanguage'=>'ru',
	'language'=>'ru',

	'preload'=>array('log'),

	'import'=>array(
		'application.models.*',
		'application.components.*',
		'application.extensions.yiidebugtb.*',
		'application.controllers.*',
		'application.modules.payeer.components.*',	//как бы обойтись без этой строки
		'application.modules.payeer.models.*',	//как бы обойтись без этой строки
		'application.modules.newYandexPay.components.*',
		'application.modules.newYandexPay.models.*',
		'application.modules.pay.models.*',
		'application.modules.pay.controllers.*',
		'application.modules.pay.views.*',
		'application.modules.yandexAccount.components.*',
		'application.modules.yandexAccount.models.*',
		'application.modules.yandexAccount.controllers.*',
		'application.modules.yandexAccount.views.*',
		'application.modules.qiwi.components.*',
		'application.modules.qiwi.models.*',
		'application.modules.qiwi.controllers.*',
		'application.modules.qiwi.views.*',
		'application.modules.p2pService.models.*',
		'application.modules.p2pService.views.*',
		'application.modules.p2pService.components.*',
	),

	'components'=>array(

		'db'=>$config['db'],

		'user'=>array(
			'loginUrl'=>array('site/login'),
		),

		/*
        'noticeAdmin' => array(
			'class' => 'ext.noticeAdmin.NoticeAdmin',
			'url'=>'http://.../notice/?r=notificator/send&key={key}&text={text}',
			'key'=>'fdsaf09ewahfbjadf80',
			'interval'=>30*60,	//минимальный интервал между уведомлениями(сек)
			'dataFile'=>DIR_ROOT.'protected/runtime/noticeAdmin.json',
		),
		*/
	),

	'params'=>$config['params'],
	'timezone'=>"Etc/GMT-3",
);