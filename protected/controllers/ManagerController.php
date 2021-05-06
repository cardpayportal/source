<?php

class ManagerController extends Controller
{
	public $defaultAction = 'index';
	
	public function actionIndex()
	{
		$userModel = User::getUser();
		if($userModel and $userModel->id == cfg('imageUserId'))
			$this->redirect('manager/imageList');
		else
			$this->redirect('user/profile');
	}


	/**
	 * список использованных кошельков для манагера
	 * возрождение киви
	 */
	public function actionAccountList()
	{
		if(!$this->isManager() and !$this->isFinansist() and !$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		if($this->isAdmin() or $this->isGlobalFin())
			$user = User::model()->findByPk(148);
		else
			$user = User::getUser();

		$params = $_POST['params'];

		$incomeMode = $user->client->income_mode; //wallet | order

		if($incomeMode == Client::INCOME_ORDER)
			$this->redirect('manager/orderList');

		$accounts = $user->accounts;

		//test отключение отображения кошельков
		if((!$user->client->pick_accounts or !config('pickAccountEnabled')))
			$accounts = [];

		$slowCheckAccounts = Account::getSlowCheckAccounts($accounts);

		if($_POST['setPriorityNow'])
		{
			if(Account::setPriorityNow($params['id']))
			{
				if($this->isAdmin())
					die('кошелек поставлен в начало очереди для проверки');
				else
					$this->success('кошелек поставлен в начало очереди для проверки');
			}
			else
				$this->error('ошибка: '.Account::$lastError);

			$this->redirect('manager/accountList');
		}
		elseif($_POST['return'] and YII_DEBUG)
		{
			if(
				$model = Account::model()->findByPk($params['id'])
				and $model->returnInToFree()
			)
			{
				die('аккаунт '.$model->login.' как будто бы и не взят юзером');
				//$this->redirect('manager/accountList');
			}
			else
				die('ошибка: '.$model::$lastError);
		}
		elseif($_POST['toggleHidden'])
		{
			if(Account::toggleHidden($_POST['id'], $user->id))
			{
				$this->success(Account::$msg);
				$this->redirect('manager/accountList');
			}
			else
				$this->error(Account::$lastError);
		}
		elseif($_POST['clearCookiesSlowCheck'])
		{
			$clearCount = 0;

			foreach($slowCheckAccounts as $slow)
			{
				if(QiwiBot::clearCookie($slow->login))
					$clearCount++;
				else
					$this->error('ошибка очистки куки у '.$slow->login);
			}

			$this->success('очищены куки у '.$clearCount.' аккаунтов');
			$this->redirect('manager/accountList');
		}



		//статистика: по меткам или просто За вчера и За сегодня
		$todayDateStart = strtotime(date('d.m.Y'));
		$todayDateEnd = $todayDateStart+3600*24 - 1;

		$yesterdayDateStart = $todayDateStart - 3600*24;
		$yesterdayDateEnd = $todayDateStart - 1;


		$stats = array(
			'today' => Transaction::managerStats($todayDateStart, $todayDateEnd, $user->id),
			'yesterday' => Transaction::managerStats($yesterdayDateStart, $yesterdayDateEnd, $user->id),
		);

		$statsType = 'simple';

		$this->render('account_list', array(
			'accounts'=>$accounts,
			'stats'=>$stats,
			'statsType'=>$statsType,
			'slowCheckAccounts'=>$slowCheckAccounts, //выбрать аккаунты которые не проверялись последние пол часа
			'oldPickAccounts'=>Account::getOldPickAccounts($accounts), //выбрать аккаунты которые пикнули давно
			'allCount' => count($accounts),
			'user'=>$user,
		));
	}

	
	/**
	 * список использованных кошельков для манагера
	 */
	public function actionAccountListOld()
	{
		if(!$this->isManager() and !$this->isFinansist() and !$this->isAdmin())
			$this->redirect(cfg('index_page'));
		
		$user = User::getUser();

		$params = $_POST['params'];

		$incomeMode = $user->client->income_mode; //wallet | order

		if($incomeMode == Client::INCOME_ORDER)
			$this->redirect('manager/orderList');

		$accounts = $user->accounts;

		//TODO: вернуть потом условие
//		if((!$user->client->pick_accounts or !config('pickAccountEnabled')) and !$this->isAdmin())
//			$accounts = [];

		$slowCheckAccounts = Account::getSlowCheckAccounts($accounts);
		
		if($_POST['setPriorityNow'])
		{
			if(Account::setPriorityNow($params['id']))
			{
				if($this->isAdmin())
					die('кошелек поставлен в начало очереди для проверки');
				else
					$this->success('кошелек поставлен в начало очереди для проверки');
			}
			else
				$this->error('ошибка: '.Account::$lastError);

			$this->redirect('manager/accountList');
		}
		elseif($_POST['return'] and YII_DEBUG)
		{
			if(
				$model = Account::model()->findByPk($params['id'])
				and $model->returnInToFree()
			)
			{
				die('аккаунт '.$model->login.' как будто бы и не взят юзером');
				//$this->redirect('manager/accountList');
			}
			else
				die('ошибка: '.$model::$lastError);
		}
		elseif($_POST['toggleHidden'])
		{
			if(Account::toggleHidden($_POST['id'], $user->id))
			{
				$this->success(Account::$msg);
				$this->redirect('manager/accountList');
			}
			else
				$this->error(Account::$lastError);
		}
		elseif($_POST['clearCookiesSlowCheck'])
		{
			$clearCount = 0;

			foreach($slowCheckAccounts as $slow)
			{
				if(QiwiBot::clearCookie($slow->login))
					$clearCount++;
				else
					$this->error('ошибка очистки куки у '.$slow->login);
			}

			$this->success('очищены куки у '.$clearCount.' аккаунтов');
			$this->redirect('manager/accountList');
		}
		

		
		//статистика: по меткам или просто За вчера и За сегодня
		$todayDateStart = strtotime(date('d.m.Y'));
		$todayDateEnd = $todayDateStart+3600*24 - 1;
		
		$yesterdayDateStart = $todayDateStart - 3600*24;
		$yesterdayDateEnd = $todayDateStart - 1;
		

		$stats = array(
			'today' => Transaction::managerStats($todayDateStart, $todayDateEnd, $user->id),
			'yesterday' => Transaction::managerStats($yesterdayDateStart, $yesterdayDateEnd, $user->id),
		);

		$statsType = 'simple';


		$session = &Yii::app()->session;
		$request = &Yii::app()->request;

		//$user = User::getUser();
		$params = $request->getPost('params');

		$interval = isset($session['qiwiStats']) ? $session['qiwiStats'] : [];

		$payParams = [];

		if($request->getPost('pay'))
		{
			if($payParams = NextQiwiPay::getPayParams($user->id, $params['amount']))
			{
				$this->success('Реквизиты получены');
			}
			else
				$this->error(NextQiwiPay::$lastError);
		}
		elseif($request->getPost('check'))
		{
			if(!NextQiwiPay::mark($params['id'], $user->id, QiwiPay::MARK_CHECKED))
				$this->error(QiwiPay::$lastError);

			$this->redirect('manager/accountList');
		}
		elseif($request->getPost('cancel'))
		{
			if(!NextQiwiPay::mark($params['id'], $user->id, QiwiPay::MARK_UNCHECKED))
				$this->error(QiwiPay::$lastError);

			$this->redirect('manager/accountList');
		}
		elseif($request->getPost('getTransactionStatus'))
		{
			if(NextQiwiPay::getTransactionStatus($params['id']))
				$this->success(' платеж принят ');

			$this->redirect('manager/accountList');
		}
		elseif($params['dateStart'] and $params['dateEnd'])
		{
			$interval = $params;

			$session['qiwiStats'] = $interval;
			$this->redirect('manager/accountList');
		}
		elseif($_POST['updateHistory'])
		{
			if(
				$payeerAccount = PayeerAccount::getModel(['id'=>$params['accountId']])
				and
				$payeerAccount->user_id == $user->id
			)
			{
				if(time() - $payeerAccount->date_check > 60)
				{
					if(QiwiPay::startUpdateHistory(0, $payeerAccount->id))
					{
						$this->success('платежи обновлены');
						$this->redirect('manager/accountList');
					}
					else
						$this->error('ошибка обновления');
				}
				else
					$this->error('обновление доступно не чаще чем раз в минуту');

			}
			else
				$this->error('неверный аккаунт');
		}
		else
		{
			if($session['qiwiStats'])
				$interval = $session['qiwiStats'];
			else
			{
				$interval = [
					'dateStart' => date('d.m.Y H:i', Tools::startOfDay(time())),
					'dateEnd' => date('d.m.Y H:i', time()+24*3600),
				];

				$session['qiwiStats'] = $interval;
			}
		}

		$userId = (!$this->isManager()) ? 0 : $user->id;
		$clientId = (!$this->isManager()) ? $user->client_id : 0;


		$models = NextQiwiPay::getModels(
			strtotime($interval['dateStart']),
			strtotime($interval['dateEnd']),
			$userId,
			$clientId,
			false
		);

		$this->render('account_list', array(
			'accounts'=>$accounts,
			'stats'=>$stats,
			'statsQiwi'=>NextQiwiPay::getStats($models),
			'payParams' => $payParams,
			'models'=>$models,
			'params'=>$params,
			'interval'=>$interval,
			'statsType'=>$statsType,
            'slowCheckAccounts'=>$slowCheckAccounts, //выбрать аккаунты которые не проверялись последние пол часа
            'oldPickAccounts'=>Account::getOldPickAccounts($accounts), //выбрать аккаунты которые пикнули давно
			'allCount' => count($accounts),
			'user'=>$user,
		));
	}
	
	/**
	 * список использованных кошельков для манагера
	 */
	public function actionAccountUsed()
	{
		if(!$this->isManager() and !$this->isFinansist() and !$this->isAdmin())
			$this->redirect(cfg('index_page'));
		
		$user = User::getUser();
		
		$params = $_POST['params'];

		$accounts = $user->getAccounts(true, true, 20);
		
		$this->render('account_used', array(
			'accounts'=>$accounts,
		));
	}
	
	public function actionAccountAdd()
	{
		if(!$this->isManager() and !$this->isFinansist())
			$this->redirect(cfg('index_page'));
			
		$user = User::getUser();
		
		$params = $_POST['params'];

		$incomeMode = $user->client->income_mode; //wallet | order

		if($incomeMode == Client::INCOME_ORDER)
			$this->redirect('manager/orderAdd');

		if($_POST['add'] or $_POST['add_full'] or $_POST['addApiHalf'] or $_POST['addApiFull'])
		{
			if ($_POST['add_full'])
				$params['status'] = 'full';
			elseif($_POST['add'])
				$params['status'] = 'half';
			elseif($_POST['addApiHalf'])
				$params['status'] = 'apiHalf';
			elseif($_POST['addApiFull'])
				$params['status'] = 'apiFull';

			if($user->pickAccounts($params))
			{
				$this->success('Кошельки получены'.$user::$msg);
				$this->redirect('manager/accountList');
			}
			else
				$this->error('ошибка: ' . $user::$lastError);
		}

		$this->render('account_add', array(
			'params'=>$params,
			'freeCountHalf'=>count(Account::getFreeInAccounts($user->client_id, 'half')),//свободных обычных
			'freeCountFull'=>count(Account::getFreeInAccounts($user->client_id, 'full')),//свободных с полным идентом
			'apiCountHalf'=>count(Account::getFreeInAccounts($user->client_id, 'apiHalf')),
			'apiCountFull'=>count(Account::getFreeInAccounts($user->client_id, 'apiFull')),
		));
	}

	public function actionAjaxChangeLabel()
	{
		$result = array(
			'error'=>0,
		);
		
		$accountId = $_POST['id']*1;
		$label = strip_tags($_POST['label']);
		
		$user = User::getUser();
		
		$accounts = $user->getAccounts(false);
		
		foreach($accounts as $account)
		{
			if($account->id==$accountId)
			{
				Account::model()->updateByPk($account->id, array('label'=>$label));
				$result['error']=0;
				break;
			}
			else
				$result['error'] = 'аккаунт не найден';
		}
		
		$this->renderPartial('//system/json', array('result'=>$result));
	}

	public function actionOrderList()
	{
		if(!$this->isManager() and !$this->isFinansist())
			$this->redirect(cfg('index_page'));

		$user = User::getUser();

		if($user->client->income_mode == Client::INCOME_WALLET)
			$this->redirect('manager/accountList');

		$params = $_POST['params'];

		if($_POST['complete'])
		{
			if(ManagerOrder::complete($params['orderId'], $user->id))
			{
				$this->success('заявка #'.$params['orderId'].' завершена');
				$this->redirect('manager/orderList');
			}
			else
				$this->error(ManagerOrder::$lastError);
		}
		elseif($_POST['setPriorityNow'])
		{
			if(isset($params['orderId']))
			{
				if(ManagerOrder::setPriorityNow($params['orderId'], $user->id))
					$this->success('кошельки поставлены в начало очереди на проверку');
				else
					$this->error(ManagerOrder::$lastError);
			}
			elseif(isset($params['accountId']))
			{
				if(Account::setPriorityNow($params['accountId']))
					$this->success('кошелек поставлен в начало очереди для проверки');
				else
					$this->error('ошибка: '.Account::$lastError);
			}

			$this->redirect('manager/accountList');
		}

		$this->render('orderList', [
			'orders'=>ManagerOrder::getActiveOrders($user->id),
			'params'=>$params,
		]);
	}

	public function actionOrderAdd()
	{
		if(!$this->isManager() and !$this->isFinansist())
			$this->redirect(cfg('index_page'));

		$user = User::getUser();

		$params = $_POST['params'];

		$incomeMode = $user->client->income_mode; //wallet | order

		if($incomeMode == Client::INCOME_WALLET)
			$this->redirect('manager/accountAdd');

		if($_POST['add'])
		{

			$params['user_id'] = $user->id;

			//чтобы небыло одновременных взятий 2мя юзерами одного кошелька
			sleep(rand(1, 10));

			if(ManagerOrder::add($params))
			{
				$this->success('Заявка создана');
				$this->redirect('manager/orderList');
			}
			else
				$this->error(ManagerOrder::$lastError);
		}

		$activeOrders = ManagerOrder::getActiveOrders($user->id);
		$orderConfig = $user->client->orderConfig;

		$this->render('orderAdd', array(
			'params'=>$params,
			'orderConfig'=>$orderConfig,
			'showForm'=>(count($activeOrders) < $orderConfig['manager_order_count_max']),
		));
	}

	public function actionOrderUsed()
	{
		if(!$this->isManager() and !$this->isFinansist())
			$this->redirect(cfg('index_page'));

		$timestampStart = time() - 3600*24*7;

		$user = User::getUser();

		$incomeMode = $user->client->income_mode; //wallet | order

		if($incomeMode == Client::INCOME_WALLET)
			$this->redirect('manager/accountUsed');

		$this->render('orderUsed', [
			'models'=>ManagerOrder::getUsedOrders($user->id, $timestampStart),
			'timestampStart'=>$timestampStart,
		]);
	}

	public function actionStats()
	{
		if(!$this->isManager() and !$this->isFinansist())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];
		$user = User::getUser();
		$interval = [];

		$dateFormat = 'd.m.Y H:i';

		if($params['date_from'] and $params['date_to'])
		{
			$intervalMin = 3600*24*7;

			$dateMin = ($params['date_to'])
				? strtotime($params['date_to']) - $intervalMin
				: time() - $intervalMin;

			if(strtotime($params['date_from']) < $dateMin)
				$interval['date_from'] = date($dateFormat, $dateMin);

			if(strtotime($params['date_from']) < $dateMin)
				$interval['date_from'] = date($dateFormat, $dateMin);
			else
				$interval['date_from'] = $params['date_from'];

			$interval['date_to'] = $params['date_to'];

			$_SESSION['intervalIn'] = $interval;
			$this->redirect('manager/stats');
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

		$timestampFrom = strtotime($interval['date_from']);
		$timestampTo = strtotime($interval['date_to']);

		$allAmount = 0;
		$stats = [];

		if($timestampFrom and $timestampTo and $timestampFrom < $timestampTo)
		{
			$stats = Transaction::controlStatsIn($timestampFrom, $timestampTo, $user->id);
			$allAmount = array_sum($stats);
		}
		else
			$this->error('неверно указана дата');

		$this->render('stats', array(
			'stats'=>$stats,
			'allAmount'=>$allAmount,
			'params'=>[
				'date_from'=>date($dateFormat, $timestampFrom),
				'date_to'=>date($dateFormat, $timestampTo),
			],
		));
	}

	public function actionWex()
	{
		if(!$this->isManager() and !$this->isFinansist() and !$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$session = &Yii::app()->session;

		$user = User::getUser();
		$params = Yii::app()->request->getPost('params');

		$interval = isset($session['couponStats']) ? $session['couponStats'] : [];

		if(Yii::app()->request->getPost('add'))
		{
			$addCount = Coupon::addMany($params['coupons'], $user->id);

			$this->success('активировано: '.$addCount);
			$this->success(Coupon::$msg);

			if(Coupon::$lastError)
				$this->error(Coupon::$lastError);
			else
				$this->redirect('manager/wex');
		}
		elseif($params['dateStart'] and $params['dateEnd'])
		{
			$interval = $params;

			$session['couponStats'] = $interval;
			$this->redirect('manager/wex');
		}
		else
		{
			if($session['couponStats'])
				$interval = $session['couponStats'];
			else
			{
				$interval = [
					'dateStart' => date('d.m.Y H:i', Tools::startOfDay(time())),
					'dateEnd' => date('d.m.Y H:i', time()+24*3600),
				];

				$session['couponStats'] = $interval;
			}
		}

		$userId = (!$this->isManager()) ? 0 : $user->id;
		$clientId = (!$this->isManager()) ? $user->client_id : 0;


		$models = Coupon::getModels(
			strtotime($interval['dateStart']),
			strtotime($interval['dateEnd']),
			$userId,
			$clientId
		);

		$this->render('wexCoupons', [
			'models'=>$models,
			'stats'=>Coupon::getStats($models),
			'params'=>$params,
			'interval'=>$interval,
		]);
	}

	public function actionYandexPay()
	{
		if(!$this->isManager() and !$this->isFinansist() and !$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$session = &Yii::app()->session;
		$request = &Yii::app()->request;


		$user = User::getUser();
		$params = $request->getPost('params');

		$interval = isset($session['yandexStats']) ? $session['yandexStats'] : [];

		$payUrl = '';

		if($request->getPost('pay'))
		{
			$payUrl = YandexPay::getPayUrl($user->id, $params['amount']);

			if(!$payUrl)
				$this->error(YandexPay::$lastError);
		}
		elseif($request->getPost('check'))
		{
			if(!YandexPay::mark($params['id'], $user->id, YandexPay::MARK_CHECKED))
				$this->error(YandexPay::$lastError);

			$this->redirect('manager/yandexPay');
		}
		elseif($request->getPost('cancel'))
		{
			if(!YandexPay::mark($params['id'], $user->id, YandexPay::MARK_UNCHECKED))
				$this->error(YandexPay::$lastError);

			$this->redirect('manager/yandexPay');
		}
		elseif($params['dateStart'] and $params['dateEnd'])
		{
			$interval = $params;


			$session['yandexStats'] = $interval;
			$this->redirect('manager/yandexPay');
		}
		elseif($_POST['updateHistory'])
		{
			if(
				$wexAccount = WexAccount::getModel(['id'=>$params['accountId']])
				and
				$wexAccount->user_id == $user->id
			)
			{
				if(time() - $wexAccount->date_check > 60)
				{
					if(YandexPay::startUpdateHistory(0, $wexAccount->id))
					{
						$this->success('платежи обновлены');
						$this->redirect('manager/yandexPay');
					}
					else
						$this->error('ошибка обновления');
				}
				else
					$this->error('обновление доступно не чаще чем раз в минуту');

			}
			else
				$this->error('неверный аккаунт');
		}
		else
		{
			if($session['yandexStats'])
				$interval = $session['yandexStats'];
			else
			{
				$interval = [
					'dateStart' => date('d.m.Y H:i', Tools::startOfDay(time())),
					'dateEnd' => date('d.m.Y H:i', time()+24*3600),
				];

				$session['yandexStats'] = $interval;
			}
		}

		$userId = (!$this->isManager()) ? 0 : $user->id;
		$clientId = (!$this->isManager()) ? $user->client_id : 0;


		$models = YandexPay::getModels(
			strtotime($interval['dateStart']),
			strtotime($interval['dateEnd']),
			$userId,
			$clientId,
			false
		);

		$wexModels = TransactionWex::getModels(
			strtotime($interval['dateStart']),
			strtotime($interval['dateEnd']),
			$userId,
			$clientId
		);

		$this->render('yandexPay', [
			'models'=>$models,
			'statsYandex'=>YandexPay::getStats($models),
			'statsWex'=>TransactionWex::getStats($wexModels),
			'params'=>$params,
			'interval'=>$interval,
			'payUrl' => $payUrl,
			'wexAccount' => $user->wexAccount,
		]);
	}

	public function actionYandexPayHistory()
	{
		if(!$this->isManager() and !$this->isFinansist() and !$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$session = &Yii::app()->session;
		$request = &Yii::app()->request;

		$user = User::getUser();
		$params = $request->getPost('params');

		$interval = isset($session['yandexStats']) ? $session['yandexStats'] : [];

		if($params['dateStart'] and $params['dateEnd'])
		{
			$interval = $params;

			$session['yandexStats'] = $interval;
			$this->redirect('manager/yandexPayHistory');
		}
		else
		{
			if($session['yandexStats'])
				$interval = $session['yandexStats'];
			else
			{
				$interval = [
					'dateStart' => date('d.m.Y H:i', Tools::startOfDay(time())),
					'dateEnd' => date('d.m.Y H:i', time()+24*3600),
				];

				$session['yandexStats'] = $interval;
			}
		}

		$userId = (!$this->isManager()) ? 0 : $user->id;
		$clientId = (!$this->isManager()) ? $user->client_id : 0;


		$models = TransactionWex::getModels(
			strtotime($interval['dateStart']),
			strtotime($interval['dateEnd']),
			$userId,
			$clientId
		);

		$this->render('yandexPayHistory', [
			'models'=>$models,
			'params'=>$params,
			'wexAccount' => $user->wexAccount,
			'interval'=>$interval,
			'stats'=>TransactionWex::getStats($models),
		]);
	}

	public function actionQiwiPay()
	{
		if(!$this->isManager() and !$this->isFinansist() and !$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$session = &Yii::app()->session;
		$request = &Yii::app()->request;

		$user = User::getUser();
		$params = $request->getPost('params');

		$interval = isset($session['qiwiStats']) ? $session['qiwiStats'] : [];

		$payParams = [];

		if($request->getPost('pay'))
		{
			$reservePayParams = QiwiPay::getPayUrlRequest($user->id, $params['amount']);

			if($reservePayParams)
			{
				$this->success('Запрос принят, ожидайте создания заявки');
				$this->redirect('manager/qiwiPay');
			}
			else
				$this->error(QiwiPay::$lastError);
		}
		elseif($request->getPost('check'))
		{
			if(!QiwiPay::mark($params['id'], $user->id, QiwiPay::MARK_CHECKED))
				$this->error(QiwiPay::$lastError);

			$this->redirect('manager/qiwiPay');
		}
		elseif($request->getPost('cancel'))
		{
			if(!QiwiPay::mark($params['id'], $user->id, QiwiPay::MARK_UNCHECKED))
				$this->error(QiwiPay::$lastError);

			$this->redirect('manager/qiwiPay');
		}
		elseif($request->getPost('getTransactionStatus'))
		{
			if(QiwiPay::getTransactionStatus($params['id']))
				$this->success(' платеж принят ');

			$this->redirect('manager/qiwiPay');
		}
		elseif($params['dateStart'] and $params['dateEnd'])
		{
			$interval = $params;

			$session['qiwiStats'] = $interval;
			$this->redirect('manager/qiwiPay');
		}
		elseif($_POST['updateHistory'])
		{
			if(
				$payeerAccount = PayeerAccount::getModel(['id'=>$params['accountId']])
				and
				$payeerAccount->user_id == $user->id
			)
			{
				if(time() - $payeerAccount->date_check > 60)
				{
					if(QiwiPay::startUpdateHistory(0, $payeerAccount->id))
					{
						$this->success('платежи обновлены');
						$this->redirect('manager/qiwiPay');
					}
					else
						$this->error('ошибка обновления');
				}
				else
					$this->error('обновление доступно не чаще чем раз в минуту');

			}
			else
				$this->error('неверный аккаунт');
		}
		else
		{
			if($session['qiwiStats'])
				$interval = $session['qiwiStats'];
			else
			{
				$interval = [
					'dateStart' => date('d.m.Y H:i', Tools::startOfDay(time())),
					'dateEnd' => date('d.m.Y H:i', time()+24*3600),
				];

				$session['qiwiStats'] = $interval;
			}
		}

		$userId = (!$this->isManager()) ? 0 : $user->id;
		$clientId = (!$this->isManager()) ? $user->client_id : 0;


		$models = QiwiPay::getModels(
			strtotime($interval['dateStart']),
			strtotime($interval['dateEnd']),
			$userId,
			$clientId,
			false
		);

		$this->render('qiwiPay', [
			'models'=>$models,
			'statsQiwi'=>QiwiPay::getStats($models),
			'params'=>$params,
			'interval'=>$interval,
			'payParams' => $payParams,
			'payeerAccount' => $user->payeerAccount,
		]);
	}

	public function actionQiwiPayHistory()
	{
		die('');
		if(!$this->isManager() and !$this->isFinansist() and !$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$session = &Yii::app()->session;
		$request = &Yii::app()->request;

		$user = User::getUser();
		$params = $request->getPost('params');

		$interval = isset($session['yandexStats']) ? $session['yandexStats'] : [];

		if($params['dateStart'] and $params['dateEnd'])
		{
			$interval = $params;

			$session['yandexStats'] = $interval;
			$this->redirect('manager/yandexPayHistory');
		}
		else
		{
			if($session['yandexStats'])
				$interval = $session['yandexStats'];
			else
			{
				$interval = [
					'dateStart' => date('d.m.Y H:i', Tools::startOfDay(time())),
					'dateEnd' => date('d.m.Y H:i', time()+24*3600),
				];

				$session['yandexStats'] = $interval;
			}
		}

		$userId = (!$this->isManager()) ? 0 : $user->id;
		$clientId = (!$this->isManager()) ? $user->client_id : 0;


		$models = TransactionWex::getModels(
			strtotime($interval['dateStart']),
			strtotime($interval['dateEnd']),
			$userId,
			$clientId
		);

		$this->render('yandexPayHistory', [
			'models'=>$models,
			'params'=>$params,
			'wexAccount' => $user->wexAccount,
			'interval'=>$interval,
			'stats'=>TransactionWex::getStats($models),
		]);
	}

	public function actionNextQiwiPay()
	{
		if(!$this->isManager() and !$this->isFinansist() and !$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$session = &Yii::app()->session;
		$request = &Yii::app()->request;

		$user = User::getUser();
		$params = $request->getPost('params');

		$interval = isset($session['qiwiStats']) ? $session['qiwiStats'] : [];

		$payParams = [];

		if($request->getPost('pay'))
		{
			if($payParams = NextQiwiPay::getPayParams($user->id, $params['amount']))
			{
				$this->success('Реквизиты получены');
			}
			else
				$this->error(NextQiwiPay::$lastError);
		}
		elseif($request->getPost('check'))
		{
			if(!NextQiwiPay::mark($params['id'], $user->id, QiwiPay::MARK_CHECKED))
				$this->error(QiwiPay::$lastError);

			$this->redirect('manager/NextQiwiPay');
		}
		elseif($request->getPost('cancel'))
		{
			if(!NextQiwiPay::mark($params['id'], $user->id, QiwiPay::MARK_UNCHECKED))
				$this->error(QiwiPay::$lastError);

			$this->redirect('manager/NextQiwiPay');
		}
		elseif($request->getPost('getTransactionStatus'))
		{
			if(NextQiwiPay::getTransactionStatus($params['id']))
				$this->success(' платеж принят ');

			$this->redirect('manager/NextQiwiPay');
		}
		elseif($params['dateStart'] and $params['dateEnd'])
		{
			$interval = $params;

			$session['qiwiStats'] = $interval;
			$this->redirect('manager/NextQiwiPay');
		}
		elseif($_POST['updateHistory'])
		{
			if(
				$payeerAccount = PayeerAccount::getModel(['id'=>$params['accountId']])
				and
				$payeerAccount->user_id == $user->id
			)
			{
				if(time() - $payeerAccount->date_check > 60)
				{
					if(QiwiPay::startUpdateHistory(0, $payeerAccount->id))
					{
						$this->success('платежи обновлены');
						$this->redirect('manager/NextQiwiPay');
					}
					else
						$this->error('ошибка обновления');
				}
				else
					$this->error('обновление доступно не чаще чем раз в минуту');

			}
			else
				$this->error('неверный аккаунт');
		}
		else
		{
			if($session['qiwiStats'])
				$interval = $session['qiwiStats'];
			else
			{
				$interval = [
					'dateStart' => date('d.m.Y H:i', Tools::startOfDay(time())),
					'dateEnd' => date('d.m.Y H:i', time()+24*3600),
				];

				$session['qiwiStats'] = $interval;
			}
		}

		$userId = (!$this->isManager()) ? 0 : $user->id;
		$clientId = (!$this->isManager()) ? $user->client_id : 0;


		$models = NextQiwiPay::getModels(
			strtotime($interval['dateStart']),
			strtotime($interval['dateEnd']),
			$userId,
			$clientId,
			false
		);



		$this->render('nextQiwiPay', [
			'models'=>$models,
			'statsQiwi'=>NextQiwiPay::getStats($models),
			'params'=>$params,
			'interval'=>$interval,
			'payParams' => $payParams,
			'payeerAccount' => $user->payeerAccount,
		]);
	}

	public function actionNewYandexPay()
	{
		if(!$this->isManager() and !$this->isFinansist() and !$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$session = &Yii::app()->session;
		$request = &Yii::app()->request;


		$user = User::getUser();
		$params = $request->getPost('params');

		$interval = isset($session['yandexStats']) ? $session['yandexStats'] : [];

		$payUrl = '';

		if($request->getPost('pay'))
		{
			$client = $user->client;

			$payUrl = NewYandexPay::getPayUrl($user->id, $params['amount']);

			if(!$payUrl)
				$this->error(NewYandexPay::$lastError);
		}
		elseif($request->getPost('check'))
		{
			if(!NewYandexPay::mark($params['id'], $user->id, NewYandexPay::MARK_CHECKED))
				$this->error(NewYandexPay::$lastError);

			$this->redirect('manager/newYandexPay');
		}
		elseif($request->getPost('cancel'))
		{
			if(!NewYandexPay::mark($params['id'], $user->id, NewYandexPay::MARK_UNCHECKED))
				$this->error(NewYandexPay::$lastError);

			$this->redirect('manager/newYandexPay');
		}
		elseif($params['dateStart'] and $params['dateEnd'])
		{
			$interval = $params;


			$session['yandexStats'] = $interval;
			$this->redirect('manager/newYandexPay');
		}
		else
		{
			if($session['yandexStats'])
				$interval = $session['yandexStats'];
			else
			{
				$interval = [
					'dateStart' => date('d.m.Y H:i', Tools::startOfDay(time())),
					'dateEnd' => date('d.m.Y H:i', time()+24*3600),
				];

				$session['yandexStats'] = $interval;
			}
		}

		$userId = (!$this->isManager()) ? 0 : $user->id;
		$clientId = (!$this->isManager()) ? $user->client_id : 0;


		if($_POST['search'])
		{
			$searchStr = trim(strip_tags($_POST['searchStr']));

			$conditionComment = "`comment`LIKE '%" . $searchStr . "' and `client_id`=".$user->client_id;
			$conditionUniqueId = "`unique_id`LIKE '%" . $searchStr . "' and `client_id`=".$user->client_id;


			if($models = NewYandexPay::model()->findAll($conditionComment)
				or $models = NewYandexPay::model()->findAll($conditionUniqueId)
			)
			{
				$this->render('newYandexPay', [
					'models'=>$models,
					'statsYandex'=>NewYandexPay::getStats($models),
					'params'=>$params,
					'interval'=>$interval,
					'payUrl' => $payUrl,
				]);
			}
			else
				$models = [];
		}
		else
		{

			if($user->client->checkRule('pagination'))
			{
				$newYadArr = NewYandexPay::getModelsForPagination(
					strtotime($interval['dateStart']),
					strtotime($interval['dateEnd']),
					$userId,
					$clientId,
					false
				);

				$models = $newYadArr['models'];
				$pages = $newYadArr['pages'];
			}
			else
			{
				$models = NewYandexPay::getModels(
					strtotime($interval['dateStart']),
					strtotime($interval['dateEnd']),
					$userId,
					$clientId,
					false
				);

				$pages = [];
			}

		}

		$this->render('newYandexPay', [
			'models'=>$models,
			'statsYandex'=>NewYandexPay::getStatsByInterval(strtotime($interval['dateStart']),
				strtotime($interval['dateEnd']), $userId, $clientId),
			'params'=>$params,
			'interval'=>$interval,
			'payUrl' => $payUrl,
			'pages' => $pages,
			'userId' => $user->id,
		]);
	}

	/**
	 * выводы StoreApi
	 */
	public function actionStoreApiWithdraw()
	{
		$user = User::getUser();
		$cfg = cfg('storeApi');

		if(!$user->store)
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];

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
			$this->redirect('manager/storeApiWithdraw');
		elseif($_POST['setBtcAddress'] and $user->store)
		{

			$store = $user->store;

			$store->withdraw_wallet = trim($params['btc_address']);
			$store->url_result = trim($params['url_result']);
			$store->url_return = trim($params['url_return']);

			if($store->save())
			{
				$this->success('настройки сохранены');
				$this->redirect('manager/storeApiWithdraw');
			}
			else
				$this->error('ошибка сохранения: '.User::$lastError);
		}

		$this->render('storeApiWithdraw', array(
			'models'=>StoreApiWithdraw::getListModels($filter),
			'stats'=>StoreApiWithdraw::$someData['stats'],
			'filter'=>$filter,
			'params'=>$params,
			'user'=>$user,
		));
	}

	/**
	 * @param $imageId
	 * редактирование координат смс кода и кнопки на скрине банка
	 */
	public function actionImagePosition($imageId)
	{
		if(!$this->isManager() and !$this->isFinansist() and !$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$model = ImagePosition::model()->findByPk($imageId);

		$params = $_POST['params'];

		if($_POST['save'])
		{
			if(ImagePosition::setPosition($params))
			{
				$this->success('Координаты сохранены');
				$this->redirect('manager/imagePosition', ['imageId'=>$imageId]);
			}
			else
				$this->error('Ошибка сохранения координат');
		}

		$this->render('image_position',[
			'model' => $model,
		]);
	}

	/**
	 * список скриншотов банка, при появлении нового скрина воспроизводится звук
	 */
	public function actionImageList($imageId = 0, $delete = false)
	{
		if(!$this->isManager() and !$this->isFinansist() and !$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$models = ImagePosition::getModels();

		if(Yii::app()->request->isAjaxRequest)
		{
			if(isset($_POST['startUpdate']))
				echo('playSound');

			Yii::app()->end();
		}

		if($delete)
		{
			ImagePosition::deleteItem($imageId);
			$this->redirect('manager/imageList');
		}

		$this->render('image_list', [
			'models'=>$models,
		]);
	}

	/**
	 * список скриншотов банка, при появлении нового скрина воспроизводится звук
	 */
	public function actionTelegramNotification()
	{
		if(!$this->isManager() and !$this->isFinansist() and !$this->isAdmin())
			$this->redirect(cfg('index_page'));

//		$models = ImagePosition::getModels();
//
//		if(Yii::app()->request->isAjaxRequest)
//		{
//			if(isset($_POST['startUpdate']))
//				echo('playSound');
//
//			Yii::app()->end();
//		}
//
//		if($delete)
//		{
//			ImagePosition::deleteItem($imageId);
//			$this->redirect('manager/imageList');
//		}

		$this->render('image_list', [
			//'models'=>$models,
		]);
	}



}