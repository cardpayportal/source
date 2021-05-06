<?php
class TestCloneController extends CController
{
	public function beforeAction($action)
	{
		//if(!$this->isAdmin())
		//	$this->redirect(cfg('index_page'));

		return parent::beforeAction($action);
	}

	//тестим скорость мегакассы
	public function actionMegakassaThread()
	{
		session_write_close();

		$url = NewYandexPay::generateMegakassaUrl(rand(100, 1000), Tools::microtime());

		if($url)
			echo $url;
		else
			echo NewYandexPay::$lastError;

		$time = Tools::timeSpend();
		echo "<br> $time сек";
	}

	public function actionNewExchange()
	{
		$key = "4ufljfls43LLfjs8549SfhvxhsdJ743FJdhslasd8fhs942JDSAHlsh4";

		$postData = "yandex_link=".base64_encode('https://yandex.ru')."&success_link=".base64_encode('https://yandex.ru')."&fail_link="
			.base64_encode('https://yandex.ru')."&yad=1000"."&bit="."0.001"."&order_id=1001&btc=1EbvFmQoPMMbXj5RiappjzXhsCzTcR1Vur&percent=0";

		$hash = md5($postData.$key);


		$url = 'https://wmsell.biz/api.php?key='.$hash;

		$sender = new Sender;
		$sender->followLocation = false;

		prrd(json_decode($sender->send($url, $postData)));


	}


}
