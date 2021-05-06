<?php

class PayeerController extends Controller
{

	public $defaultAction = 'list';

	public function actionIndex()
	{

//		$api = new PayeerApi('P1003276723', '627250904', 'y0aZftFYWMtlaHCK', 'adm:FHldfjksfojf332@54.37.196.83:7778');
//
//		prrd($api->getExchangeRate(array('input' => 'N')));
//
//		$balanceArr = $api->getBalance();

//		var_dump($balanceArr);
//		die;

		//prrd($balanceArr['balance']['RUB']['BUDGET']);
//		foreach($transactionArr['history'] as $id=>$transaction)
//		{
//			$result[] = $api->getShopOrderInfo(['shopId'=>,]);
//		}

		//$result[] = $api->getShopOrderInfo(['shopId'=>1262, 'orderId'=>77212147]);

		//prrd($api->getShopOrderInfo(array()));
		//prrd($api->merchant(array('shopId'=>1262, 'orderId'=>77212147)));

		$smsApi = new SmsActivateApi('kgaLktMNgt:seven787@31.184.233.251:52637');


//		$model = new SmsActivate;
//		$model->scenario = 'add';
//		$model->phone = 79252778164;
//		$model->status = 'wait';
//		$model->payeer_account_id = 1;
//		$model->save();

		//prrd($smsApi->getNewNumber()); //Array ( [id] => 74281518 [phone] => 79253446483 )
		//prrd($smsApi->changeActivationStatus(1, 74303574));
		//prrd($smsApi->getQiwiActivationStatus(74303574));




		$bot = new PayeerBot('P1002760755', 'u7QmJ4Xy',
			'adm:FHldfjksfojf332@54.37.196.83:7778',
			'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.84 Safari/537.36'
			//,['withoutAuth'=>true]
		);

//		prrd($bot->sendQiwiMoneyRu(array(
//			'amount' => 100,
//			'receiver' => 'P1003327009',
//		)));
		//prrd($bot->createApiParams());

		//prrd($bot->getBalance());
//		prrd($bot->getTransactionStatus());



		$params = $bot->getPayParams(100.00, 79260215154);

		prrd($params);

		$params = [
			'mShop' => 1262,
			'mOrderid' => 78593534,
			'mAmount' => 100.00,
			'mCurr' => 'RUB',
			'mDesc' => 'QWRkIEZ1bmRzIHRvIFAxMDAyNzYwNzU1IFs4MTc5MjY4XQ==',
			'mSign' => '62AD7B7B3C083499A4DCF76065E2BD3042E8648EC703CE58B9F26F3757D8AE18',
			'email' => 'raintramp@tutanota.com',
			'ps' => 20916096,
			'orderId' => 20916096,
		];

		prrd($bot->getPayParams(100.00, 77475228628, 29185, $params));
//		prrd($bot->getHistory());
//

	}
}