<?php
/**
 * контроллер для записей таблицы Account
 * hello git
 */
class AccountController extends Controller
{
	public $defaultAction = 'list';

	public function actionList($login=false, $page=false)
	{
		if (!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];

		$condition = '';

		if($_POST['search'])
		{
			$_POST['search'] = trim(strip_tags($_POST['search']), '+');

			$search = trim(strip_tags($_POST['search']));

			if(strlen($search)<=5)
				$condition = "`id`= '" . $search . "'";
			else
				$condition = "`login`LIKE '%" . $search . "'";

			if($findModel = Account::model()->find($condition))
				$this->redirect('account/list', ['login'=>trim($findModel->login, '+')]);
		}
		elseif($_POST['clearCookie'])
		{
			if ($account = Account::model()->findByPk($params['accountId']))
			{
				if (QiwiBot::clearCookie($account->login))
					$this->success('куки аккаунта ' . $account->login . ' очищены');

				$this->redirect('account/list', array('login'=>trim($account->login, '+')));
			}
		}
		elseif($_POST['pushMoney'])
		{
			//пометить кошелек специальной ошибкой, кото
			if($account = Account::model()->findByPk($params['accountId']))
			{

				$account::model()->updateByPk($account->id, array(
						'error'=>'push_money',
				));

				$this->success('средства с кошелька '.$account->login.' будут выведены в стандартном порядке');
			}
		}
		elseif($_POST['makeCritical'])
		{
			//пометить кошелек специальной ошибкой, кото
			if($account = Account::modelByPk($params['accountId']))
			{

				if($account->makeCritical())
				{
					$this->success('кошелек '.$account->login.' теперь будет сливать без транзитов');
					$this->redirect('account/list', ['login'=>trim($account->login, '+')]);
				}
				else
					$this->error(Account::$lastError);
			}
			else
				$this->error('кошелек не найден');
		}
		elseif($_POST['setProxy'])
		{
			if($account = Account::model()->findByPk($params['accountId']))
			{
				if($account->setProxy($_POST['proxy'][$account->id]))
					$this->success('прокси заменен');

				$this->redirect('account/list', array('login'=>trim($account->login, '+')));
			}
			else
				$this->error('кошелек не найден');
		}
		else
		{
			if($login)
			{
				$login = trim(strip_tags($login), '+');
				$condition = "`login` LIKE '%$login'";
			}

		}


		$criteria = new CDbCriteria();
		$criteria->condition = $condition;
		$criteria->order = "`balance` desc, `error` ASC,  `date_used` ASC, `date_check` desc";

		//счетчики
		//$info = Account::getInfo($criteria->condition);

		$count = Account::model()->count('');

		$pages=new CPagination($count);
		$pages->pageSize = cfg('page_size');
		$pages->applyLimit($criteria);

		$models = Account::model()->findAll($criteria);

		$this->render('list', array(
				'models'=>$models,
				'params'=>$params,
				//'info'=>$info,
				'pages'=>$pages,
				'searchStr'=>$_POST['search'],
		));
	}

	/**
	 */
	public function actionSecurity($id, $sms=false)
	{
		if(!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		session_write_close();

		if($model = Account::model()->findByPk($id))
		{
			/*
			todo: сделать выход в предыдущем шаге
			//логинимся
			$bot = new QiwiBot($model->login, $model->pass);

			if(!$bot->logOut())
				die('ошибка выхода из аккаунта');
			*/

			$bot = $model->bot;
			$bot->pause = 0;

			if(!$bot->error)
			{
				$securityRes = $bot->hasLockedSecurity();

				if($securityRes===false)
                    $this->success('все в порядке');
                elseif($securityRes===true)
                    $this->error('включена смс');
                else
                    $this->error('ошибка проверки смс');
			}
			else
				die('error: '.$bot->errror);
		}



		$this->render('security', array(

		));
	}




	/**
	 * история платежей за последний месяц
	 */
	public function actionHistory($id)
	{
		if(!$this->isAdmin() and !$this->isModer())
			$this->redirect(cfg('index_page'));

		if($account = Account::model()->findByPk($id))
		{
			if($this->isModer() and $account->type!=Account::TYPE_OUT)
				die('wrong type of wallet');

			$models  = $account->transactions;
		}
		$this->render('history', array(
				'account'=>$account,
				'models'=>$models,
		));
	}

	/**
	 * перенаправить на нужное действие Qiwi-контроллера
	 */
	public function actionRepair($id)
	{
		die('old');

		if(!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		if($model = Account::model()->findByPk($id))
		{
			if($model->error==Account::ERROR_SMS)
			{
				$this->redirect('qiwi/security', array(
						'id'=>$id,
				));
			}
			elseif($model->error=='identify_anonim')
			{
				$this->redirect('qiwi/identify', array(
						'id'=>$id,
				));
			}
			else
			{
				$this->error('ошибка: '.$model->error.' не опознана');
			}
		}
		else
			$this->error('аккаунт не найден');

		$this->render('repair');
	}

	/**
	 * полная проверка аккаунта
	 */
	public function actionFullCheck($id)
	{
		if(!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		session_write_close();

		if($model = Account::model()->findByPk($id))
		{
			if($model->error or YII_DEBUG)
			{
				if($model->fullCheck())
				{
					$this->success('кошелек проверен');
				}
				else
				{
					$this->error('ошибка проверки: '.$model::$lastError);
				}
			}
			else
				//вдруг уже чтото работает с этим акком
				$this->error('в аккаунте нет ошибки или не включен Тестовый режим');
		}
		else
			$this->error('аккаунт не найден');

		$this->render('full_check', array(
				'model'=>$model,
		));
	}

	/*
	 * получить фио владельца
	 * (не доделано)
	 */
	public function actionInfo($id)
	{
		if(!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$model = Account::model()->findByPk($id);

		if(!$model)
			die('не найден '.$id);

		$bot = $model->bot;
		$bot->pause = 0;

		if(!$bot->error)
		{
			$info = $bot->info();
			print_r($info);
		}
		else
			die($bot->error);
	}

    public function actionUnban($id)
    {
        if(!$this->isAdmin())
            $this->redirect(cfg('index_page'));

		die('action disabled');

        $model = Account::model()->findByPk($id);

        if(!$model)
            die('не найден '.$id);

        if($model::model()->updateByPk($model->id, array(
            'error'=>'',
            'date_out_of_limit'=>0,
            'date_used'=>0,
            'check_priority'=>1,
        )))
        {
            die('кошелек '.$model->login.' разбанен');
            //$this->redirect('manager/accountList');
        }
        else
            die('ошибка обновления');
    }

	public function actionClearError($id)
	{
		if(!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$model = Account::model()->findByPk($id);

		if(!$model)
			die('не найден '.$id);

		if($model::model()->updateByPk($model->id, array(
			'error'=>'',
		)))
		{
			die('кошелек '.$model->login.': ошибка стерта');
			//$this->redirect('manager/accountList');
		}
		else
			die('ошибка обновления');
	}

	public function actionHistoryAdmin($id, $dayCount=30, $nude=false)
	{
		/**
		 * @var $bot QiwiBot;
		 */

		if(!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		session_write_close();

		if($model = Account::model()->findByPk($id))
		{

			/**
			 * @var Account $model
			 */

			/*
			print_r($bot);
			var_dump($bot->error);
			var_dump($bot->errorCode);
			die;
			*/

			//если мобильный то обязательно редирект
			if(($model->api_token) or $model->mobile_id)
				$this->redirect('account/historyAdminApi', ['id'=>$id, 'dayCount'=>$dayCount]);


			if(in_array($model->client_id, [16, 17]))
				$bot = $model->botTest;
			else
				$bot = $model->bot;

			/**
			 * @var QiwiBot|QiwiBotTest $bot
			 */

			$balanceKzt = 0;

			if($bot)
			{
				$balance = $bot->getBalance();

				if($balance === false)
				{
					die('error balance: '.$bot->error);
				}

				if(in_array($model->client_id, [16, 17]))
				{
					$balanceKzt = $bot->getBalance(QiwiBot::CURRENCY_KZT);

					if($balanceKzt!==false)
					{
						$model->updateBalanceKzt($balanceKzt);

						if($balanceKzt >= cfg('kztTestMinBalance'))
							$model->setPriority(Account::PRIORITY_NOW);
					}
					else
						$this->error('error kzt balance: '.$bot->error);

				}

				if($balance!==false)
					$model->updateBalance($balance);
				else
					$this->error($bot->error);


				if(in_array($model->client_id, [16, 17]))
					$transactions = $bot->getLastPayments($dayCount, true);
				else
					$transactions = $bot->getLastPayments($dayCount);

				if($nude)
				{
					die($bot->lastContent);
				}

				if($transactions!==false)
				{
					//важно не обновлять date_check чтобы не спутать стату по забанам
					if(
						in_array($model->error, array('', Account::ERROR_LIMIT_OUT, Account::ERROR_RAT, 'old','check_wait'))!==false
						and $model->comment != 'отключен админом'
						and $dayCount <= 300
					)
					{

						$model->updateTransactions($transactions, true);
						Account::model()->updateByPk($model->id, array('date_check'=>time()));
					}
					else
					{
						if($dayCount > 30 and !$model->error)
							$this->error('платежи не обновлены: не обновляем старые платежи > 30 дней');
						elseif($model->error)
							$this->error('платежи не обновлены из-за ошибки на кошельке');
						else
							$this->error('платежи не обновлены');
					}

				}
				else
					die('error getLastPayments: '.$bot->error.$bot->sender->info['proxy'][0].$bot->sender->info['header'][0]);

				//статистика по платежам
				$stats = array(
					'all_amount'=>0,//общая сумма платежей
					'all_count'=>0,//всего успешных операций +  в ожидании
					'in_amount'=>0,//сумма входящих
					'in_count'=>0,//количество входящих
					'out_amount'=>0,//сумма исходящих
					'out_count'=>0,//количество исходящих
					'today_amount'=>0,//входящие за сегодня
					'today_amount_out'=>0,//исходящие за сегодня
					'commission_amount'=>0,//комиссия за сегодня
					'today_wallets_count'=>$model->wallets_count,//уникальные кошельки за  сегодня
					'month_amount'=>0,//входящие за сегодня
					'month_amount_out'=>0,//исходящие за сегодня
					'yesterday_wallets_count'=>0,//уникальные кошельки за  вчера
				);

				$todayStart = strtotime(date('d.m.Y'));
				$todayEnd = $todayStart + 3600*24;

				$yesterdayStart = $todayStart - 3600*24;
				$yesterdayEnd = $todayStart;
				$yesterdayWallets = [];

				$monthStart = strtotime(date('01.m.Y'));

				foreach($transactions as $trans)
				{
					if($trans['currency'] and $trans['currency'] !== QiwiBot::CURRENCY_RUB)
						continue;

					if($trans['amount']>0 and ($trans['status']=='success' or $trans['status']=='wait'))
					{
						$stats['all_amount'] += $trans['amount'];
						$stats['all_count'] ++;

						if($trans['type']=='in')
						{
							$stats['in_amount'] += $trans['amount'];
							$stats['in_count'] ++;

							if($trans['timestamp'] >= $todayStart and $trans['timestamp'] < $todayEnd)
								$stats['today_amount'] += $trans['amount'];

							if($trans['timestamp'] >= $monthStart)
								$stats['month_amount'] += $trans['amount'];
						}
						elseif($trans['type']=='out')
						{
							$stats['out_amount'] += $trans['amount'];
							$stats['out_count'] ++;

							$stats['commission_amount'] += $trans['commission'];

							if($trans['timestamp'] >= $todayStart and $trans['timestamp'] < $todayEnd)
								$stats['today_amount_out'] += $trans['amount'];

							if($trans['timestamp'] >= $monthStart)
								$stats['month_amount_out'] += $trans['amount'];
						}

						if($trans['timestamp'] >= $yesterdayStart and $trans['timestamp'] < $yesterdayEnd)
							$yesterdayWallets[] = $trans['wallet'];
					}

				}

				$stats['yesterday_wallets_count'] = count(array_unique($yesterdayWallets));


				if(!$bot->lastContent)
					$bot->lastContent = $bot->sender->info['header'][0].'  '.$bot->sender->info['proxy'][0];

				//страница настроек безопасности
				//$bot->isSmsPaymentEnabled();
				//die($bot->lastContent);


				$this->render('historyAdmin', array(
					'model'=>$model,
					'balance'=>$balance,
					'balanceKzt'=>$balanceKzt,
					'transactions'=>$transactions,
					'status'=>$bot->getStatus(),
					'proxy'=>$bot->proxy,
					'browser'=>$bot->browser,
					'isEmail'=>$bot->isEmailLinked(),
					'smsEnabled'=>$bot->hasLockedSecurity(),
					'outIp'=>$bot->getMyIp(),
					'smsPaymentEnabled'=>$bot->isSmsPaymentEnabled(),
					'stats'=>$stats,
				));
			}
			else
			{
				die('error: '.$model->botError);
				//content: '.$model->botContent

				if($bot->errorCode === QiwiBot::ERROR_BAN and cfg('with_bans'))
				{
					$model::model()->updateByPk($model->id, array('error'=>'ban'));
					toLog('забанен '.$model->login);
				}
				elseif($bot->errorCode === QiwiBot::ERROR_PASSWORD_EXPIRED)
				{
					Account::model()->updateByPk($model->id, array('error'=>Account::ERROR_PASSWORD_EXPIRED));
					toLog('(' . $model->login . ') : ERROR_PASSWORD_EXPIRED ' . $bot->error . $bot->lastContent, false, true);
				}

				echo('error:'.$bot->error);
				//echo $bot->lastContent;
				//print_r($bot->sender->info);
			}

		}
	}

	public function actionAutoAdd()
	{
		if(!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];

		if($params['autoAddSwitch'])
		{
			if(!$params['turnOnAutoAdd'])
			{
				config('autoAddEnabled', 0);
				$this->error('Автодобавление отключено');
				$this->redirect('account/autoAdd');
			}
			else
			{
				config('autoAddEnabled', 1);
				$this->success('Автодобавление включено');
				$this->redirect('account/autoAdd');
			}
		}
		elseif($_POST['edit'])
		{
			$content = Account::editAutoAddFile($params['content']);

			if(!Account::$lastError)
			{
				$this->success('сохранено');
				$this->redirect('account/autoAdd');
			}
			else
				$this->error(Account::$lastError);
		}
		else
			$content = Account::editAutoAddFile();


		$this->render('autoAdd', array(
			'content'=>$content,
		));
	}


	public function actionHistoryAdminApi($id, $dayCount=30)
	{
		if(!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		session_write_close();

		if($model = Account::modelByPk($id))
		{
			if(!$model->api_token)
				$this->redirect('account/historyAdmin', ['id'=>$id, 'dayCount'=>$dayCount]);

			if($api = $model->api)
			{
				$balance = $api->getBalance();

				if($balance === false)
				{
					toLogError('error balance api2: (' . $model->login . ') ' . $api->error);

					if($api->errorCode === QiwiApi::ERROR_BAN )
					{
						if($model->error != Account::ERROR_BAN)
						{
							Account::model()->updateByPk($this->id, [
								'error'=>Account::ERROR_BAN,
							]);

							toLogError('забанен '.$model->login.': api ответ: 401');
						}
						else
							die('error: '.$api->error);
					}

					die('error balance: '.$api->error);
				}

				//обновить баланас
				$model->updateBalance($balance);

				//обновить платежи
				$timestampFrom = time() - 3600*24*$dayCount;
				$transactions = $api->getHistory($timestampFrom);

				if($transactions === false)
				{
					die('ошибка получения истории '.$model->login.' : '.$api->error);
				}

				if(!$model->updateTransactions($transactions))
				{
					die('ошибка обновления платежей: '.Account::$lastError);
				}

				$model->updateDateCheck(time());

				//апи отдает только 50: грузим все из базы
				$transactionModels = $model->getAllTransactions($timestampFrom);

				//приводим к обычному виду
				$transactions = [];
				foreach($transactionModels as $trModel)
				{
					$transactions[] = [
						'id' => $trModel->qiwi_id,
						'type' => $trModel->type,
						'wallet' => $trModel->wallet,
						'amount' => $trModel->amount,
						'commission' => $trModel->commission,
						'currency' => QiwiApi::CURRENCY_RUB,
						'comment' => $trModel->comment,
						'status' => $trModel->status,
						'timestamp' => $trModel->date_add,
						'date' => $trModel->dateAddStr,
						'error' => $trModel->error,
					];
				}

				//статистика по платежам
				$stats = array(
					'all_amount'=>0,//общая сумма платежей
					'all_count'=>0,//всего успешных операций +  в ожидании
					'in_amount'=>0,//сумма входящих
					'in_count'=>0,//количество входящих
					'out_amount'=>0,//сумма исходящих
					'out_count'=>0,//количество исходящих
					'today_amount'=>0,//входящие за сегодня
					'today_amount_out'=>0,//исходящие за сегодня
					'commission_amount'=>0,//комиссия за сегодня
					'today_wallets_count'=>$model->wallets_count,//уникальные кошельки за  сегодня
					'month_amount'=>0,//входящие за сегодня
					'month_amount_out'=>0,//исходящие за сегодня
					'yesterday_wallets_count'=>0,//уникальные кошельки за  вчера
				);

				$todayStart = strtotime(date('d.m.Y'));
				$todayEnd = $todayStart + 3600*24;

				$yesterdayStart = $todayStart - 3600*24;
				$yesterdayEnd = $todayStart;
				$yesterdayWallets = [];

				$monthStart = strtotime(date('01.m.Y'));

				foreach($transactions as $trans)
				{
					if($trans['currency'] and $trans['currency'] !== QiwiBot::CURRENCY_RUB)
						continue;

					if($trans['amount']>0 and ($trans['status']=='success' or $trans['status']=='wait'))
					{
						$stats['all_amount'] += $trans['amount'];
						$stats['all_count'] ++;

						if($trans['type']=='in')
						{
							$stats['in_amount'] += $trans['amount'];
							$stats['in_count'] ++;

							if($trans['timestamp'] >= $todayStart and $trans['timestamp'] < $todayEnd)
								$stats['today_amount'] += $trans['amount'];

							if($trans['timestamp'] >= $monthStart)
								$stats['month_amount'] += $trans['amount'];
						}
						elseif($trans['type']=='out')
						{
							$stats['out_amount'] += $trans['amount'];
							$stats['out_count'] ++;

							$stats['commission_amount'] += $trans['commission'];

							if($trans['timestamp'] >= $todayStart and $trans['timestamp'] < $todayEnd)
								$stats['today_amount_out'] += $trans['amount'];

							if($trans['timestamp'] >= $monthStart)
								$stats['month_amount_out'] += $trans['amount'];
						}

						if($trans['timestamp'] >= $yesterdayStart and $trans['timestamp'] < $yesterdayEnd)
							$yesterdayWallets[] = $trans['wallet'];
					}
				}

				$stats['yesterday_wallets_count'] = count(array_unique($yesterdayWallets));

				$this->render('historyAdmin', array(
					'model'=>$model,
					'balance'=>$balance,
					//'balanceKzt'=>$balanceKzt,
					'transactions'=>$transactions,
					'status'=>'half',
					'proxy'=>$model->proxy,
					'isEmail'=>$model->is_email,
					'stats'=>$stats,
				));

			}
			else
				die('error: '.$model::$lastError);
		}

	}

	/**
	 * упрощенное добавление коша для глобалов
	 */
	public function actionAddAccountByGlobal()
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		session_write_close();

		$params = $_POST['params'];

		if($_POST['addAccount'])
		{

			$params['phones'] = $params['login'].' '.$params['pass'].' '.$params['token'].' '.$params['proxy'];

			$params['type'] = 'in';

			$count = Account::addMany($params);

			$this->success('добавлено: ' . $count . ' '.$params['type']. ' аккаунтов to '.Client::model()->findByPk($params['clientId'])->name.' (group '.$params['groupId'].')');

			if(Account::$lastError)
				$this->error('error: '.Account::$lastError);
			else
			{
				/**
				 * @var Account $model
				 */
				$model = Account::model()->findByAttributes(['login'=>$params['login']]);
				if($model)
				{
					echo('find model');
					if($params['status'] !== '')
						$model->status = $params['status'];
					if($params['status'] == Account::STATUS_FULL)
					{
						$model->limit_in = 7000000;
						$model->limit_out = 7000000;
					}
					$model->save();
				}

				$this->redirect('account/addAccountByGlobal');
			}

		}

		$this->render('addAccountByGlobal', [
			'params' => $params,
		]);
	}
}