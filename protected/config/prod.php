<?php

$db_config = require(dirname(__FILE__).'/db_config.php');

define('MAX_E_TIME', 55);
define('START_TIME', time());

return array(

		'db'=>array(
				'connectionString' => "mysql:host=$db_config[host];dbname=$db_config[name]",
				'username' => $db_config['user'],
				'password' => $db_config['pass'],
				'charset' => $db_config['charset'],
				'tablePrefix' => $db_config['table_prefix'],
				'schemaCachingDuration' => 1,
		),

		'params'=>array(
				'logFile'=>DIR_ROOT.'protected/log/log.txt',
				'logDir'=>DIR_ROOT.'protected/log/',
				'maxLogSize'=>1024*1024*2,	//mb
				'cron_pass'=>'cronpasswd',
				'threader_dir'=>DIR_ROOT.'protected/runtime/threader/',
				'auth_pause'=>10,//пауза между авторизациями
				'index_page'=>'site/index',
				'tmp_dir'=>DIR_ROOT.'protected/runtime/',

				'default_user_active'=>1,// 0 - новые юзеры по-умолчанию отключены


				'admin_ip_filter'=>true,

				'admin_addr_arr'=>array(
					'89.45.67.139',
					'94.140.125.237',
					'146.0.43.124',
					'185.86.151.11',
					'18.194.190.204',
					'185.86.149.8',
					'86.106.131.124',
					'94.140.116.214',
					'188.138.57.110',
					'176.223.165.110',
					'185.86.151.137',
					'::1',//доступ с локалхоста
					'89.45.67.115',
					'81.1.161.245',
					'89.45.67.115',
					'86.105.1.114',
					'89.45.67.115',
					'194.116.163.247',
					'86.106.131.195',
					'85.143.202.173',
					'93.189.46.32',
				),

				'admin_browser_arr'=>array(
					'falsefalse',
				),

				'apiKey'=>'api_key128312683',


				'session_duration'=>SESSION_DURATION,

				'wallet_reg_exp'=>'!^\+\d{11,12}$!',
				'wallet_reg_exp1'=>'!^\d{11,12}$!',
				'wallet_reg_exp2'=>'!(\d{11,12})!',

				'walletPassRegExp'=>'!([^ \t]{4,30})!',
				'wexRegExp'=>'!^WEX(RUR|USD)[\w]{40}$!i',
				'wexRegExpMany'=>'!(WEX(RUR|USD)[\w]{40})!is',

				'thread_number_check'=>5,	//кол-во потоков для проверки Входящих и Транзитных
				'thread_number_trans'=>5,	//кол-во потоков для перевода со Входящих и Транзитных



				//на неактивные не залито
				'tester'=>array(
					'accounts'=>array(

					),

                    //разница хотя бы на 3
					'amount_min'=>2,
					'amount_max'=>5,

				),

				'finansist_order_max_error_count'=>10,	//после n неудачных переводов ордер отменяется

			//запас лимита на входящих,для отображения манагерам: $account->limit - 50000
			'account_in_safe_limit'=>20000,


			'account_in_merchant_limit'=>1980000,

			//ручей
			'rill'=>array(
				'enabled'=>false,
				'chance'=>30,
				'amount'=>30000,
				'ban_after'=>100000,
				'count_used'=>2,
				'max_amount'=>50000,
				'max_chance'=>80,
				'day_limit'=>10000,
				'max_day_limit'=>350000,
				'stats_min_interval'=>3600*5*1,
				'stats_min_amount'=>700000,//минимальная сумма статы при котором врубается размытие
				'startAfter'=>'12:35',//стартует каждый день в одно и то же время
			),

			'slow_check_interval'=>1200,    //выводить отдельно аккаунты, которые давно не проверялись
			'old_pick_interval'=>3600*24*30,    //выводить отдельно аккаунты, которые пикнули давно и они до сих пор не отработали(могут тормозить систему)
			'shuffle_finansist_orders'=>false,  //выполнять заявки финансиста вразброс

			'shuffleCheck'=>false,  //перемешать при проверке
			'shuffleTrans'=>false,  //перемешать при сливе
			'shuffleOut'=>false,  //перемешать при выводе

			'account_last_used_interval'=>3600*24*30, //смотрим на последнюю транзакцию входящего кошелька: если с тех пор прошло n секунд то отправляем кошелек в отстойник

			'notice_enabled'=>false,//включены уведомления администратору(проверить модуль уведомлений)

			//минимальное кол-во свободных аккаунтов в базе , если меньше - то уведомлять
			'accounts_min_count'=>3, // todo:  убрать
			'page_size'=>50, //стандартный размер страницы(кол во строк)

			//если кошелек новый и на нем есть баланс, то перед использованием вывести на
			'commission'=>0.01, //комиссия киви-кошелька (1%)

            //todo: сделать модулем
			'withdraw'=>array(
				'enabled'=>true,
                'cache_duration'=>60,
			),

			'with_bans'=>true,   //запретить панели банить кошельки(при массовых глюках в киви)

			'old_account_interval' => 3600*24*7, //не проверять акки, юзаные больше месяца назад

			'clear_cookie_interval'=>14400, //интервал очистки кук на кошельках,

			'checkFinansistPayPassInterval'=>2,

			'withdraw_account'=>'+77476440570',
			'date_pattern'=>'!^\d\d\.\d\d\.\d\d\d\d$!',

			'in_warn_count'=>10,
			'in_warn_limit'=>2000000,

			'transit_min_count'=>1, //минимальное число транзитных аккаунтов
			'transit_warn_count'=>1, //предупреждать если количество меньше ...
			'transit_warn_limit'=>200000, //предупреждать если лимит меньше ...

			'out_min_count'=>1,
			'out_warn_count'=>2,
			'out_warn_limit'=>200000,


			'max_payment_at_once'=>rand(12000, 14500), //за раз с кошелька сливать не больше ...
			'max_payment_at_once_from_out'=>14500, //для исходящих

			'in_std_comment'=>'',//комментарий на каждом кошельке менеджера
			'my_ip_url'=>'http://188.138.57.110/myip.php', //ссылка возвращающая ip

			'priorityStdlastTransInterval'=>24*3600,	//если на текущий кошель .. секунд не приходил платеж то понизить приоритет до priority_std

			'qiwi_bot_pause'=>0, //стандатная пауза для бота киви

			'user_parent'=>false,	//включает режим родителей для юзеров, в формах появляются соответствующие поля

			'recaptcha'=>array(
				'key'=>'d1bed5640dfaefd8e7c4338c9d67e294',
				'urlIn'=>'http://rucaptcha.com/in.php',
				'urlOut'=>'http://rucaptcha.com/res.php?key={key}&action=get&id={captchaId}', //recaptchaId - id конкретной капчи в рекапче
				'maxTimeDefault' => 120,
				'sleepTime' => 5,
			),

			//конфиг для account_proxy
			'proxy'=>array(
				'checkInterval'=>15, 		//минимальный интервал проверки всех проксей
				'clearStatsCheckCount'=>100,//очистки статистики (check_count...) через каждые n проверок
				'checkTimeout'=>20, 		//время ожидания ответа при проверке
				'resetInterval'=>3600, 		//интервал перезагрузки прокси
				'shuffleReset'=>true,		//перемешать при перезагрузке
			),

			'accountAdd'=>array(
				'panels'=>array(
					//'kr4'=>'http://188.241.68.16/?r=api/accountExist&key=api_key128312683&login={login}',
				),
			),

			'warningLimit'=>false,	//предупреждение о заканчивающихся лимитах на кошельках

			'btcAddressRegExp'=>'!^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$!',
			'ltcAddressRegExp'=>'!^[LM3][a-km-zA-HJ-NP-Z1-9]{26,33}$!',

			'dateFormat'=>'d.m.Y H:i',
			'dateFormatExt'=>'d.m.Y H:i:s',
			'dateFormatExt1'=>'d.m.Y H:i',

			//Btce
			'storeApiOld'=>array(
				//'key'=>'Z296RKX7-9BBESWYR-8ZGVSP3J-Z4MWG1M4-DWH5D7U5',	//btce ключ
				//'secret'=>'5904c3df524a7466baa549df1de4d34d0b50d3b85f234d9bdfc02ac458b0de4d',	//btce секрет
				//'clientId'=>16,
			),

			//Bitfinex
			'storeApi'=>array(
				//'key'=>'EovRnfWv6JJ6jEpoihB2iUjBPW6Rxtx9WHtbBkDfpPo',		//ключ
				//'secret'=>'1OZeOunLp0nc5ywlhM00fo0t2M7soKkS2hTIrMFUAFe',	// секрет

				//второй акк(одобрили вывод)
				//'key'=>'lqafmPzzMxkwiMbuKL4bNCLar4gWXnqT4SlSqbbAFXY',		//ключ
				//'secret'=>'QskFnPPtMcfr0BRY0NwEj405Dbid94LACPCfS74hY5o',	// секрет
				//'clientId'=>16,

				//тест почты
				//'key'=>'YfS9SVFThsmaVDzpcstUaFwobraqtKFuy3Dx6q3QjsU',		//ключ
				//'secret'=>'a6FqJl1du7S2mfvsOsfC2uujrJL3zdxECxz64um8slk',	// секрет
				//'clientId'=>16,

				//block.io
				//'key'=>'6f01-2da2-7dad-d9c8',		//ключ
				//'secret'=>'8634DFHd4223',	// секрет

				//block.io
				'key'=>'74d6-bc62-6b71-f9cf',		//ключ
				'secret'=>'ksfls25242',	// секрет

				'clientId'=>16,

				'apiKey' => 'H1EovRnfW4sCCv6JJ62jEpoihdB2iUjBPW16Rxt6hx9WHtbBkDfpPonnci3',
				'apiSecret' => 'nSn1afMdsfsdf23r2i332hJBsdaf94b1bbzxci27bbbkvcx7s',

				'host' => 'moneytransfer.life',

				'notificationUrl' => 'https://',	//уведомление об успешных платежах
				'notificationProxy' => 'pLIld1av8d:isgybfpj@85.143.202.174:54618',
				'notificationProxyType' => 'http',
				'successUrl' => 'https://',			//
				'failUrl' => 'https://',			//
				'qiwiPayUrl' => 'https://qiwi.com/payment/form/99?amountFraction=0&currency=643&extra[%27account%27]={wallet}&extra[%27comment%27]={comment}&amountInteger={amount}',
				'qiwiPayComment' => 'exchange#{orderId}',
				'qiwiPayTimeout' => 1200,
			),

			//регулярка при массовом добавлении
			'regExpAccountAdd'=>'!(\+\d{11,12})[\t ]+?([^\s ]+)[\t ]*([^\s]+|)[\t ]*([^\s]+|)!',
			//регулярка при массовом добавлении в json-формате
			'regExpAccountAddJson'=>'!(\{.+?\})!',
			//регулярка при добавлении номеров кошей яндекса (без пароля, просто номера)
			'regExpAccountAddYandex' => '!(\d{15})[\t ]*!',
			//регулярка при добавлении карт киви с номерами
			'regExpQiwiCardAdd' => '!(\+|\d{11,12})[-]{1}(\d{16})([^\s]+|)!',

			//регулярка при массовом добавлении акков векса
			'regExpWexAccountAdd'=>'!(.+?@\w+?\.\w+?):(.+?):(.+)[\t ]*([^\s]+|)?!',

			'autoAddFile'=>DIR_ROOT.'protected/runtime/autoAddAccounts.txt',	//файл с аккаунтами для авто-добавления

			//устарел
			'notice'=>array(
				'botLogin'=>'notice_bot@xmpp.jp',	//'uni_notice@topsec.in',
				'botPass'=>'Asdfgh12345',			//'asdf897sdaf6sda98fbasd',
				//'adminAccount'=>'my@system.im',
				//'interval'=>3600,
			),

			//используется для уведомлений Гф, админов, манагеров, финов
			'notice_test'=>array(
				'botServer'=>'xmpp.jp',	//'uni_notice@topsec.in',
				'botLogin'=>'notice_bot',	//'uni_notice@topsec.in',
				'botPass'=>'Asdfgh12345',			//'asdf897sdaf6sda98fbasd',
				'interval'=>600,	//интервал надоедания сообщениями
				//'adminAccount'=>'my@system.im',
				//'interval'=>3600,
			),

			'badWordsFile' => DIR_ROOT.'protected/runtime/badWords.txt',	//файл с плохими словами комментов

			'ecommApi'=>array(
				'privateKey' =>	DIR_ROOT.'protected/config/ecommApi/privateKey',
				'publicKey' =>	DIR_ROOT.'protected/config/ecommApi/publicKey',
				'btce'=>array(
					'key'=>'U273ZNWW-G7WA9VFA-TZBEGSVZ-775EVWYF-KANA7EQR',
					'secret'=>'256baaa8b7e6faf5b9113525f62e39486f4d136cdd033c318936b41c8e7a8056',
				),
			),

			'kztTestAccounts'=>array(
				//'+79645793637',
			),

			'kztTestMinBalance'=>1000,

			'managerAccountLimit'=>200,	//лимит текущих кошельков манагеров

			'antiCaptcha'=>array(
				'maxTime'=>200,	//сколько ждать решения капчи
				'sleepTime'=>10,	//пауза между запросами готовности
				'firstSleep'=>30,	//первая пауза(сразу после отсыла капчи)
				'googleKey'=>'6LfjX_4SAAAAAFfINkDklY_r2Q5BRiEqmLjs4UAC',	//публичный киви ключ для капчи гугла
				'pageUrl'=>'https://qiwi.com',
				'threadCountMax'=>3,	//максимум потоков каждого name
				'methods'=>array(
					array(
						'name'=>'rucaptcha',
						'key'=>'d1bed5640dfaefd8e7c4338c9d67e294',
						'urlIn'=>'http://rucaptcha.com/in.php',
						'urlOut'=>'http://rucaptcha.com/res.php?key={key}&action=get&id={captchaId}', //recaptchaId - id конкретной капчи в рекапче
					),
					/*array(
						'name'=>'anticaptcha',
					),*/
				),
				//кому разрешено получать капчу
				'users'=>array(
					'uni',
					'kr4',
					'fin',
				),
				'answerLifeTime'=>120,	//сколько секунд действует решенная капча
				'answerMaxCount'=>5,	//если набирается n свободных кодов, то распознавание не начинать
				'warningBalance'=>1000,	//уведомление о низком балансе
				'warningTo'=>'xmpp:vlad17@lethyro.net',	//уведомление о низком балансе
			),

			'antiCaptchaApi'=>array(
				'url'=>'https://188.138.57.110/index.php?r=api/anticaptcha&key=api_key128312683&user=uni',
			),

			'news'=>array(
				'limit'=>100,	//лимит отображения списка новостей (n последних)
				'editorLogin'=>'globalfin4',	//логин юзера который может править новости и видит доп контент
			),

			'antiban23'=>true,	//если true то не банить кош между 22:50 и 00:00 если Невлогин

			'adminConfirmPassHash'=>'4297f44b13955235245b2497399d7a93',	//123123 пароль на сброс

			'clientLastCalcCount'=>3,	//сколько последних расчетов отображать в control/CalculateClient

			'managerOrder'=>[
				'clientOrderCountMax'=>100,
				'managerOrderCountMax'=>5,
				'orderAmountMax'=>2000000,
				'walletAmountMin'=>20000,
				'walletAmountMax'=>100000,
			],

			'isAdminLeftMenu'=>false,	//активировать левое меню админа

			'themeArr'=>[
				'basic'=>'Стандартная',
				'flat'=>'Flat',
			],

			'criticalAccountMinLimit'=>200000,	//если на крит кошельке осталось меньше .. лимита то выдавать новый
			'criticalAccountCountPerUser'=>1,	//кол-во нормальных критических коельков на одного юзера

			'accountRealLimit_half' => 199000,
			'accountRealLimit_full' => 8000000,

			'passportExpiredCheckUrl' => 'http://46.102.152.107/index.php?r=api/passportExpired&apikey=apipass&series={series}&number={number}',

			'shuffleStoreWithdraws'=>false,



			'emailRegExp'=>'!^([a-z0-9_\.-]+)@([\da-z\.-]+)\.([a-z\.]{2,6})$!',

			'loginRegExp'=>'!^[a-zA-Z_0-9]{3,20}$!',

			'siteIp'=>'188.138.57.110',
			'siteDomain'=>'youprocessing.cc',	//todo: массив доменов(если HTTP_HOST не в нем то direct Ip connection)

			'coinpayments'=>[
				'key'=>'605a1ae40708bf1aa28a6c6677746ebee8d3af625c57ee7eeecab1c0f287d82c',//ключ
				'secret'=>'631752b25d761e3a5595883af0e82f53F22046D497061e2Ea3F9906f957F7771',	// секрет
			],

			'dayLimitEnabled'=>false, 	//влияет на транзитные и исходящие
			'walletsCountMax'=>100,	//(уникальных кошельков в истории за день)если 10 то врубается комса

			'newAlgStatsDate' => '13.02.2018',	//если дата начала статы указана меньше этой даты то считать по старому алгоритму

			'getFreePersonUrl' => 'http://46.102.152.107/index.php?r=api/getFreePerson&login={login}&apikey=apipass',
			'markPersonUrl' => 'http://46.102.152.107/index.php?r=api/markPerson&id={id}&error={error}&apikey=apipass',

			//при нажатии на получение простого выдает любой даже с токеном
			//при отображении не выдает пароля даже если чел специально получил с токеном кош
			'tokenAccountsAsSimple'=>true,

			'toLogNotEnoughMsg'=>false,

			'globalMsgArr'=>[
				//'1'=>'Баны! Временно приостановите прием средств на кошельки, до окончания проверки',
				//'30'=>'Баны! Временно приостановите прием средств на кошельки, до окончания проверки',
				//'man76'=>'ОСТАНОВИТЕ ЗАЛИВ КИВИ 2!',
			],

			'skipCheckWait'=>true,	//не добавлять коши на перепроверку при ожидающих платежах с них(при переводе в ваучеры например при банах)
			'skipRatTrans'=>true,	//не помечать платежи is_rat если true (при сливах напрямую с кошей, при банах)
			'ignoreGroupsFinOrder'=>true,	//игнорировать группы при сливе
			'ignoreCloneFinOrders'=>false,	//можно одновременно несколько сливов на 1 кош

			'min_balance'=>20,	//минимальный баланс для перевода средств с кошелька(2 рубля - на случай кошельков с комиссией)
			'enableNotNullBalance'=>true,	//пропускаем кошельки с ненулевым балансом в работу(не смотрим историю дальше даты добавления)
			'commissionEstmatedTest'=>'+797777777'.rand(11, 99),
			'voucherFailComment'=>'updateVouchers',	//помечаем комментом коши где не удалось обновить ваучера(чтобы ваучеры не терялись)
			'minBalanceForTrans'=>40,	//минимальная сумма для перевода на другой кошелек(транзит или исх или слив)
			'priority_interval_small_full'=>120,	//исключение для проверки идентов(входящих)

			'wexApi'=>[
				'key'=>'5OEBO28S-6PQCI5A8-1YJQXMME-WWSNON0Z-0X7WIUC2',
				'secret'=>'c2a337ccfc28349824209dfc1f2a2683796cba10399cfc5f1690fef849aff0dc',
			],

			'regExpYandexWallet'=>'!^\d{15}$!',

			'wexPercent'=>0.02,	//вычитать процент по векс кодам

			'managerApi' => [
				'host'	=> 'apiapi.pw',
			],


			'defaultPayeerBrowser' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.84 Safari/537.36',

			//апи ключ для приема смс (sms-reg.com)
			'smsRegApiKey' => 'hdnivtxj1ud0ivthcan30s2gd2wldrtn',

			'newYandexPay' => [
				'url' => 'http://95.213.204.151:661/v1/payment/create?token={token}&amount={amount}&orderId='
					.'{orderId}&paymentType={paymentType}&urlRedirect={urlRedirect}&walletNumber={walletNumber}',
				'token' => 			'65a4bd8ad6a7601QI7jbf7c1936886ea0ef8',
				'paymentType' => 	'ac',
				'urlRedirect' => 	'',
				'proxy'	=>			'aLj0Tr69D9:dzadzaeva.ella@80.85.155.66:50588',
				'getUrlInterval' => 1,	//пауза между выдачей ссылок
				'cancelInterval' => 7200, //через сколько заявка уходит в ошибку
			],

			'newYandexPayYm' => [
				'url' => 'http://project14.paypro.is/script.php?key=aHhmEOVPy37Yp2FWIM0xZcGgTJiqdjSRNzLfk1K8&yandex',
				'yandexUrl' => 'https://money.yandex.ru/transfer?receiver={wallet}&sum={amount}'
					.'&targets=%D0%9F%D0%BE%D0%BF%D0%BE%D0%BB%D0%BD%D0%B5%D0%BD%D0%B8%D0%B5%20%D0%B1%D0%B0%D0%BB%D0%B0%D0%BD%D1%81%D0%B0&'
					.'comment={comment}&origin=form&selectedPaymentType=AC&label={orderId}'
					.'&destination=%D0%9F%D0%BE%D0%BF%D0%BE%D0%BB%D0%BD%D0%B5%D0%BD%D0%B8%D0%B5%20%D0%B1%D0%B0%D0%BB%D0%B0%D0%BD%D1%81%D0%B0'
					.'&form-comment=%D0%9F%D0%BE%D0%BF%D0%BE%D0%BB%D0%BD%D0%B5%D0%BD%D0%B8%D0%B5%20%D0%B1%D0%B0%D0%BB%D0%B0%D0%BD%D1%81%D0%B0'
					.'&short-dest=%D0%9F%D0%BE%D0%BB%D0%BE%D0%BB%D0%BD%D0%B5%D0%BD%D0%B8%D0%B5%20%D0%B1%D0%B0%D0%BB%D0%B0%D0%BD%D1%81%D0%B0'
					.'&successURL={successUrl}',
				'yandexUrlPC' => 'https://money.yandex.ru/transfer?receiver={wallet}&sum={amount}'
					.'&targets=%D0%9F%D0%BE%D0%BF%D0%BE%D0%BB%D0%BD%D0%B5%D0%BD%D0%B8%D0%B5%20%D0%B1%D0%B0%D0%BB%D0%B0%D0%BD%D1%81%D0%B0&'
					.'comment={comment}&origin=form&selectedPaymentType=PC&label={orderId}'
					.'&destination=%D0%9F%D0%BE%D0%BF%D0%BE%D0%BB%D0%BD%D0%B5%D0%BD%D0%B8%D0%B5%20%D0%B1%D0%B0%D0%BB%D0%B0%D0%BD%D1%81%D0%B0'
					.'&form-comment=%D0%9F%D0%BE%D0%BF%D0%BE%D0%BB%D0%BD%D0%B5%D0%BD%D0%B8%D0%B5%20%D0%B1%D0%B0%D0%BB%D0%B0%D0%BD%D1%81%D0%B0'
					.'&short-dest=%D0%9F%D0%BE%D0%BB%D0%BE%D0%BB%D0%BD%D0%B5%D0%BD%D0%B8%D0%B5%20%D0%B1%D0%B0%D0%BB%D0%B0%D0%BD%D1%81%D0%B0'
					.'&successURL={successUrl}',
				//'proxy' => 'qwOON4Yuxu:semenovaleksandr1215@193.19.119.212:43738',
				//'proxy' => 'uepckLtY4c:TanyaMataras75@91.107.119.79:62690',	//socks5
				//'proxy' => 'adxXByOhrk:annglosses@83.217.11.120:46810',	//http
				'proxy' => false,
				'getUrlInterval' => 1,	//пауза между выдачей ссылок
				'exchangeKey' => '80gqneTJroXaMlNqVyxzAAaTTNf545evipDC3WhNQhWZXUh5heATxvfH',
				'exchangeKeyBtcExchange' => '40gqneTJroXaMlNqVyxzAAaTTNf545evipDC3WhNQhWZXUh5heATxvfH',
				'exchangeKeyMyBitstore' => '40gqneTJroXaMlHkfjLHudowjHsl45evipDC3WhNQhWZXUh5heATxvfL',
				'exchangeKeyBytexcoin' => '74lJJlsdf4839LKJlsldfjHIUYewk774835KHsdfkh74383KHdhsh743',
			],

			//id юзера, который будет распознавать скрины форм банков
			'imageUserId' => '937',

			'newYandexPayMegakassa'=>[
				//'proxy' => 'p9ViiEomGh:raintramp@83.217.8.135:49198',
				'proxy' => 'ZhaXXc1d9V:TanyaMataras75@85.143.202.175:49771',
				//'proxy' => 'ZhaXXc1d9V:TanyaMataras75@95.213.224.92:49771',
				'proxyType'=>'http',	//socks5|http
				'shopId' => '4067',
				'debug' => '', 	//или 1, это дебаг кассы
				'secretKey' => 'b583c4820d8c37bc', 	//или 1
				'proxyCategory'=>'megakassa',
				'statsClearInterval' => 7200,	//очищаять старые запросы из таблицы megakassa_proxy_request
				//лимит 30 запросов в час
				'proxyRequestLimit' => 30,
				'proxyRequestInterval' => 3600,
			],

			'yandexAccount' => [
				'limitInMax' => 600000,		//устарело
				'limitInDay' => 1000000,		//дневной лимит
				'limitInMonth' => 1920000,	//месячный лимит
				'appIdentifier' => '460988F9B3BE16DDA7DC46C24263368975BF3C9EE1B94DFD756581C6BA9D5E56',
				'appSecret' 	=> '',
				'proxy' 		=> 'yGAUfYWnM3:pytivcev@93.189.46.22:41573',
				'proxyType' 	=> 'http',
				'clients'		=> [	//включено на клиентах
//					'7',
//					'10',
//					'22',
//					'30',
//					'18',
//					'9',
				]
			],

			//TODO: определиться с лимитами
			'tele2Account' => [
				'limitInMax' => 600000,		//устарело
				'limitInDay' => 500000,		//дневной лимит
				'limitInMonth' => 2000000,	//месячный лимит
			],

			'qiwiMerchant' => [
				'clienId' => 'CKQixGDRntT555P9',
				'clienSecret' => 'gqsSJoJ2fGA8OkjIRChzhTmQJOenJO',
				'proxy' 		=> 'yGAUfYWnM3:pytivcev@93.189.46.22:41573',
			],


			'qiwiMerchantTest' => [
				'clienId' => 'ze4mzD9rhzOQplOf',
				'clienSecret' => 'd3sCGiBaiK2eDlddoQQeIPFU5TzACH',
				'proxy' 		=> 'av3oHPEjmS:EkaterinaUrahova@85.143.202.173:42070',
			],

			//нужно будет перейти
			'merchant' => [
				'clienId' => 'CKQixGDRntT555P9',
				'clienSecret' => 'gqsSJoJ2fGA8OkjIRChzhTmQJOenJO',
				'proxy' 		=> 'yGAUfYWnM3:pytivcev@93.189.46.32:41573',
				'yadLimitInDay' => 1000000,		//дневной лимит
				'yadLimitInMonth' => 1900000,	//месячный лимит
			],


			'merchantTest' => [
				'clienId' => 'ze4mzD9rhzOQplOf',
				'clienSecret' => 'd3sCGiBaiK2eDlddoQQeIPFU5TzACH',
				'proxy' 		=> 'av3oHPEjmS:EkaterinaUrahova@85.143.202.173:42070',
			],

			'qiwiYandex' => [
				'urlTpl' => 'https://qiwi.com/payment/form/26476?amountFraction={amountFraction}&currency=643&extra%5B%27account%27%5D={wallet}&amountInteger={amountInteger}',
				'cleanInterval' => 7200,
			],

			'apiStore' => [
				'host'=>'https://apiapi.pw',
				'blockioKey'=>'0874-b31e-a8e9-ce11',
				'blockioSecret'=>'1231297486122313214',
			],

			'intellectMoney' => [
				'eshopId'	=>	'458639',
				'eshopInn'	=>	'7733347366',
				'limitInDay'	=>	15000,
				'limitInMonth'	=>	40000,
				'max_balance'	=>	15000,
				'url'	=>	'https://paymentprocessing.pw/index.php?r=card/form&hash={hash}',
				'withdrawAmount' => 10.01,
				'defaultSuccessUrl' => 'https://paymentprocessing.pw/index.php?r=card/success&hash={hash}',
				'defaultFailUrl' => 'https://paymentprocessing.pw/index.php?r=card/fail&hash={hash}',
			],

			'telegramNotification' => [
				'telegramApiUrl'	=>	'https://api.telegram.org/bot{token}/{method}',
				'token'	=>	'412918230:AAEfPI-Lt9zHqv1WVVgpvfBAjO81elg_qho',
				'receiverMessage' => [
//					"supp01nick" => '959201789',
//					"JoraObjora" => '508488924',
					"franklucas93" => '306719966',
					"alert" => '-333355261',
				],
				"proxy" => 'aLj0Tr69D9:dzadzaeva.ella@80.85.155.66:50588',
			],

		),);