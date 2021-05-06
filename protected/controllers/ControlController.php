<?php

class ControlController extends Controller
{
	public $defaultAction = 'index';

	public function actionIndex()
	{
		if($this->isGlobalFin())
			$this->redirect('control/globalFinLog');
		else
			$this->redirect('control/manager');
	}

	/**
	 * статистика по менеджерам
	 * поступления за выбранный период, по умолчанию - за текущий день(05:00 - 05:00)
	 * активные кошельки на данный момент, с суммой поступлений за текущие сутки
	 *
	 */
	public function actionManager()
	{
		if(
			!$this->isControl()
			and !$this->isAdmin()
			and !$this->isModer()
		)
			$this->redirect(cfg('index_page'));


		$params = $_POST['params'];
		$interval = array();

		$dateFormat = 'd.m.Y H:i';

		if($params['date_from'] and $params['date_to'])
		{
			$intervalMin = (YII_DEBUG) ? 3600*24*30 : 3600*24*7;
			$dateMin = ($params['date_to'])
				? strtotime($params['date_to']) - $intervalMin
				: time() - $intervalMin;

			if(strtotime($params['date_from']) < $dateMin)
				$interval['date_from'] = date($dateFormat, $dateMin);
			else
				$interval['date_from'] = $params['date_from'];

			$interval['date_to'] = $params['date_to'];

			$_SESSION['intervalIn'] = $interval;
			$this->redirect('control/manager');
		}
		else
		{
			if($_SESSION['intervalIn'])
			{
				$interval = $_SESSION['intervalIn'];
			}
			else
			{
				$interval['date_from'] = date('d.m.Y');
				$interval['date_to'] = date('d.m.Y', time()+24*3600);
				$_SESSION['intervalIn'] = $interval;
			}
		}

		$currentUser = User::getUser();

		$timestampFrom = strtotime($interval['date_from']);
		$timestampTo = strtotime($interval['date_to']);

		if($timestampFrom and $timestampTo and $timestampFrom < $timestampTo)
		{
			$users = User::model()->findAll(array(
				'condition' => "`role` IN('".USER::ROLE_MANAGER."', '".USER::ROLE_FINANSIST."') AND `active`=1 AND `client_id`='".$currentUser->client_id."'",
				'order'=>"`login` ASC",
			));
		}
		else
			$this->error('неверно указана дата');



		$allAmount = 0;

		$stats = array();

		foreach($users as $user)
		{
			$arr = Transaction::controlStatsIn($timestampFrom, $timestampTo, $user->id);
			$stats[$user->id] = $arr;
			$allAmount += array_sum($arr);
		}

		//доступно для оплаты
		$condition = "`type`='".Account::TYPE_OUT."' and `error`=''";

		$info = Account::getInfo($condition);

		$result = User::buildManagerTree(User::getUser(), $stats);



		$allAmount = 0;

		foreach($result as $arr)
			$allAmount += $arr['amount'];

		//данные по кражам за выбранный период
		$ratTransactions = Transaction::getRatTransactions($timestampFrom, $timestampTo, $currentUser->client_id);

		$ratAmount = 0;

		foreach($ratTransactions as $trans)
			$ratAmount += $trans->amount;

		$this->render('stats_in', array(
			'result'=>$result,
			'allAmount'=>$allAmount,
			'forPayment'=>$info['balance_out'],
			'params'=>array(
				'date_from'=>date($dateFormat, $timestampFrom),
				'date_to'=>date($dateFormat, $timestampTo),
			),
			'ratTransactions'=>$ratTransactions,
			'ratAmount'=>$ratAmount,
			'allAmountWithRat'=>($allAmount - $ratAmount),
		));
	}

	/*
	 * кошельки в работе
	 */
	public function actionActiveWallets()
	{
		if(!$this->isFinansist())
			$this->redirect(cfg('index_page'));

		$currentUser = User::getUser();

		$params = $_POST['params'];

		if($_POST['setPriorityNow'])
		{
			if(Account::setPriorityNow($params['id']))
				$this->success('кошелек поставлен в начало очереди для проверки');
			else
				$this->error('ошибка: '.Account::$lastError);

			$this->redirect('control/activeWallets');
		}
		elseif($_POST['return'] and YII_DEBUG)
		{
			if(
				$model = Account::model()->findByPk($params['id'])
				and $model->returnInToFree()
			)
			{
				die('аккаунт '.$model->login.' как будто бы и не взят юзером');
				//$this->redirect('control/activeWallets');
			}
			else
			{
				die('ошибка: '.$model::$lastError);
			}
		}

		//чтобы вывести стату по юзерам
		$userCount = User::model()->count("`role`='".User::ROLE_USER."' AND `client_id`='{$currentUser->client_id}' AND `active`=1");

		$walletCount = 0;

		//группировано по юзерам
		$accounts = $currentUser->myManagerAccounts;

		//отключить отображение текущих акков фину если выдача закрыта
		if((!$currentUser->client->pick_accounts or !config('pickAccountEnabled')) and !$this->isAdmin())
			$accounts = [];

		//в $someData['count'] запишется число акков
		$allCount = User::$someData['count'];
		//print_r($accounts);die;


		//статистика: по меткам или просто За вчера и За сегодня
		$todayDateStart = strtotime(date('d.m.Y'));
		$todayDateEnd = $todayDateStart+3600*24 - 1;

		$yesterdayDateStart = $todayDateStart - 3600*24;
		$yesterdayDateEnd = $todayDateStart - 1;


		$stats = array(
			'today' => Transaction::myManagerStats($todayDateStart, $todayDateEnd, $currentUser->id),
			'yesterday' => Transaction::myManagerStats($yesterdayDateStart, $yesterdayDateEnd, $currentUser->id),
		);


		$statsType = 'simple';

		$this->title = 'Кошельки в работе';

		$this->render('//manager/account_list', array(
			'accounts'=>$accounts,
			'userCount'=>$userCount,
			'walletCount'=>$walletCount,
			'stats'=>$stats,
			'statsType'=>$statsType,
			'allCount'=>$allCount,
		));

	}

	/*
	 * все исходящие транзакции с исходящих кошельков
	 */
	public function actionFinansist()
	{
		if(!$this->isAdmin() and !$this->isModer()  and !$this->isConsultant())
			$this->redirect(cfg('index_page'));

		$user = User::getUser();

		if($user->client->global_fin)
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];
		$interval = array();

		$dateFormat = 'd.m.Y H:i';

		if($params['date_from'] and $params['date_to'])
		{
			$dateMin = time() - 3600*24*7;

			if(strtotime($params['date_from']) < $dateMin)
				$interval['date_from'] = date($dateFormat, $dateMin);
			else
				$interval['date_from'] = $params['date_from'];

			$interval['date_to'] = $params['date_to'];

			$_SESSION['intervalOut'] = $interval;
			$this->redirect('control/finansist');
		}
		else
		{
			if($_SESSION['intervalOut'])
			{
				$interval = $_SESSION['intervalOut'];
			}
			else
			{
				$interval['date_from'] = date('d.m.Y');
				$interval['date_to'] = date('d.m.Y', time()+24*3600);
				$_SESSION['intervalOut'] = $interval;
			}
		}

		$timestampFrom = strtotime($interval['date_from']);
		$timestampTo = strtotime($interval['date_to']);

		$stats = array();

		if($timestampFrom and $timestampTo and $timestampFrom < $timestampTo)
		{
			$stats = Transaction::finansistStats($timestampFrom, $timestampTo, $user->client_id);
		}
		else
			$this->error('неверно указана дата');

		$this->render('stats_out', array(
			'stats'=>$stats,
			'params'=>array(
				'date_from'=>date($dateFormat, $timestampFrom),
				'date_to'=>date($dateFormat, $timestampTo),
			),
		));
	}

	/*
	 * todo: переделать на подробную стату по переводам
	 * подробная стата по транзакциям (сколько куда и откуда, где осталось, куда не дошло , где не обновилось)
	 * для поиска застрявших или пропавших
	 */
	public function actionTransactionStats()
	{
		if(!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];

		$interval = array();

		$dateFormat = 'd.m.Y H:i';

		if($params['date_from'] and $params['date_to'])
		{
			$dateMin = time() - 3600*24*7;

			if(strtotime($params['date_from']) < $dateMin)
				$interval['date_from'] = date($dateFormat, $dateMin);
			else
				$interval['date_from'] = $params['date_from'];

			$interval['date_to'] = $params['date_to'];

			$interval['clientId'] = $params['clientId'];

			$_SESSION['intervalStats'] = $interval;
			$this->redirect('control/transactionStats');
		}
		else
		{
			if($_SESSION['intervalStats'])
				$interval = $_SESSION['intervalStats'];
			else
			{
				$interval['date_from'] = date('d.m.Y');
				$interval['date_to'] = date('d.m.Y', time()+24*3600);
				$interval['clientId'] = 1;
				$_SESSION['intervalStats'] = $interval;
			}
		}

		$timestampStart = strtotime($interval['date_from']);
		$timestampEnd = strtotime($interval['date_to']);

		//данные по кражам за выбранный период
		$stats = Transaction::transactionStats($timestampStart, $timestampEnd, $interval['clientId']);


		$this->render('transactionStats', array(
			'stats'=>$stats,
			'params'=>array(
				'date_from'=>date($dateFormat, $timestampStart),
				'date_to'=>date($dateFormat, $timestampEnd),
				'clientId'=>$interval['clientId'],
			),
		));
	}

	public function actionGlobalStats()
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];
		$user = User::getUser();

		$interval = array();

		$dateFormat = 'd.m.Y H:i';

		$timestampMin = time() - 3600*24*7;

		if($params['date_from'] and $params['date_to'])
		{
			//если стата по всем то только за 2 дня
			//todo: поменять, на большее кол-во дней
			$intervalMax = 3600*24*7;

			//var_dump($params['clientId']);die;

			if(empty($params['clientId']) and (strtotime($params['date_to']) - strtotime($params['date_from'])) > $intervalMax)
			{
				$interval['date_from'] = date('d.m.Y');
				$interval['date_to'] = date('d.m.Y', time()+3600*24);
			}
			else
			{
				$interval['date_from'] = $params['date_from'];
				$interval['date_to'] = $params['date_to'];
			}

			$interval['clientId'] = $params['clientId'];

			$_SESSION['intervalStats'] = $interval;
			$this->redirect('control/globalStats');
		}
		else
		{
			if($_SESSION['intervalStats'])
				$interval = $_SESSION['intervalStats'];
			else
			{
				$interval['date_from'] = date('d.m.Y');
				$interval['date_to'] = date('d.m.Y', time()+24*3600);
				$interval['clientId'] = 1;
				$_SESSION['intervalStats'] = $interval;
			}
		}

		if($_POST['limitOutsendMoney'])
		{
			//$params = array('to'=>.., 'amount'=>.., 'extra'=>..);

			$amountSend = AccountLimitOut::sendMoney($user->id, $params);

			$this->success('отправлено: '.$amountSend);

			if(AccountLimitOut::$lastError)
				$this->error(AccountLimitOut::$lastError);
		}

		$timestampStart = strtotime($interval['date_from']);
		$timestampEnd = strtotime($interval['date_to']);

		$stats = Client::globalStats($timestampStart, $timestampEnd, $interval['clientId']);

		//$currentAccounts = Client::getCurrentInAccounts($interval['clientId']);

		$this->render('globalStats', array(
			'stats'=>$stats,
			//'currentAccounts'=>$currentAccounts,
			'params'=>array(
				'date_from'=>date($dateFormat, $timestampStart),
				'date_to'=>date($dateFormat, $timestampEnd),
				'clientId'=>$interval['clientId'],
			),
		));

	}

	/*
	 * рассчитать клиента
	 */
	public function actionCalculateClient($clientId, $dateStart = '', $dateEnd = false)
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];

		$user = User::getUser();
		$client = Client::modelByPk($clientId);

		if(!$client->calc_enabled)
		{
			$this->error('расчеты клиента отключены');
			$this->redirect(cfg('index_page'));
		}


		$calcParams = ClientCalc::getCalcParams($client->id, $dateStart);	//получить параметры по-умолчанию(array)


		if(!$calcParams)
			$this->error(ClientCalc::$lastError);

		//$warnAccount = ClientCalc::$someData['warnAccount'];

		if($_POST['calculate'])
		{
			//добавление расчета автоматом
			$params['client_id'] = $client->id;
			$params['user_id'] = $user->id;

			if(ClientCalc::add($params))
			{
				$this->success('расчет добавлен');
				$this->redirect('control/CalculateClient', array('clientId'=>$params['client_id']));
			}
			else
				$this->error('ошибка: '.ClientCalc::$lastError);
		}

		if($controlCalc = $client->lastControlCalc)
		{
			$lastCalcCount = ClientCalc::model()->count("`client_id`={$client->id} AND `id`>={$controlCalc->id}");
		}
		else
			$lastCalcCount = cfg('clientLastCalcCount');

		$lastCalcArr = ClientCalc::getLastCalcArr($client->id, $lastCalcCount);

		$lastCalc = current($lastCalcArr);	//чтобы получить долг


		$statsIn = ($lastCalc) ? $client->statsIn($lastCalc->date_add, time()) : 0;

		$statsQiwi = $statsIn;


		//wex
		$coupons = Coupon::getModels($lastCalc->date_add, time(), 0, $client->id);
		$couponStats = Coupon::getStats($coupons);
		$statsIn += $couponStats['amount'] * (1 - cfg('wexPercent'));

		$statsWex = $couponStats['amount'];

		//yandex
		$yandexPayments = TransactionWex::getModels($lastCalc->date_add, time(), 0, $client->id);
		$yandexStats = TransactionWex::getStats($yandexPayments);
		$statsIn += $yandexStats['amount'];
		$statsYandex = $yandexStats['amount'];

		//new yandex
		$newYandexPayments = NewYandexPay::getModels($lastCalc->date_add, time(), 0, $client->id, true);
		$newYandexStats = NewYandexPay::getStats($newYandexPayments);
		$statsIn += $newYandexStats['amount'];
		$statsNewYandex = $newYandexStats['amount'];

		//merchant qiwi adgroup
		$merchantQiwiPayments = MerchantTransaction::getModels($lastCalc->date_add, time(), 0, $client->id, 0, ['qiwi_wallet', 'qiwi_card']);
		$merchantQiwiStats = MerchantTransaction::getStatsUser($merchantQiwiPayments);
		$statsIn += $merchantQiwiStats['amount'];
		$statsMerchantQiwi = $merchantQiwiStats['amount'];

		//merchant yandex adgroup
		$merchantYadPayments = MerchantTransaction::getModels($lastCalc->date_add, time(), 0, $client->id, 0, ['yandex']);
		$merchantYadStats = MerchantTransaction::getStatsUser($merchantYadPayments);
		$statsIn += $merchantYadStats['amount'];
		$statsMerchantYad = $merchantYadStats['amount'];

		//Yandex Account
		$statsYandexAccount = YandexTransaction::getStats($lastCalc->date_add, time(), $client->id);
		$statsIn += $statsYandexAccount['amountIn'];

		//Sim
		$simStats = SimTransaction::getStats($lastCalc->date_add, time(), $client->id);
		$statsIn += $simStats['amountIn'];

		//RiseX
		$riseXStats = RisexTransaction::getStats($lastCalc->date_add, time(), $client->id);
		$statsIn += $riseXStats['amountIn'];

		//WalletS
		$walletSPayments = WalletSTransaction::getModels($lastCalc->date_add, time(), $client->id, 0, 'successIn');
		$walletSStats = WalletSTransaction::getStats($walletSPayments);
		$statsIn += $walletSStats['amount'];
		$statsWalletS = $walletSStats['amount'];


		$recalcResult = $client->recalc();


		$this->render('calculateClient', array(
			'client'=>$client,
			'lastCalcArr'=>$lastCalcArr,
			'lastCalc'=>$lastCalc,
			//'recalcResult'=>$recalcResult,
			//'warnAccount'=>$warnAccount,
			'params'=>$params,
			'calcParams'=>$calcParams,
			'clientCalcPercent'=>config('client_calc_percent'),
			'clientCalcBonus'=>config('client_calc_bonus'),
			'recalcResult'=>$recalcResult,
			'statsIn'=>$statsIn,
			'orders'=>$client->getNotPaidOrders(),
			'statsQiwi' => $statsQiwi,
			'statsMerchantQiwi' => $statsMerchantQiwi,
			'statsMerchantYad' => $statsMerchantYad,
			'statsWex' => $statsWex,
			'statsYandex' => $statsYandex,
			'statsWalletS' => $statsWalletS,
			'statsNewYandex' => $statsNewYandex,
			'statsYandexAccount' => $statsYandexAccount['amountIn'],
			'statsSim' => $simStats['amountIn'],
			'statsRiseX' => $riseXStats['amountIn'],
			'bonuses'=>ClientCommission::getBonus($client->id),
		));
	}

	/*
	 * список расчетов (фильтр по клиенту)
	 */
	public function actionCalculateClientList($clientId=false, $action=false)
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];

		if($_POST['filter'])
		{
			$clientIds = ($params['clientId']) ? $params['clientId'] : [];

			$this->redirect('control/calculateClientList', [
				'clientId'=>implode(',', $clientIds),
				'dateStart'=>$params['dateStart'],
				'dateEnd'=>$params['dateEnd'],
			]);
		}

		if($action == 'cancel')
		{
			$client = Client::modelByPk($clientId);

			if($client->cancelLastCalc())
				$this->success('расчет отменен');
			else
				$this->error('ошибка отмены: '.Client::$lastError);

			$this->redirect('control/CalculateClientList');
		}
		elseif($action == 'delete')
		{
			$client = Client::modelByPk($clientId);

			if($client->deleteLastCalc())
				$this->success('расчет удален');
			else
				$this->error('ошибка удаления: '.Client::$lastError);

			$this->redirect('control/CalculateClientList');
		}

		$filter = [
			'clientId' => ($_GET['clientId']) ? $_GET['clientId'] : 1,
			'dateStart' => ($_GET['dateStart']) ? $_GET['dateStart'] : date('d.m.Y H:i', Tools::startOfDay()),
			'dateEnd' => ($_GET['dateEnd']) ? $_GET['dateEnd'] : date('d.m.Y H:i', Tools::startOfDay(time()+3600*24)),
		];


		$clientIds = explode(',', $filter['clientId']);
		$timestampStart = strtotime($filter['dateStart']);
		$timestampEnd = strtotime($filter['dateEnd']);

		$timestampMin = time() - 3600*24*30;

		if($timestampStart < $timestampMin)
		{
			$timestampStart = $timestampMin;
			$filter['dateStart'] = date('d.m.Y H:i', $timestampStart);
			$this->error('максимальный интервал 30 дней');
		}

		$models = ClientCalc::getListByParams($clientIds, $timestampStart, $timestampEnd);

		$this->render('calculateClientList', array(
			'models'=>$models,
			'stats'=>ClientCalc::getStats($models),
			'filter'=>[
				'clientIds' => $clientIds,
				'dateStart' => $filter['dateStart'],
				'dateEnd' => $filter['dateEnd'],
			],
			'params' => $params,
		));
	}

	public function actionCalculateClientEdit($id)
	{
		if(!$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$user = User::getUser();

		if(!$user->is_wheel)
		{
			$this->error('только рулевой может просматривать расчет клиента');
			$this->redirect('control/CalculateClientList');
		}

		$model = ClientCalc::getModelById($id);

		if(!$model)
		{
			$this->error('заявка не найдена');
			$this->redirect('control/CalculateClientList');
		}

		if(!in_array($model->status,  [ClientCalc::STATUS_NEW, ClientCalc::STATUS_WAIT]))
		{
			$this->error('расчет уже был оплачен или отменен ранее');
			$this->redirect('control/CalculateClientList');
		}

		$model->user_id = $user->id;

		if($model->changeStatusWait())
			$this->success($model::$msg);
		else
			$this->error('ошибка смены статуса заявки: '.$model::$lastError.'. обновите страницу');

		$params = [];

		if($_POST['save'])
		{
			$params = $_POST['params'];

			$model->btc_rate = $params['btc_rate'];
			$model->amount_btc = $params['amount_btc'];
			$model->comment = $params['comment'];

			if($model->changeStatusPay())
			{
				$this->success('расчет '.$model->id.'  помечен оплаченым');
				$this->redirect('control/CalculateClientList');
			}
			else
				$this->error(ClientCalc::$lastError);
		}

		$this->render('calculateClientEdit', [
			'model'=>$model,
			'params'=>$params,
			'btcRateBitfinex'=>config('btc_usd_rate_btce'),
		]);
	}

	public function actionGlobalFinLog()
	{
		$limit = 100;

		$this->render('globalFinLog', array(
			'models'=>GlobalFinLog::getList($limit),
		));
	}



	/*
	 * пересчет всех полей формы
	 */
	public function actionAjaxRecalc()
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			die('access denied');

		$result = ClientCalc::getCalcParams($_POST['clientId'], $_POST['dateStart']);


		$this->renderPartial('//system/json', array(
			'result'=>$result,
		));
	}

	/*
	 * пересчет amoun_usd_issued
	 */
	public function actionAjaxRecalcUsd()
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			die('access denied');

		$amountRub = str_replace(array(',', ' '), array('.', ''), trim($_POST['amountRub']))*1;
		$rateUsd = str_replace(array(',', ' '), array('.', ''), trim($_POST['rateUsd']))*1;

		$result = array(
			'amountUsd'=>ClientCalc::calcUsdAmount($amountRub, $rateUsd)
		);


		$this->renderPartial('//system/json', array(
			'result'=>$result,
		));
	}

	/*
	 * установить высший check_priority на все текущие кошельки клиента которые проверялись до $timestampEnd
	 */
	public function actionAjaxPriorityNow()
	{
		$result = array(
			'result'=>false,
		);

		$params = array(
			'clientId'=>$_POST['clientId'],
			'timestampEnd'=>strtotime($_POST['dateEnd']),
		);


		if(ClientCalc::setPriorityNow($params['clientId'], $params['timestampEnd']))
			$result['result'] = true;
		else
			$result['result'] = false;

		$this->renderPartial('//system/json', array(
			'result'=>$result,
		));
	}


	/*
	 * множество удобных инструментов для сапортов(Гф)
	 *
	 */
	public function actionTools()
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];
		$result = array();

		if($_POST['markPhones'])
		{
			//поиск и выделение телефона из текста слева в тексте справа
			if(preg_match_all('!((\+|)\d{11,12})!isu', $params['findWhat'], $res))
			{
				$result['findCount'] = count($res[1]);

				$result['markedText'] = str_replace("\r\n", '<br>', $params['findWhere']);
				$result['markedText'] = str_replace("\n", '<br>', $result['markedText']);
				$result['markedText'] = str_replace(" ", ' &nbsp;', $result['markedText']);
				$result['markedText'] = str_replace("\t", ' &nbsp;&nbsp;&nbsp;', $result['markedText']);

				//+записать количество замен в $result['findCount']
				$result['replaceCount'] = 0;

				foreach($res[1] as $phone)
				{
					$phone = trim($phone, '+');
					$replaceCount = 0;
					$result['markedText'] = preg_replace('!((\+|)'.$phone.')[^<]!isu', '<font color="red">$1</font>', $result['markedText'], -1, $replaceCount);

					$result['replaceCount'] += $replaceCount;
				}

			}
			else
				$this->error('искомый текст не содержит номера телефонов');

		}

		$this->render('tools', array(
			'params'=>$params,
			'result'=>$result,
		));
	}

	//информация об аккаунте (мыло с паролем)
	public function actionAccountInfo($login=false)
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];

		if($_POST['submit'])
			$this->redirect('control/accountInfo', array('login'=>trim($params['login'], '+')));

		if($login)
		{
			$login = '+'.trim($login, '+');

			$account = Account::model()->findByAttributes(array('login'=>$login));

			if(!$account)
				$this->error('аккаунт не найден');
		}
		else
			$account = false;

		$this->render('accountInfo', array(
			'account'=>$account,
			'login'=>$login,
		));
	}

	/**
	 * вывод ошибок и баланса, возможность изменить статус на success, либо удалить платеж(логировать)
	 */
	public function actionStoreApi()
	{
		if (!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$params =$_POST['params'];

		if($id = $_POST['id'])
		{
			if($model = StoreApiTransaction::getModel($id))
			{
				if($_POST['confirm'])
				{
					if($model->confirm())
					{
						$this->success('платеж '.$id.' скоро будет оплачен');
						$this->redirect('control/storeApi');
					}
					else
						$this->error(StoreApiTransaction::$lastError);
				}
				elseif($_POST['delete'])
				{
					if($model->deleteErrorModel())
					{
						$this->success('платеж '.$id.' удален');
						$this->redirect('control/storeApi');
					}
					else
						$this->error(StoreApiTransaction::$lastError);
				}
			}
			else
				$this->error('платеж не найден');

		}
		elseif($_POST['switchWithdraw'])
		{
			if(config('storeApiWithdrawEnabled'))
			{
				config('storeApiWithdrawEnabled', '');
				$msg = 'выводы отключены';
				toLogStoreApi($msg);
				$this->success($msg);
				$this->redirect('control/storeApi');
			}
			else
			{
				config('storeApiWithdrawEnabled', '1');
				$msg = 'выводы включены';
				toLogStoreApi($msg);
				$this->success($msg);
				$this->redirect('control/storeApi');
			}
		}
		elseif($_POST['switchGetWallets'])
		{
			if(config('storeApiGetWalletsEnabled'))
			{
				config('storeApiGetWalletsEnabled', '');
				$msg = 'выдача кошельков отключена';
				toLogStoreApi($msg);
				$this->success($msg);
				$this->redirect('control/storeApi');
			}
			else
			{
				config('storeApiGetWalletsEnabled', '1');
				$msg = 'выдача кошельков включена';
				toLogStoreApi($msg);
				$this->success($msg);
				$this->redirect('control/storeApi');
			}
		}
		elseif($_POST['save'])
		{
			//config('storeApiNoticeJabber', $params['noticeJabber']);
			//config('storeApiNoticeMinBalance', str_replace(',', '.', $params['noticeMinBalance']*1));

			config('storeApiNoticeJabber', $params['noticeJabber']);
			config('storeApiNoticeMinBalanceBtc', str_replace(',', '.', $params['noticeMinBalanceBtc']*1));
			$this->success('настройки сохранены');
			$this->redirect('control/storeApi');
		}
		elseif($_POST['changePriority'])
		{
			config('blockio_withdraw_priority', $params['priority']);
			$this->success('приоритет выводов изменен');
			$this->redirect('control/storeApi');
		}



		$errorTransactions = StoreApiTransaction::getErrorTransactions();

		$unknownTransactions = StoreApiTransaction::getUnknownTransactions();

		$this->render('storeApi', array(
			'errorTransactions'=>$errorTransactions,
			'btceBalanceUsd'=>config('storeApiBalanceUsd'),
			'btceBalanceBtc'=>config('storeApiBalanceBtc'),
			'btceBalanceTimestamp'=>config('storeApiBalanceTimestamp'),
			'noticeJabber'=>config('storeApiNoticeJabber'),
			'noticeMinBalance'=>config('storeApiNoticeMinBalance'),
			'unknownTransactions'=>$unknownTransactions,
			'withdrawEnabled'=>config('storeApiWithdrawEnabled'),
			'getWalletsEnabled'=>config('storeApiGetWalletsEnabled'),
			'balanceBtc'=>config('storeApiBalanceBtc'),
			'balanceBtcTimestamp'=>config('storeApiBalanceBtcTimestamp'),
			'networkCommission'=>config('storeApiCommissionBtc'),
			'noticeMinBalanceBtc'=>config('storeApiNoticeMinBalanceBtc'),
			'unconfirmedWithdraws'=>StoreApiWithdraw::getUnconfirmedWithdraws(),
			'withdrawPriority'=>config('blockio_withdraw_priority'),
			'withdrawPriorityArr'=>Blockio::getWithdrawPriorityArr(),
			'btcRateBitfinex'=>config('btc_usd_rate_btce'),
		));
	}

	public function actionStoreApiWithdraw()
	{
		if (!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$todayStart = strtotime(date('d.m.Y'));
		$todayEnd = $todayStart + 3600 * 24;

		if(!isset($_SESSION['filterStoreApi']))
			$_SESSION['filterStoreApi'] = array(
				'dateStart'=>date(cfg('dateFormatExt1'), $todayStart),
				'dateEnd'=>date(cfg('dateFormatExt1'), $todayEnd),
			);

		$filter = ($_POST['filter']) ? $_POST['filter'] : $_SESSION['filterStoreApi'];

		$_SESSION['filterStoreApi'] = $filter;

		if($_POST['filter'])
			$this->redirect('control/storeApiWithdraw');

		$this->render('storeApiWithdraw', array(
			'models'=>StoreApiWithdraw::getListModels($filter),
			'stats'=>StoreApiWithdraw::$someData['stats'],
			'filter'=>$filter,
		));

	}

	public function actionStoreApiWithdrawAdd($storeId)
	{
		if (!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];

		if($_POST['markPaid'])
		{
			if(StoreApiWithdraw::markPaid($params['storeId'], $params['transactions']))
			{
				$this->success('успех');
				$this->success(StoreApiWithdraw::$msg);
				$this->redirect('control/storeApiList');
			}
			else
			{
				$this->success(StoreApiWithdraw::$msg);
				$this->error('ошибка: '. StoreApiWithdraw::$lastError);
			}
		}

		$notPaidAmount = 0;
		$notPaidTransactions = StoreApiTransaction::getNotPaidTransactions($storeId);

		foreach($notPaidTransactions as $trans)
		{
			if($trans->currency == StoreApiTransaction::CURRENCY_RUB)
				$notPaidAmount += $trans->amount;
		}

		$this->render('storeApiWithdrawAdd', array(
			'params'=>$params,
			'store'=>StoreApi::model()->findByAttributes(array('store_id'=>$storeId)),
			'notPaidTransactions'=>$notPaidTransactions,
			'notPaidAmount'=>$notPaidAmount,
		));

	}

	public function actionStoreApiLog()
	{
		if (!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$this->render('storeApiLog', array(
			'logContent'=>Tools::logOut(0, 'storeApi', false)
		));
	}

	public function actionStoreApiList()
	{
		if (!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];

		if($_POST['save'])
		{
			$setCount = StoreApi::setWithdrawLimit($_POST['withdraw_limit']);

			if($setCount)
				$this->success('отредактировано '.$setCount.' записей');

			if(StoreApi::$lastError)
				$this->error('ошибка: '.StoreApi::$lastError);
		}
		elseif($_POST['switchStatus'])
		{
			//включить-отключить
			$id = $params['id'];

			if(StoreApi::switchStatus($id))
				$this->success('статус магазина изменен');
			else
				$this->error('ошибка: '.StoreApi::$lastError);
		}

		$this->render('storeApiList', array(
			'models'=>StoreApi::getStoreArr(),
		));

	}

	public function actionStoreApiTransactions()
	{
		if (!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$todayStart = strtotime(date('d.m.Y'));
		$todayEnd = $todayStart + 3600 * 24;

		if(!isset($_SESSION['filterStoreApi']))
			$_SESSION['filterStoreApi'] = array(
				'dateStart'=>date(cfg('dateFormatExt1'), $todayStart),
				'dateEnd'=>date(cfg('dateFormatExt1'), $todayEnd),
			);

		$filter = ($_POST['filter']) ? $_POST['filter'] : $_SESSION['filterStoreApi'];

		$_SESSION['filterStoreApi'] = $filter;

		if($_POST['filter'])
			$this->redirect('control/storeApiTransactions');

		$this->render('storeApiTransactions', array(
			'models'=>StoreApiTransaction::getListModels($filter),
			'stats'=>StoreApiTransaction::$someData['stats'],
			'filter'=>$filter,
		));

	}

	public function actionStoreApiRequest()
	{
		if (!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$todayStart = strtotime(date('d.m.Y'));
		$todayEnd = $todayStart + 3600 * 24;

		if(!isset($_SESSION['filterStoreApi']))
			$_SESSION['filterStoreApi'] = array(
				'dateStart'=>date(cfg('dateFormatExt1'), $todayStart),
				'dateEnd'=>date(cfg('dateFormatExt1'), $todayEnd),
			);

		$filter = ($_POST['filter']) ? $_POST['filter'] : $_SESSION['filterStoreApi'];

		$_SESSION['filterStoreApi'] = $filter;

		$this->render('storeApiRequest', array(
			'models'=>StoreApiRequest::getListModels($filter),
			'stats'=>StoreApiRequest::$someData['stats'],
			'filter'=>$filter,
		));

	}

	/**
	 * монитор комментариев к платежам
	 * только для админа
	 */
	public function actionCommentMonitor()
	{
		if(!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];

		if($_POST['save'])
		{
			if(Transaction::setBadWordsContent($params['badWordsContent']))
			{
				$this->success('сохранено');
				$this->redirect('control/commentMonitor');
			}
			else
				$this->error(Transaction::$lastError);
		}

		$this->render('commentMonitor', array(
			'lastTransactions'=>Transaction::getLastTransactionsIn(true),
			'stats'=>Transaction::$someData['stats'],
			'badWordsContent'=>Transaction::getBadWordsContent(),
		));
	}

	/**
	 * монитор плохих платежей(по комментам и огрНаИсх приходу..)
	 */
	public function actionTransactionMonitor()
	{
		if (!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$transactions = Transaction::getBadTransactionsIn();

		if(count($transactions) < 1000)
			$transactions = array_merge($transactions, Transaction::getLastTransactionsIn());


		$this->render('transactionMonitor', array(
			'badTransactions'=>$transactions,
			'stats'=>Transaction::$someData['stats'],
		));
	}

	public function actionAntiban()
	{
		if (!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];

		if(!$params) $params = array();

		if($_POST['banGroup'])
		{
			//забанить группу
			if(Account::banGroup($params['clientId'], $params['groupId'], $params['withCleanWallets']))
			{
				$this->success('успех: '.Account::$someData['msg']);
				$this->redirect('control/antiban');
			}
			else
				$this->error('ошибка: '.Account::$lastError);
		}
		elseif($_POST['banMany'])
		{
			//забанить выбранные
			$result = Account::banMany($params['walletsStr']);
			$this->success(Account::$msg);

			if($result)
			{
				$this->redirect('control/antiban');
			}
			else
				$this->error('ошибка: '.Account::$lastError);
		}
		elseif($_POST['trans'])
		{
			//перевести средства (только error=ban)
			$amount = Account::transFromMany($params['from'], $params['to']);

			if($amount !== false)
				$this->success('переведено: '.$amount);

			if(Account::$lastError)
				$this->error(Account::$lastError);
		}


		$this->render('antiban', array(
			'params'=>$params,
			'msg'=>Account::$msg,
			'oldAccounts'=>Account::getOldAccounts(),	//группа риска(давно добавленные кошельки)
		));
	}

	/*
	 * информация о системе распознавания капчи
	 */
	public function actionAntiCaptcha()
	{
		if(!$this->isAdmin())
			$this->redirect('index_page');

		$dateAdd = time() - 1800; //за последние n секунд

		$models = AntiCaptcha::model()->findAll(array(
			'condition'=>"`date_add`>$dateAdd",
			'order'=>"`date_add` DESC",
		));


		$this->render('antiCaptcha', array(
			'dateAdd'=>$dateAdd,
			'models'=>$models,
			'stats'=>AntiCaptcha::getStats($models),
		));
	}

	/**
	 * todo: список входящих транзакций(не дошедших)
	 * todo: запихать проверку в модель StoreApi
	 */
	public function actionStoreApiDeposit()
	{
		if (!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		//чтобы нельзя было случайно накосячить
		$oldRate = config('storeApiBtcRate');
		$rateMin = $oldRate*0.8;
		$rateMax = $oldRate*1.2;

		$depositAddress = '';

		$params = $_POST['params'];

		if($_POST['submit'])
		{
			//убрать разделитель тысяч
			$rate = str_replace(',', '', $params['btcRate'])*1;

			if($rate >= $rateMin and $rate <= $rateMax)
			{
				if($depositAddress = StoreApi::getDepositAddress($params['changeAddress']))
				{
					config('storeApiBtcRate', $rate);
					$this->success('курс сохранен');
					$this->success('для пополнения кошелька, отправьте BTC на указанный адрес');
				}
				else
					$this->error('ошибка получения адреса для пополнения: '.StoreApi::$lastError);
			}
			else
				$this->error('изменение курса слишком критичное для сохранения. должно быть от '.$rateMin.' до '.$rateMax);
		}

		$this->render('storeApiDeposit', [
			'btcRate'=>config('storeApiBtcRate'),
			'depositAddress'=>$depositAddress,
			'changeAddressCount' => config('blockio_change_address_left'),
		]);
	}

	/**
	 * массовая постановка на проверку кошельков
	 * ставит check_priority=2(дает првоерять даже ушедшие кошельки)
	 * ставятся на првоерку только `type`='in' and error=''
	 * @param int|bool $clientId если указан то отображать кошельки которые еще не првоерились (check_priority=2)
	 * todo: цепляться надо не за дату проверки а за чтото другое например за последний приход
	 */
	public function actionMassCheck($clientId = false)
	{
		if(!$this->isAdmin())
			$this->redirect('index_page');

		$clientId = $clientId*1;

		$params = $_POST['params'];

		if($_POST['submit'])
		{
			$checkCount = Account::massCheck($params['clientId'], $params['date']);

			if($checkCount !== false)
			{
				$this->success('поставлено на проверку '.$checkCount);
				$this->success(Account::$msg);
				$this->redirect('control/massCheck', ['clientId'=>$params['clientId']]);
			}
			else
				$this->error('ошибка: '.Account::$lastError);
		}

		$notCheckedAccounts = ($clientId)
			? Account::model()->findAll("`check_priority`=".Account::PRIORITY_NOW." AND `client_id`=$clientId AND `type`='".Account::TYPE_IN."' AND `user_id`>0 AND `enabled`=1")
			: [];

		$this->render('massCheck', [
			'clientId'=>$clientId,
			'notCheckedAccounts'=> $notCheckedAccounts,
			'params'=>$params,
		]);
	}

	public function actionBanChecker()
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];

		if($_POST['add'])
		{
			$addCount = BanChecker::addMany($params['loginContent'], $params['noSearch']);

			$this->success('добавлено: '.$addCount);

			if(BanChecker::$lastError)
				$this->error(BanChecker::$msg);
			else
				$this->redirect('control/banChecker');
		}
		elseif($_POST['clearOld'])
		{
			$deleteCount = BanChecker::clearOld();

			$this->success('очищено: '.$deleteCount);

			if(BanChecker::$lastError)
				$this->error(BanChecker::$lastError);
			else
				$this->redirect('control/banChecker');
		}
		elseif($_POST['clearAll'])
		{
			$deleteCount = BanChecker::clearAll();

			$this->success('очищено: '.$deleteCount);

			if(BanChecker::$lastError)
				$this->error(BanChecker::$lastError);
			else
				$this->redirect('control/banChecker');
		}
		elseif($_POST['selectActiveAccounts'])
		{
			$client = Client::getModel($params['clientId']);
			$activeAccounts = $client->activeAccounts;
		}
		elseif($_POST['selectClientWallets'])
		{
			$client = Client::getModel($params['clientId']);
			$clientWallets = $client->lastWallets;
		}
		elseif($_POST['linkSearch'])
		{
			$accounts = Transaction::linkSearch($params['wallet']);

			if($accounts)
			{
				$msg = '';

				foreach($accounts as $account)
					$msg .= "{$account->login}<br>";

				$this->success($msg);
			}
			elseif($accounts !== null)
				$this->success('не найдено переводов');
			else
				$this->error(Transaction::$lastError);
		}

		$this->render('banChecker', [
			'params'=>$params,
			'models'=>BanChecker::getModelsForView(),
			'stats'=>BanChecker::getStats(),
			'activeAccounts'=>isset($activeAccounts) ? $activeAccounts : [],
			'clientWallets'=>isset($clientWallets) ? $clientWallets : [],
		]);
	}

	public function actionOrderConfig()
	{
		if (!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];

		if($_POST['save'])
		{
			$countSave = ManagerOrderConfig::saveConfig($params);

			$this->success('сохранено: '.$countSave);

			if(ManagerOrderConfig::$lastError)
				$this->error(ManagerOrderConfig::$lastError);

			if(!ManagerOrderConfig::setTimeout($_POST['managerOrderTimeout']))
				$this->error(ManagerOrderConfig::$lastError);
		}

		$this->render('orderConfig', [
			'params'=>$params,
			'clients'=>Client::getActiveClients(),
			'managerOrderTimeout'=>round(config('managerOrderTimeout')/3600),
		]);
	}

	/**
	 * статистика использования кошельков по клиентам:
	 * -взятые кошельки
	 * -текущие кошельки
	 * -объем по приходу
	 */
	public function actionAccountStats()
	{

	}

	public function actionLatestOrders()
	{
		if(!$this->isAdmin() and !$this->isGlobalFin() and !$this->isFinansist())
			$this->redirect(cfg('index_page'));

		$timestampStart = time() - 3600 * 24 * 7;
		$user = User::getUser();

		if($this->isFinansist())
			$models = ManagerOrder::getLatestOrders($timestampStart, $user->client_id);
		else
			$models = ManagerOrder::getLatestOrders($timestampStart);



		$this->render('latestOrders', [
			'timestampStart'=>$timestampStart,
			'models'=>$models
		]);
	}

	/**
	 * монитор текущих кошельков и переводов
	 * @param int $clientId
	 * @param string $dateStart
	 */
	public function actionCommissionMonitor($clientId, $dateStart)
	{
		$client = Client::modelByPk($clientId);
		$stats = $client->commissionStats(strtotime($dateStart));

		$this->render('commissionMonitor', [
			'client' => $client,
			'stats' => $stats,
		]);
	}


	public function actionVouchers()
	{
		if(!$this->isGlobalFin() and !$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$params = Yii::app()->request->getPost('params');
		$session = &Yii::app()->session;

		$interval = [];

		if($params['dateStart'] and $params['dateEnd'])
		{
			$interval['clientId'] = $params['clientId'];

			$session['voucherStats'] = $interval;
			$this->redirect('control/vouchers');
		}
		elseif(Yii::app()->request->getPost('createVouchers'))
		{
			if(!$params['wallets'])
			{
				$balanceAccounts = Account::getAccountsWithBalance($params['clientId']);

				foreach($balanceAccounts as $account)
					$params['wallets'] .= "{$account->login}\n";
			}
			prrd($params);

			$createResult = AccountVoucher::createAllFromWallets($params['wallets']);
			var_dump($createResult);
		}
		else
		{
			if($session['voucherStats'])
				$interval = $session['voucherStats'];
			else
			{
				$interval = [
					'dateStart' => date('d.m.Y'),
					'dateEnd' => date('d.m.Y', time()+24*3600),
					'clientId' => 0,
				];

				$session['intervalStats'] = $interval;
			}
		}

		$models = AccountVoucher::getModelsForView($interval['clientId'],
			strtotime($interval['dateStart']), strtotime($interval['dateEnd']));

		$this->render('vouchers', [
			'models' => $models,
			'params' => $params,
			'interval' => $interval,
		]);
	}

	public function actionApiTokenPersonal($token = '')
	{
		if(!$this->isGlobalFin() and !$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$cfg = [
			'proxy' => 'adm:LDFJLDKdifj74fj43j43@93.117.137.49:7778',
			'clientId' => 'wallet_api_303155',
			'clientSecret' => '%ks#g4zQvxeD',
		];

		$sender = new Sender;
		$sender->followLocation = false;
		$sender->useCookie = false;

		$params = $_POST['params'];

		if($_POST['submitToken'])
		{
			$params['login'] = trim($params['login'], '+');
			$params['pass'] = trim($params['pass']);

			$url = 'http://qiwi.com/oauth/authorize';
			$post = 'response_type=code&client_id='.$cfg['clientId'].'&client_secret='
				.urlencode($cfg['clientSecret']).'&username='.$params['login'].'&password='.urlencode($params['pass']);

			$content = $sender->send($url, $post, $cfg['proxy']);

			if(preg_match('!\{"code":"(.+?)"\}!', $content, $res))
			{
				$this->success('введите смс с номера: +'.$params['login']);
				$this->redirect('control/ApiTokenPersonal', ['token'=>$res[1]]);
			}
			else
				$this->error('error: '.$content);
		}
		elseif($_POST['submitSms'])
		{
			$url = 'http://qiwi.com/oauth/token';

			$post = 'grant_type=urn:qiwi:oauth:grant-type:vcode&client_id='.$cfg['clientId']
				.'&client_secret='.urlencode($cfg['clientSecret']).'&code='.$token.'&vcode='.trim($params['sms']);

			$content = $sender->send($url, $post, $cfg['proxy']);

			if(preg_match('!{"access_token":"(.+?)","token_type":"Bearer","expires_in":"\d+","refresh_token":"(.+?)"}!', $content, $res))
			{
				$this->success("token: ".$res[1]);
				$this->success("refresh: ".$res[2]);
				$this->redirect('control/ApiTokenPersonal');
			}
			else
				$this->error('error: '.$content);
		}

		$this->render('apiTokenPersonal', [
			'params'=>$params,
			'token'=>$token,
		]);
	}

	public function actionCriticalWallets()
	{
		$params = $_POST['params'];

		if($_POST['save'])
		{
			AccountCritical::setAccounts($params);
		}

		$this->render('criticalWallets', [
			'params'=>$params,
			'models'=>Account::getCurrentInAccounts(0, true, "`client_id` ASC"),
		]);
	}

	public function actionConfig()
	{
		Yii::app()->theme = 'basic';

		if(!$this->isGlobalFin() and !$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];

		if($_POST['save'])
		{
			if(ClientCalc::setBtcUsdRateSource($params['btc_usd_rate_source']))
			{
				$this->success('сохранено. дождитесь обновления курса (раз в минуту)');
				$this->refresh();
			}
			else
				$this->error(ClientCalc::$lastError);

		}

		$config = [
			'btc_usd_rate_source' => config('btc_usd_rate_source'),
		];

		$this->render('config', [
			'params' => $params,
			'config' => $config,
		]);
	}
}