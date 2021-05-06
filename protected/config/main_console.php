<?php
define('SESSION_DURATION', 86400);
setlocale(LC_ALL, 'ru_RU.utf8');
setlocale(LC_NUMERIC, "C");
set_time_limit(600);

if(YII_DEBUG)
{
	error_reporting(E_ALL ^ E_NOTICE);
	ini_set('display_errors', 'On');
	ini_set('display_startup_errors', 'On');
	$config = require(dirname(__FILE__).'/prod.php');
	$modules = require(dirname(__FILE__).'/modules.php');
}
else
{
	error_reporting(0);
	ini_set('display_errors', 'Off');
	ini_set('display_startup_errors', 'Off');
	$config = require(dirname(__FILE__).'/prod.php');
	$modules = require(dirname(__FILE__).'/modules.php');
}

require_once realpath(__DIR__.'/../').'/vendor/autoload.php';

//ini_set('date.timezone', 'UTC+12'); //'Europe/Moscow');

session_start();
ini_set('session.gc_maxlifetime', SESSION_DURATION);
ini_set('session.cookie_lifetime', SESSION_DURATION);
header("Content-Type: text/html;charset=utf-8");

return array(
	'basePath'=>dirname(__FILE__).DIRECTORY_SEPARATOR.'..',
	'name'=>'Uni',
    //'sourceLanguage'=>'ru',
    //'language'=>'ru',
    
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

		'application.modules.sim.models.*',
		'application.modules.sim.controllers.*',
		'application.modules.sim.views.*',
		'application.modules.sim.components.*',

		'application.modules.card.models.*',
		'application.modules.card.controllers.*',
		'application.modules.card.views.*',
		'application.modules.card.components.*',

		'application.modules.merchant.models.*',
		'application.modules.merchant.controllers.*',
		'application.modules.merchant.views.*',
		'application.modules.merchant.components.*',

		'application.modules.walletS.controllers.*',
		'application.modules.walletS.views.*',
		'application.modules.walletS.components.*',
		'application.modules.walletS.models.*',

		'application.modules.p2pService.models.*',
		'application.modules.p2pService.views.*',
		'application.modules.p2pService.components.*',
	),

	'components'=>array(
        
		'db'=>$config['db'],
				
		'user'=>array(
        	'loginUrl'=>array('site/login'),
        ),

		'cache'=>array('class'=>'system.caching.CFileCache'),

		/*
        'noticeAdmin' => array(
			'class' => 'ext.noticeAdmin.NoticeAdmin',
			'url'=>'http://.../notice/?r=notificator/send&key={key}&text={text}',
			'key'=>'fdsaf09ewahfbjadf80',
			'interval'=>30*60,	//минимальный интервал между уведомлениями(сек)
			'dataFile'=>DIR_ROOT.'protected/runtime/noticeAdmin.json',
		),
		*/

		'qiwiApi'=>[
			'class' => 'application.components.QiwiApi',
		],

		'qiwiMobile'=>[
			'class' => 'application.components.QiwiMobile',
		],

		'exmoApi'=>[
			'class' => 'application.components.ExmoApi',
		],
	),
 
    'params'=>$config['params'],
    'timezone'=>"Etc/GMT-3",
	'modules'=>$modules,
);