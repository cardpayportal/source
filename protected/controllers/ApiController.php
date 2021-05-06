<?php

/**
 * апи для
 */
class ApiController extends ApiParentController
{
	const ERROR_WRONG_WALLET = 'wrong_wallet';

	const ERROR_CAPTCHA_NOT_ENOUGH = 'captchaNotEnough';
	const ERROR_NO_REQUEST_DATA = 'Not found Yandex requests';

	public $errorMsg;

	/*
	 * возвращает строку: truse|false
	 */
	public function actionAccountExist($login, $key=false)
	{
		$result = '';

		if(!preg_match('!^(\d{11,12})$!', $login))
			$this->errorCode = self::ERROR_WRONG_WALLET;

		if(!$this->errorCode)
		{
			$login = '+'.$login;

			if($account = Account::model()->find("`login`='$login'"))
				$result = 'true';
			else
				$result = 'false';
		}

		$this->resultOut($result);
	}

	public function actionAntiCaptcha($key, $user)
	{
		$result = AntiCaptcha::getAnswer($user);

		if(!$result)
		{
			$this->errorCode = self::ERROR_CAPTCHA_NOT_ENOUGH;
			$result = '';
		}

		$this->resultOut($result);
	}

	protected function getGoogleCaptchaKey()
	{
		return '6Lf_2Q0TAAAAABzDzxrOMAFty0K_OLFDhlu7P7in';
	}

	/**
	 * антикапча для простых и google капч
	 * @param $key
	 * @param $type
	 * @param string $imageUrl
	 */
	public function actionReCaptcha($type='', $params = [])
	{
		session_write_close();

		if(!$type && !$params['imageUrl'])
		{
			$type = $_POST['type'];
			$params['imageUrl'] = $_POST['imageUrl'];
		}

		$recaptchaCfg = cfg('recaptcha');

		$googleSiteKey = $this->getGoogleCaptchaKey();

		if($type == 'recaptcha')
		{
			$captchaId = Tools::anticaptcha('recaptcha', array(
				'step'=>'send',
				'googleApiKey'=>$googleSiteKey,
				'pageUrl'=>'https://payeer.com/ru/account/',
			));
		}
		elseif($type == 'image')
		{
			$captchaId = Tools::anticaptcha('image', array(
				'step'=>'send',
				'imageContent'=>$params['imageContent'],
			));
		}
		elseif($type == 'url')
		{
			$captchaId = Tools::anticaptcha('url', array(
				'step'=>'send',
				'imageUrl'=>$params['imageUrl'],
			));
		}

		$timeStart = time();


		if($captchaId)
		{
			sleep(25);

			$captchaCode = false;

			while(time() - $timeStart < $recaptchaCfg['maxTimeDefault'])
			{
				if($captchaCode = Tools::anticaptcha($type, array(
					'step'=>'get',
					'captchaId'=>$captchaId,
				)))
				{
					toLogRuntime(' капча распознана '.$captchaCode);
					break;
				}
				elseif(Tools::$error == Tools::ANTICAPTCHA_NOT_READY)
				{
					//sleep($recaptchaCfg['sleepTime']);
				}
				else
				{
					toLogError('ошибка распознавания капчи ');
				}

				sleep($recaptchaCfg['sleepTime']);
			}

			if(!$captchaCode)
			{
				toLogError(' ошибка распознавания капчи (затрачено '.(time() - $timeStart).' сек) '.Tools::$error);
			}

		}
		else
		{
			toLogError(' captchaId  не получен от сервиса антикапчи ('.Tools::$error.')');
		}

		$result = $captchaCode;

		if(!$result)
		{
			$this->errorCode = self::ERROR_CAPTCHA_NOT_ENOUGH;
			$result = '';
		}

		$this->resultOut($result);
	}

	/**
	 * подтверждаем платеж по апи
	 */
	public function actionConfirmYadPaymentWithApi()
	{
		session_write_close();

		toLog('Подтверждение с яда(test1): '.arr2str($_REQUEST));

		if($_POST)
		{
			toLog('Ответ яда: '.arr2str($_POST));
		}

		if($_POST['apiId'])
		{
			$apiId = $_POST['apiId'];
			if($model = YandexPay::getModel(['api_id'=>$apiId]))
			{
				if($model->status !== 'success')
				{
					$model->status = 'success';
					$model->date_pay = time();

					if($model->save())
					{
						$success = 'подтвержден платеж: '.$model->url.' apiId = '.$apiId;
						toLogRuntime($success);
						prrd($success);
					}
					else
					{
						$error = 'ошибка обновления заявки id='.$model->id.' apiId = '.$apiId.' url: '.$model->url;
						toLogError($error);
						prrd($error);
					}
				}
				else
				{
					$error = 'ошибка, заявка уже подтверждена id='.$model->id.' apiId = '.$apiId.' url: '.$model->url;
					toLogError($error);
					prrd($error);
				}

			}
			else
			{
				$error = 'заявка не найдена по apiId: '.$apiId.' url: '.$model->url;
				toLogError($error);
				prrd($error);
			}
		}
		prrd('test');
	}


	/**
	 * тест нового яндекса
	 * подтверждение от Яндекса
	 */
	public function actionNewConfirmYadPaymentWithApi()
	{
		toLog('Подтверждение с яда(test2): '.arr2str($_REQUEST));

		session_write_close();

		if($_POST)
		{
			$request = $_POST['request'];

			$requestArr = json_decode($request, true);

			//YandexRequest::add($requestArr);

			if($requestArr['unaccepted'] !== 'false' or $requestArr['currency'] !== '643')
			{
				return false;
			}

			$params = [
				'amount' => $requestArr['withdraw_amount'],
				'number' => ($requestArr['notification_type'] == 'card-incoming') ? 'card' : $requestArr['sender'],
				'paymentId' => $requestArr['operation_id'],
				'orderId' => $requestArr['label'],
				'notificationType' => $requestArr['notification_type'],
				'wallet' => $requestArr['wallet'],
				'amount1' => $requestArr['amount'],
			];

			if(NewYandexPay::confirmPayment($params))
				echo 'ok';
			else
				echo 'error';
		}
		else
			toLogError('не пост запрос');
	}

	/**
	 * тест нового яндекса
	 * подтверждения от Николаса
	 */
	public function actionConfirmNewYandexPayment()
	{
		session_write_close();
		//toLog('confirmNewYandexPayment from nikolas: '.arr2str($_REQUEST));

		if($_POST)
		{
			$requestArr = $_POST;

			if($requestArr['unaccepted'] !== 'false' or $requestArr['currency'] !== '643')
			{
//				toLogError('actionConfirmNewYandexPayment неверные параметры: '.arr2str($requestArr));
				return false;
			}

			$params = [
				'amount' => $requestArr['withdraw_amount'],
				'number' => ($requestArr['notification_type'] == 'card-incoming') ? 'card' : $requestArr['sender'],
				'paymentId' => $requestArr['operation_id'],
				'orderId' => $requestArr['label'],
				'notificationType' => $requestArr['notification_type'],
				'wallet' => $requestArr['wallet'],
				'amount1' => $requestArr['amount'],
			];

			if(NewYandexPay::confirmPayment($params))
				echo 'OK';
			else
				echo 'error: '.NewYandexPay::$lastError;
		}
		else
			toLogError('не пост запрос');

	}


	public function actionGetYandexPaymentRequest($key, $wallet)
	{
		session_write_close();
		toLog('передаем данные запросов яндекс ');

		$models = YandexRequest::getModels($wallet);

		$resultArr = [];

		foreach($models as $model)
		{
			$resultArr[] = $model->attributes;
		}

		if(!$resultArr)
		{
			$this->errorCode = self::ERROR_NO_REQUEST_DATA;
			$result = '';
		}

		$this->resultOut($resultArr);
	}


	public function actionWriteYandexPaymentRequest($key, $wallet)
	{
		session_write_close();

		if($_POST)
		{
			toLog('WriteYadRequest: '.arr2str($_POST));

			$request = $_POST['request'];

			$requestArr = json_decode($request, true);

			if($requestArr['unaccepted'] !== 'false' or $requestArr['currency'] !== '643')
			{
				return false;
			}

			return YandexRequest::add($requestArr);
		}

		//////
		$models = YandexRequest::getModels($wallet);

		$resultArr = [];

		foreach($models as $model)
		{
			$resultArr[] = $model->attributes;
		}

		if(!$resultArr)
		{
			$this->errorCode = self::ERROR_NO_REQUEST_DATA;
			$result = '';
		}

		$this->resultOut($resultArr);
	}

	/**
	 * тест нового яндекса
	 * подтверждение от Яндекса
	 */
	public function actionConfirmMegakassa()
	{
		session_write_close();

		if($_POST)
		{
			toLog('Подтверждение с Megakassa: '.arr2str($_POST));

			if($_POST['payment_method_id'] == '22')
			{
				if(TestQiwi::confirmPaymentMegakassa($_POST))
					echo 'ok';
				else
					echo 'error';
			}
			elseif($_POST['payment_method_id'] == '17')
			{
				//пересылаем уведомления по вмз от мегакассы на др сервак
				$sender = new Sender;
				$sender->useCookie = false;

				echo $sender->send('https://www.sel-game.ru/?route=payment/webmoney_megakassa_wmz/status',
					http_build_query($_POST));
			}
			else
			{
				if(NewYandexPay::confirmPaymentMegakassa($_POST))
					echo 'ok';
				else
					echo 'error';
			}

		}
	}

	protected function getErrorMsg()
	{
		$arr = array(
			self::ERROR_TEST_MODE => 'тех работы',
			self::ERROR_ACCESS => 'доступ запрещен',
		);

		if($this->errorCode)
			return $arr[$this->errorCode];
		elseif($this->errorMsg)
			return $this->errorMsg;
		else
			return '';
	}

	/**
	 * отдача информации о SimTransaction
	 * @param string $orderId
	 */
	public function actionGetCardPayOrder($orderId)
	{
		session_write_close();

		$result = [];

		if($model = SimTransaction::getModel(['order_id'=>$orderId]))
		{
			$result = [
				'id' => $model->order_id,
				'clientOrderId' => $model->client_order_id,
				'amount' => $model->amount,
				'phone' => $model->account->login,
				'status' => $model->status,
				'successUrl' => $model->success_url,
				'failUrl' => $model->fail_url,
			];
		}
		else
			$this->errorMsg = 'заявка не найдена';

		$this->resultOut($result);
	}

	/**
	 * отдача информации об IntellectMoney
	 * @param string $hash
	 */
	public function actionGetIntellectMoneyOrder($hash)
	{
		session_write_close();

		$result = [];

		if($model = IntellectTransaction::getModel(['hash'=>$hash]))
		{
			$result = [
				'orderId' => $model->order_id,
				'amount' => $model->amount,
				'status' => $model->status,
				'successUrl' => $model->success_url,
				'failUrl' => $model->fail_url,
				'email' => $model->account->email,
			];
		}
		else
			$this->errorMsg = 'заявка не найдена';

		$this->resultOut($result);
	}

	/**
	 * записываем в заявку orderId от стороннего апи, по нему будем чекать статус заявки
	 * @return array
	 */
	public function actionUpdatePayUrlExtended($orderId, $remoteOrderId)
	{
		$result = [];
//		$amount = $amount*1;

		if(!$orderId or !$remoteOrderId)
		{
			$this->errorMsg = 'не указан orderId';
			$this->resultOut($result);
		}

		if($order = NewYandexPay::getModel(['order_id'=>$orderId]))//, 'user_id'=>$userId
		{
			/**
			 * @var NewYandexPay $order
			 */
			$order->remote_order_id = $remoteOrderId;
			$order->save();

			$result = [
				'orderId' => $order->order_id,
				'remoteOrderId' => $order->remote_order_id,
			];

		}
		else
		{
			$this->errorMsg = 'orderId не найден';
			toLogError('updatePayUrlExtended: '.$this->errorMsg);
			$this->resultOut($result);
		}

		$this->resultOut($result);
	}

	public function actionGetOrderYadTele2($orderId)
	{
		$result = [];

		/**
		 * @var NewYandexPay $model
		 */
		if($model = NewYandexPay::getModel(['order_id'=>$orderId]))
		{
			$result = [
				'orderId' => $model->order_id,
				'amount' => $model->amount,
				'wallet' => $model->wallet,
				'unique_id' => $model->unique_id,
				'status' => $model->status,
				'urlYadtele2' => $model->url_yadtele2,
			];
		}
		else
			$this->errorMsg = 'заявка не найдена';

		$this->resultOut($result);
	}

	public function actionGetCardPayUrl()
	{
		$params = $_POST;

		if(!$result = SimTransaction::getBankUrl($params))
			$this->errorMsg = SimTransaction::$lastError;

		$this->resultOut($result);
	}

	/**
	 * клон метода GetCardPayUrl, пока тестовый
	 */
	public function actionGetIntellectMoneyPayUrl()
	{
		session_write_close();
		$params = $_POST;

		if(!$result = IntellectTransaction::getBankUrl($params))
			$this->errorMsg = IntellectTransaction::$lastError;

		$this->resultOut($result);
	}

	/**
	 * отмена SimTransaction при неудачном платеже
	 * @param string $orderId
	 */
	public function actionCancelCardPayOrder($orderId)
	{
		session_write_close();

		$result = '';

		if($model = SimTransaction::getModel(['order_id'=>$orderId]))
		{
			if($model->cancelOrder())
				$result = 'ok';
			else
				$this->errorMsg = $model::$lastError;
		}
		else
			$this->errorMsg = 'заявка не найдена';

		$this->resultOut($result);
	}

	/**
	 * отмена SimTransaction при неудачном платеже
	 * @param string $orderId
	 * @param string $hash
	 */
	public function actionConfirmCardPayOrder($orderId, $hash)
	{
		session_write_close();

		$result = '';

		if($model = SimTransaction::getModel(['order_id'=>$orderId, 'hash'=>$hash]))
		{
			if($model->confirmOrder())
				$result = 'ok';
			else
				$this->errorMsg = $model::$lastError;
		}
		else
			$this->errorMsg = 'заявка не найдена';

		$this->resultOut($result);
	}

	/**
	 * проверка платежа SimTransaction после редиректа с банка (МТС)
	 * @param string $orderId
	 * @return array
	 */
	public function actionCheckCardPayOrder($orderId)
	{
		session_write_close();

		$result = '';

		if($model = SimTransaction::getModel(['order_id'=>$orderId]))
		{
			$result = $model->checkOrder($_POST);
		}
		else
			$this->errorMsg = 'заявка не найдена';

		$this->resultOut($result);
	}

	/**
	 * @return bool
	 *
	 * ловим уведомление об оплате walletesvoe
	 */
	public function actionWalletSCollback()
	{
		session_write_close();
		if($_POST)
		{
			/**
			 * @var WalletSTransaction $model
			 */
			$params = $_POST;

			if($model=WalletSTransaction::model()->findByAttributes(['order_id'=>$params['order_nr']]))
			{
				if($params['order_nr'] and $params['status'] == 'ok')
				{
					/**
					 * @var WalletSTransaction $model
					 */
					$model->status = WalletSTransaction::STATUS_SUCCESS;
					$model->date_pay = strtotime($params['payment_date']) + 3600; // поправка часовых поясов
					$model->hash = $params['hash'];
					$model->pan = $params['pan'];
					return $model->save();
				}
				elseif($params['order_nr'] and ($params['status'] == 'time_out'
						or $params['status'] == 'error' or $params['status'] == 'cancel'))
				{
					$model->status = WalletSTransaction::STATUS_ERROR;
					if($params['status'] == 'time_out')
						$model->error = 'time_out';
					elseif($params['status'] == 'cancel')
						$model->error = 'cancel';
					else
						$model->error = $params['error_msg'];
					return $model->save();
				}
				elseif($params['order_nr'] and $params['status'] == 'processing')
				{
					$model->status = WalletSTransaction::STATUS_PROCESSING;
					return $model->save();
				}
				else
				{
					toLogError('WalletS error:'.arr2str($_REQUEST));
					return false;
				}
			}

		}
		toLogRuntime('WalletS :'.arr2str($_REQUEST));
		exit('Finish');
	}

	public function actionMegafonCollback()
	{
		session_write_close();
		toLogRuntime('MegafonCollback :'.arr2str($_REQUEST));
		die;
	}
}