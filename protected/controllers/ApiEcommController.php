<?php

/**
 * апи для Ecomm
 */
class ApiEcommController extends CController
{
	private $config;

	public function beforeAction($action, $params)
	{
		session_destroy();
		return parent::beforeAction($action, $params);
	}

	public function actionIndex()
	{
		$this->config = cfg('ecommApi');

		$postRaw = file_get_contents('php://input');

		//декодировать в массив
		if($paramsArr = EcommApi::decrypt($postRaw, $this->config['privateKey']))
		{
			//первоначальная проверка запроса
			EcommApi::checkRequest($paramsArr);
		}

		$result = array();

		$response = '';

		if(!EcommApi::$lastErrorCode)
		{
			//отдать закешированный запрос если уже был
			$savedRequest = EcommApi::findRequest($paramsArr);

			if(!$savedRequest)
			{
				$method = $paramsArr['method'];

				$result = EcommApi::$method($paramsArr);
			}
			else
				$response = $savedRequest;
		}


		if(!$response)
		{
			$resultArr = array(
				'code'=>EcommApi::$lastErrorCode,
				'message'=>EcommApi::$lastError,
				'result'=>$result,
			);

			if(YII_DEBUG)
				$response = Tools::arr2Str($resultArr);
			else
				$response = json_encode($resultArr);

			//сохранить запрос (если не тест)
			if(!YII_DEBUG)
				EcommApi::saveRequest($postRaw, $paramsArr, $response, EcommApi::$lastErrorCode);
		}

		if(YII_DEBUG)
		{
			$content = $response;

			if(isset($savedRequest) and $savedRequest)
				$content = 'cached: '.$content;
		}
		else
			$content = EcommApi::encrypt($response, $this->config['publicKey']);


		echo $content;

		Yii::app()->end();
	}



}