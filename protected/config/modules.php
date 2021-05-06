<?php

return [
	'payeer'=>[],
	'pay'=>[],
	'yandexAccount'=>[
		//'class' => 'application.modules.yandexAccount.YandexAccountModule',
		//'limitInMax' => 600000,
	],
	'tele2'=>[],
	'sim'=>[
		'config'=>[
			'cryptEnabled' => false,
			'cryptPass' => 'Bdsfkiweeury23842369423Gsbsfgsdf181jhdsjfsdfdsUJfdsjfgsd1',	//пароль для шифрования важных данных
			'formDomainApiKey' => 'ahsldfF213hlsdfHdsfk13',	//ключ для апи с домена формы с данными карты
			'payUrlTpl' => 'https://paymentprocessing.pw/index.php?r=payment/form&orderId={orderId}',
			'successUrlTpl' => 'https://paymentprocessing.pw/index.php?r=payment/success&orderId={orderId}&hash={hash}',
			'failUrlTpl' => 'https://paymentprocessing.pw/index.php?r=payment/fail&orderId={orderId}',
			'checkUrlTpl' => 'https://paymentprocessing.pw/index.php?r=payment/check&orderId={orderId}',	//урл для проверки статуса (мтс)
			'selenoidApiUrl' => 'http://199.192.28.172/selenoid/',
			'selenoidApiKey' => '3123123123123fafdasfasdf',
			'selenoidHubUrl' => 'http://199.192.28.172/4444/wd/hub',
			'mtsApiUrlGet' => 'https://199.192.28.172/quickstart/public/api',
			'mtsApiUrlCheck' => 'https://199.192.28.172/quickstart/public/api/check',
			'captchaKey' => '9fa236677da7aef40ec2933d62305fe2',
		]
	],
	'intellectMoney'=>[
		'config'=>[
			'successUrlTpl' => 'https://paymentprocessing.pw/index.php?r=payment/success&orderId={orderId}&hash={hash}',
			'failUrlTpl' => 'https://paymentprocessing.pw/index.php?r=payment/fail&orderId={orderId}',
			'checkUrlTpl' => 'https://paymentprocessing.pw/index.php?r=payment/check&orderId={orderId}',
		]
	],
	'card'=>[
		'config'=>[
			'cryptEnabled' => false,
			'cryptPass' => 'Bdsfkiweeury23842369423Gsbsfgsdf181jhdsjfsdfdsUJfdsjfgsd1',	//пароль для шифрования важных данных
			'formDomainApiKey' => 'ahsldfF213hlsdfHdsfk13',	//ключ для апи с домена формы с данными карты
			'payUrlTpl' => 'https://paymentprocessing.pw/index.php?r=payment/form&orderId={orderId}',
			'successUrlTpl' => 'https://paymentprocessing.pw/index.php?r=payment/success&orderId={orderId}&hash={hash}',
			'failUrlTpl' => 'https://paymentprocessing.pw/index.php?r=payment/fail&orderId={orderId}',
			'checkUrlTpl' => 'https://paymentprocessing.pw/index.php?r=payment/check&orderId={orderId}',	//урл для проверки статуса (мтс)
			'selenoidApiUrl' => 'http://199.192.28.172/selenoid/',
			'selenoidApiKey' => '3123123123123fafdasfasdf',
			'selenoidHubUrl' => 'http://199.192.28.172/4444/wd/hub',
			'mtsApiUrlGet' => 'https://199.192.28.172/quickstart/public/api',
			'mtsApiUrlCheck' => 'https://199.192.28.172/quickstart/public/api/check',
		]
	],
	'merchant'=>[],
	'walletS'=>[
		'config' =>[
			'testParams'=>[
				'baseUrl' => 'https://walletesvoe.com/api/v1',
				'merchant_key'=>'55ekZu2wkvq4HQN',
				'merchant_sign'=>'TciU8Jt71h4nTvMQSd8ZOTx6Sla9ahxUkOkgZB1ujS2Oj',
				"currency" => 'EUR',
				"currentRateEur" => 70.48,
				"lang"=> "RU",
				"success_method" => "GET",
				"cancel_method" => "GET",
				"callback_url" => "https://apiapi.pw/index.php?r=api/WalletSCollback&key=api_key128312683",
				"callback_method" => "POST",
				'proxy' => 'ivxynHJuQ2:pillars000@31.41.44.134:42370',
				'proxyType' => 'http',
			],
			'devParams'=>[
				//TODO: заполнить когда выдадут рабочие ключи
				'baseUrl' => 'https://walletesvoe.com/api/v1',
				'merchant_key'=>'55ekZu2wkvq4HQN',
				'merchant_sign'=>'TciU8Jt71h4nTvMQSd8ZOTx6Sla9ahxUkOkgZB1ujS2Oj',
				"currency" => 'EUR',
				"currentRateEur" => 70.48,
				"lang"=> "RU",
				"success_method" => "GET",
				"cancel_method" => "GET",
				"callback_url" => "https://apiapi.pw/index.php?r=api/WalletSCollback&key=api_key128312683",
				"callback_method" => "POST",
				'proxy' => 'ivxynHJuQ2:pillars000@31.41.44.134:42370',
				'proxyType' => 'http',
			],
		]
	],
	'testCard'=>[
		'config'=>[

		],
	],
	'p2pService'=>[
		'config'=>[
			'proxy' => 'yGAUfYWnM3:pytivcev@93.189.46.22:41573',
			'token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImQ0M2Y1MTcxOTdhMjkzMDJlNWRkNzNjMzFiNGVkNGZlNTJiMTYzZDQ2YTA3NmI3ZjMzMzE0NjVkODVkZWQxYzAwMmI4ODNjNGUyYWQxZDhjIn0.eyJhdWQiOiIxIiwianRpIjoiZDQzZjUxNzE5N2EyOTMwMmU1ZGQ3M2MzMWI0ZWQ0ZmU1MmIxNjNkNDZhMDc2YjdmMzMzMTQ2NWQ4NWRlZDFjMDAyYjg4M2M0ZTJhZDFkOGMiLCJpYXQiOjE1ODUyMDk2ODMsIm5iZiI6MTU4NTIwOTY4MywiZXhwIjoxNjE2NzQ1NjgzLCJzdWIiOiIzMTE2Iiwic2NvcGVzIjpbXX0.uvMFXGh8bZRR36mcnN1hbYxMcHbZk_MYppCH7I-jmh8UPbUk5XYQDGzwA9GAMDGevHLksiIEuVyTWFz1H_56sfxGPpbiJK1blxrC3O9dUZz1F371aqbdJGH0COOgyyUXdmR92cjyNtfd9-m9D0KgVhXZXaZT-r04BBc4oMJrRu7H_QnkY0X4jFVRxV6wP-cu3B1WXXG3kA4L1zvDGDCShucF1VkLhOvxouK8I7CKck5jsuVcJN90urw9Z4XzkkiYmk5RMwRq6KLbucaWcZSXkwsIlGIFIc1epq4NB9m8szQ9SJVyPW4_qesmJ4ok-yozdlJJVtYQfqPm8FsNnzIWlgrseLTpir5aTTft7e24tTCFV7gbzW6txwXFcJrXiY2drtvgVbw5TxtHh4Ot6QiKNO8mLJA3ugXbby4m1xTsl7CSTFSybvwCwblhOZqcwlosHz6TSaAVJd_Rt87GeE3EDOMqTInxHiSeTAhmQgBB-XTm2vFh8WsSXWTt9uv-9Qjdxand11x-wSXcdJo8a7eTX8xIIn1jB9BQg8v8EmpAQAtopiOU4PpZMBsZlpVguzlGqZQcjXS1XkIdyJiek09msUUxnbWWwRwGwyXdSAl7fnlFFiNbgMgAaOlpo5uQuf5MT16IMuzxgB586a8HPkJYqWeSDBB9t63mKIHgXVQi38U',
		],
	],
];