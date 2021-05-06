<?php

/**
 * апи для магазина
 * сделать минимальную паузу между запросами
 * @property User user
 * todo: requestNumber сделать для каждого магазина своим (чтобы было 2 одинаковых но с разными storeId)
 * todo: сделать payments необязательным параметром
 * todo: очистку резервов коельков
 * @property StoreApi store

*/
class ApiStoreController_old extends CController
{
	private $errorCode = 0;
	private $errorMsg = '';

	const GET_WALLETS_MIN_AMOUNT = 1000;
	const GET_WALLETS_MAX_AMOUNT = 500000;

	
	const DEFAULT_CURRENCY = StoreApiTransaction::CURRENCY_RUB;

	const PAUSE = 5000;	//2 запроса в секунду

	private $store;	//модель StoreApi (ставится после checkAccess)

	private $debug = false;	//

	private $_method = '';

	public function beforeAction($action)
	{
		session_destroy();	//чтоб файлов в папке сессии не плодилось

		//ограничение по кол-ву запросов
		/*
		//session_write_close();

		$currentMicrotime = Tools::microtime();
		$lastRequestMicrotime = config('storeApiLastRequest');

		if($currentMicrotime - $lastRequestMicrotime < self::PAUSE)
			die('to: '.Tools::microtimeDate($lastRequestMicrotime));

		if(!Tools::threader('storeApi'))
			die('ar');

		config('storeApiLastRequest', $currentMicrotime);
		*/

		$this->_method = strtolower(Yii::app()->controller->action->id);

		$this->debug = ($_GET['debug']) ? true : false;
		$storeId = $_GET['storeId'] * 1;

		//каждый магазин в 1 поток
		if(!Tools::threader('storeApi'.$storeId))
			die('already run');

		if(YII_DEBUG and !Tools::isAdminIp())
		{
			$this->errorCode = StoreApi::ERROR_DEBUG;
			$this->resultOut();
		}

		$postRaw = file_get_contents('php://input');

		if(!$_POST['params'])
			$_POST['params'] = $postRaw;

		$paramsEncode = $_POST['params'];

		//toLog(Tools::arr2Str($_SERVER).Tools::arr2Str($postRaw));

		if($this->checkAccess($storeId, $paramsEncode))
		{
			//на повторы действий не выполняем
			if($this->store->requestModel)
			{
				if($this->debug)
				{
					echo 'cached: ';
					echo $this->store->requestModel->answer;
				}
				else
					echo StoreApi::crypt($this->store->requestModel->answer, $_GET['storeId']);

				Yii::app()->end();
			}

			//пропускаем на выполнение экшна
			return true;
		}
		else
			$this->resultOut();
	}

	protected function getErrorMsg()
	{
		$arr = array(
			StoreApi::ERROR_PARAMS => 'ошибка в params',
			StoreApi::ERROR_REQUEST_NUMBER => 'ошибка в номере запроса',
			StoreApi::ERROR_AMOUNT => 'ошибка в amount',
			StoreApi::ERROR_PAYMENTS => 'ошибка в payments',
			StoreApi::ERROR_BTC_ADDRESS => 'ошибка btcAddress',
			StoreApi::ERROR_DEBUG => 'тех работы',
			StoreApi::ERROR_ACCESS => 'ошибка доступа',
			StoreApi::ERROR_DECRYPT => 'ошибка расшифровки',
			StoreApi::ERROR_DATE => 'ошибка в дате',
			StoreApi::ERROR_NO_WALLETS => 'недостаточно кошельков',
			StoreApi::ERROR_STORE_ID => 'ошибка в storeId',
			StoreApi::ERROR_CURRENCY => 'ошибка в валюте',
			StoreApi::ERROR_WALLET_LIMIT => 'выдача кошельков ограничена',

		);

		if($this->errorMsg)
			return $this->errorMsg;

		if($this->errorCode and isset($arr[$this->errorCode]))
			return $arr[$this->errorCode];
		else
			return '';
	}

	/**
	 * устанавливает свойство $this->store
	 * @param int $storeId
	 * @param string $paramsEncode
	 * @return bool
	 */
	private function checkAccess($storeId, $paramsEncode)
	{
		$result = StoreApi::checkAccess($storeId, $paramsEncode);

		$this->store = StoreApi::$obj;

		if($result)
		{
			return true;
		}
		else
		{
			$this->errorMsg = StoreApi::$lastError;
			$this->errorCode = StoreApi::$lastErrorCode;
			return false;
		}

	}

	/**
	 * @param array|bool $response
	 * @return null
	 */
	private function resultOut($response = array())
	{
		//добавить запрос в StoreApiRequest

		$resultArr = array(
			'code'=>$this->errorCode,
			'result'=>$response,
			'message'=>$this->getErrorMsg(),
		);

		if($this->store)
		{
			if($this->_method != 'getrate')	//не засорять таблицу запросами курса
			{
				if(!$this->store->logRequest($resultArr))
					toLogStoreApi('ошибка логирования запроса '.Tools::arr2Str($resultArr));
			}
		}
		else
		{
			//логируем ошибочные запросы
			$postRaw = file_get_contents('php://input');

			if(!$_POST['params'])
				$_POST['params'] = $postRaw;

			$paramsEncode = $_POST['params'];

			//пропуск ошибок getRate
			if($this->_method != 'getrate')
				toLog('storeApi error: storeId='.$_GET['storeId'].' (method='.$this->_method.', code='.$this->errorCode.') post: '.$paramsEncode);
		}

		//если ошибка дешифровки
		$resultContent = json_encode($resultArr);

		if($this->errorCode !== StoreApi::ERROR_DECRYPT)
			$resultContent = StoreApi::crypt($resultContent);

		if($this->debug)
		{
			print_r($resultArr);
		}
		else
			echo $resultContent;

		//важно
		Yii::app()->end();

		/*
		$this->renderPartial('//system/json', array(
			'result'=>$resultArr,
		));
		*/
	}

	/**
	 * получение кошельков - сумм для залива суммы amount
	 * $status = 'half'|'full'
	 * $params = array(
	 * 	'amount'=>500000,
	 * 	'currency'=>'RUB|KZT',	необязательное, по-умолчанию RUB
	 * );
	 * выводит массив array('+799023203'=>20000, '+786352326323'=>500000);
	 */
	public function actionGetWallets()
	{
		$result = array();

		$params = $this->store->requestParams;

		if(!config('storeApiGetWalletsEnabled'))
		{
			$this->errorCode = StoreApi::ERROR_NO_WALLETS;
			$this->resultOut();
		}

//		if($this->store->store_id != 160)
//		{
//			$this->errorCode = StoreApi::ERROR_NO_WALLETS;
//			$this->resultOut();
//		}


		$amount = $params['amount'] * 1;

		if(isset($params['currency']))
			$currency = $params['currency'];
		else
			$currency = self::DEFAULT_CURRENCY;

		$currencyArr = StoreApiTransaction::currencyArr();

		if(!isset($currencyArr[$currency]))
		{
			$this->errorCode = StoreApi::ERROR_CURRENCY;
			$this->resultOut();
		}

		$minAmountStr = self::GET_WALLETS_MIN_AMOUNT;
		$maxAmountStr = self::GET_WALLETS_MAX_AMOUNT;

		//костыль чтобы не переделывать функцию резервирования(переводим все в рубли)
		if($currency == StoreApiTransaction::CURRENCY_KZT)
		{
			$amount = floorAmount($amount/6, 2);
			$minAmountStr = self::GET_WALLETS_MIN_AMOUNT*6;
			$maxAmountStr = self::GET_WALLETS_MAX_AMOUNT*6;
		}

		if(!$amount or $amount < self::GET_WALLETS_MIN_AMOUNT or $amount > self::GET_WALLETS_MAX_AMOUNT)
		{
			$this->errorCode = StoreApi::ERROR_AMOUNT;
			$this->errorMsg = 'ошибка в amount: должен быть от '.$minAmountStr.' до '.$maxAmountStr.' для валюты '.$currency;
			$this->resultOut();
		}

		if($accounts = $this->store->user->pickAccountsByAmount($amount))
		{
			foreach($accounts as $arr)
			{
				if($currency == StoreApiTransaction::CURRENCY_KZT)
					$result[$arr['account']->login] = round($arr['amount']*6);
				else
					$result[$arr['account']->login] = $arr['amount'];
			}

			//чтобы видно было что кошельки берут в кзт
			$mayBe = ($currency == StoreApiTransaction::CURRENCY_RUB) ? '' : '(эквивалент кзт)';

			toLogStoreApi('выдано '.count($accounts).' кошельков на сумму'.$mayBe.': '.$amount.' руб, user: '.$this->store->user->name);
		}
		else
		{
			if(User::$lastErrorCode == User::ERROR_NO_WALLETS)
				$this->errorCode = StoreApi::ERROR_NO_WALLETS;
			elseif(User::$lastErrorCode == User::ERROR_WALLET_LIMIT)
				$this->errorCode = StoreApi::ERROR_WALLET_LIMIT;
			else
				$this->errorCode = StoreApi::ERROR_NO_WALLETS;

			$this->errorCode = User::$lastErrorCode;
			$this->resultOut();
		}

		$this->resultOut($result);
	}

	/**
	 * сохранение успешных платежей
	 * todo: необязательный btc_address
	 * todo: отдавать статусы платежей
	 */
	public function actionSetPayments()
	{

		$params = $this->store->requestParams;

		$result = array();
		$payments = $params['payments'];
		$btcAddress = $params['btcAddress'];

		$paymentsForSave = array();

		if(!$payments and !$btcAddress)
		{
			$this->errorCode = StoreApi::ERROR_BTC_ADDRESS;
			$this->errorMsg = 'не указан payments и btcAddress';
			$this->resultOut();
		}

		if($payments)
		{
			foreach($payments as $key=>$payment)
			{
				$payment['walletTo'] = '+'.ltrim($payment['walletTo'], '+');

				if(!preg_match(cfg('wallet_reg_exp'), $payment['walletTo']))
				{
					$this->errorCode = StoreApi::ERROR_PAYMENTS;
					$this->errorMsg = 'неверно указан walletTo';
				}

				$payment['walletFrom'] = '+'.ltrim($payment['walletFrom'], '+');

				if(!preg_match(cfg('wallet_reg_exp'), $payment['walletFrom']))
				{
					$this->errorCode = StoreApi::ERROR_PAYMENTS;
					$this->errorMsg = 'неверно указан walletFrom';
					$this->resultOut();
				}

				if(!preg_match('!\d+$!', $payment['amount']) or $payment['amount']*1 < 1)
				{
					$this->errorCode = StoreApi::ERROR_PAYMENTS;
					$this->errorMsg = 'неверно указана сумма';
				}

				//валюта
				if(isset($payment['currency']))
					$currency = $payment['currency'];
				else
					$currency = self::DEFAULT_CURRENCY;

				$currencyArr = StoreApiTransaction::currencyArr();

				if(!isset($currencyArr[$currency]))
				{
					$this->errorCode = StoreApi::ERROR_CURRENCY;
					$this->errorMsg = 'неверно указана валюта';
				}

				if(!preg_match('!\d+$!', $payment['transactionId']))
				{
					$this->errorCode = StoreApi::ERROR_PAYMENTS;
					$this->errorMsg = 'неверно указан id';
				}

				if(!$payments)
				{
					$this->errorCode = StoreApi::ERROR_PAYMENTS;
					$this->errorMsg = 'неверный формат payments';
				}

				if(!$accountModel = Account::model()->findByAttributes(array('user_id'=>$this->store->user->id, 'login'=>$payment['walletTo'])))
				{
					toLogStoreApi('ВНИМАНИЕ!! ошибка при апи-запросе (кошелек мог уйти другому ману)  userId:'.$this->store->user->id.'|'.$payment['walletTo'].'| storeId: '.$this->store->store_id);
					$this->errorCode = StoreApi::ERROR_PAYMENTS;
					$this->errorMsg = 'не найден walletTo';
				}

				if($this->errorCode)
				{
					$this->errorMsg .=  ' в платеже '.($key+1);
					$this->resultOut();
				}

				$paymentsForSave[$key] = array(
					'account_id'=>$accountModel->id,
					'wallet_from'=>$payment['walletFrom'],
					'amount'=>$payment['amount'],
					'qiwi_id'=>$payment['transactionId'],
					'store_id'=>$this->store->store_id,
					'currency'=>$currency,
				);
			}

			//сохранить платежи
			if($models = StoreApiTransaction::addMany($paymentsForSave))
			{
				//вывести инфу о платежах
				if(!$result = StoreApiTransaction::updateInfo($models))
					toLogStoreApi('ошибка обновления платежей: '.StoreApiTransaction::$lastError);
			}
			else
				toLogStoreApi('ошибка добавления платежей: '.StoreApiTransaction::$lastError);
		}

		if($btcAddress)
		{
			//обновить адрес магазина для вывода

			if(preg_match(cfg('btcAddressRegExp'), $btcAddress))
			{
				if($btcAddress !== $this->store->withdraw_wallet)
				{
					$this->store->withdraw_wallet = $btcAddress;
					$this->store->date_wallet_change = time();

					if($this->store->save())
					{
						toLogStoreApi('сохранен withdraw_wallet для storeId='.$this->store->store_id.': '.$btcAddress);
					}
					else
						toLogStoreApi('ошибка изменения withdraw_wallet: '.$btcAddress);
				}
			}
			else
			{
				$this->errorCode = StoreApi::ERROR_BTC_ADDRESS;
				$this->errorMsg = 'неверный btcAddress';
				$this->resultOut();
			}
		}

		$this->resultOut($result);
	}

	public function actionGetWithdraw()
	{

		$params = $this->store->requestParams;

		$timestampStart = $params['timestampStart'];
		$timestampEnd = (isset($params['timestampEnd'])) ? $params['timestampEnd'] : false;
		$storeId = $this->store->store_id;

		$result = StoreApiWithdraw::getListArr($storeId, $timestampStart, $timestampEnd);

		if(StoreApiWithdraw::$lastErrorCode)
			$this->errorCode = StoreApiWithdraw::$lastErrorCode;

		$this->resultOut($result);
	}

	public function actionGetRate()
	{
		$params = $this->store->requestParams;
		$currency = $params['currency'];

		if($result = $this->store->getRate($currency))
		{
			Yii::app()->cache->set('storeApiRateBtc', $result, 120);
		}
		else
			$this->errorCode = StoreApi::$lastErrorCode;

		$this->resultOut($result);
	}


}