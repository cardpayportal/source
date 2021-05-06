<?
class ClientController extends Controller
{

	public $defaultAction = 'list';

	/**
	 * список использованных кошельков для манагера
	 */
	public function actionList()
	{
		if (!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];
		$user = User::getUser();
		$pickAccountEnabled = config('pickAccountEnabled');

		if($_POST['add'])
		{
			if(Client::add($params))
			{
				$this->success('add complete, не забыть добавить cron-таски');
				$this->success(Client::$msg);
				$this->redirect('client/list');
			}
			else
				$this->error('error: '.Client::$lastError);
		}
		elseif($_POST['enableGlobalFin'])
		{
			if(Client::enableGlobalFin($params['id'], $user->id))
				$this->success('включен  globalFin на clientId='.$params['id']);
			else
				$this->error('ошибка: '.Client::$lastError);
		}
		elseif($_POST['disableGlobalFin'])
		{
			if(Client::disableGlobalFin($params['id'], $user->id))
				$this->success('отключен  globalFin на clientId='.$params['id']);
			else
				$this->error('ошибка: '.Client::$lastError);
		}
		elseif($_POST['cancelFinOrders'])
		{
			if(Client::cancelFinOrders($params['id'], $user->id))
				$this->success(Client::$someData['msg']);

			if(Client::$lastError)
				$this->error('ошибка: '.Client::$lastError);
		}
		elseif($_POST['resetClient'])
		{
			$result = Client::reset($params['clientId'], $params['confirmPass']);

			$this->success(Client::$msg);

			if($result)
			{
				$this->success('успех');
				$this->redirect('client/list');
			}
			else
				$this->error('ошибка: '.Client::$lastError);
		}
		elseif($_POST['togglePickAccount'])
		{
			if($pickAccountEnabled)
			{
				config('pickAccountEnabled', '');
				$this->success('Выдача кошельков прекращена');
			}
			else
			{
				config('pickAccountEnabled', 'true');
				$this->success('Выдача кошельков включена');
			}

		}
		elseif($_POST['pickAccountsSwitch'])
		{
			$client = Client::modelByPk($params['client_id']);

			Client::switchPickAccounts($params['client_id'], $client->pick_accounts xor 1);

			if(Client::$lastError)
				$this->error(Client::$lastError);
			else
			{
				$this->success(Client::$msg);
				$this->redirect('client/list');
			}
		}
		elseif($_POST['pickAccountsNextQiwiSwitch'])
		{
			$client = Client::modelByPk($params['client_id']);

			Client::switchPickAccountsNextQiwi($params['client_id'], $client->pick_accounts_next_qiwi xor 1);

			if(Client::$lastError)
				$this->error(Client::$lastError);
			else
			{
				$this->success(Client::$msg);
				$this->redirect('client/list');
			}
		}
		elseif($_POST['controlYandexBit'])
		{
			$client = Client::modelByPk($params['client_id']);

			Client::controlYandexBit($params['client_id'], $client->control_yandex_bit xor 1);

			if(Client::$lastError)
				$this->error(Client::$lastError);
			else
			{
				$this->success(Client::$msg);
				$this->redirect('client/list');
			}
		}
		elseif($_POST['disableClient'])
		{
			if($this->isAdmin())
			{
				if(Client::getModel($params['id'])->disable())
				{
					$this->success('клиент отключен <br>'.Client::$msg);
					$this->redirect('client/list');
				}
				else
					$this->error(Client::$lastError.'<br>'.Client::$msg);
			}
		}
		elseif($_POST['enableClient'])
		{
			if($this->isAdmin())
			{
				if(Client::getModel($params['id'])->enable())
				{
					$this->success('клиент задействован <br>'.Client::$msg);
					$this->redirect('client/list');
				}
				else
					$this->error(Client::$lastError.'<br>'.Client::$msg);
			}
		}
		elseif($_POST['calcEnable'])
		{
			if(Client::getModel($params['id'])->calcEnable())
			{
				$this->success('расчеты включены');
				$this->redirect('client/list');
			}
			else
				$this->error(Client::$lastError.'<br>'.Client::$msg);
		}
		elseif($_POST['calcDisable'])
		{
			if(Client::getModel($params['id'])->calcDisable())
			{
				$this->success('расчеты отключены');
				$this->redirect('client/list');
			}
			else
				$this->error(Client::$lastError.'<br>'.Client::$msg);
		}
		elseif($_POST['edit'])
		{
			if(Client::getModel($params['id'])->edit([
				'email'=>$params['email']
			]))
			{
				$this->success('данные сохранены');
				$this->redirect('client/list');
			}
			else
				$this->error(Client::$lastError.'<br>'.Client::$msg);
		}
		elseif($_POST['cancelFinOrdersAll'])
		{
			$result = Client::cancelFinOrdersAll($user->id);

			if($result)
				$this->success('все сливы отменены');

			$this->success(Client::$msg);

			if($result)
				$this->redirect('client/list');

			if(Client::$lastError)
				$this->error('ошибка: '.Client::$lastError);
		}
		elseif($_POST['savePaymentType'])
		{
			$params = $_POST['params'];

			if($client = Client::modelByPk($params['client_id']))
			{
				$client->yandex_payment_type = $params['yandex_payment_type'];
				$client->save();
			}
		}

		$this->render('list', array(
			'models'=>Client::clientList(),
			'params'=>$params,
			'pickAccountEnabled'=>config('pickAccountEnabled'),
		));
	}

	/*
	 * отображает статистику по клиентам и выводит предупреждения
	 *
	 * сколько и каких кошельков осталось
	 * какой общий лимит IN, TRANSIT, OUT  кошельков, куда нужно добавить и сколько
	 * общий баланс кошельков
	 * сколько  кошельков сейчас вработе, сколько использованы
	 * общая сумма за выбранный период
	 */
	public function actionStats()
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];

		if($_POST['addAccounts'])
		{
			Yii::app()->session['cientId'] = $params['clientId'];

			$count = Account::addMany($params);

			$this->success('добавлено: ' . $count . ' '.$params['type']. ' аккаунтов to '.Client::model()->findByPk($params['clientId'])->name.' (group '.$params['groupId'].')');

			if(Account::$lastError)
				$this->error('error: '.Account::$lastError);
			else
				$this->redirect('client/stats');
		}
		elseif($_POST['addAccountsFromTableFields'])
		{
			$params = $_POST['countToAdd'];

			$result = Account::addAccountsFromLimitTable($params);

			if($result)
				$this->success($result);

			if(Account::$lastError)
				$this->error('error: '.Account::$lastError);
			else
				$this->redirect('client/stats');
		}

		$sumOutBalanceWithGroups = Client::getSumOutBalanceWithGroups(null, true);

		$this->render('stats', array(
			'params'=>$params,
			'stats'=>Client::getStatsTest(),
			'groupArr'=>Account::getGroupArr(),
			'lastClientId'=>Yii::app()->session['cientId'],
			'outAmountWithGroups'=>$sumOutBalanceWithGroups,
		));
	}

	/*
	 * способы начисления процентов
	 */
	public function actionCommission()
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];

		if($_POST['save'])
		{
			$count = ClientCommission::editMany($params);

			$this->success('изменено: '.$count.' записей');

			if(ClientCommission::$lastError)
				$this->error(ClientCommission::$lastError);
			else
				$this->redirect('client/commission');
		}
		elseif($_POST['add'])
		{
			if(ClientCommission::add($params))
			{
				$this->success('правило добавлено');
				$this->redirect('client/commission');
			}
			else
				$this->error(ClientCommission::$lastError);
		}

		$models = ClientCommission::getList();

		$this->render('commission', array(
			'models'=>$models,
			'params'=>$params,
		));
	}

	/*
	 * счета к оплате от Fin к GlobalFin
	 */
	public function actionInvoiceList()
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$dateStart = time() - 86400*7;	//неделя

		$models = ClientInvoice::getGfModels();

		$this->render('invoiceList', [
			'models'=>$models,
			'dateStart'=>$dateStart,
		]);
	}

	public function actionWexAccounts($clientId = 0)
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];

		$history = [];

		if($_POST['save'])
		{
			$done = WexAccount::saveMany($params);

			if(!WexAccount::$lastError)
			{
				$this->success('сохранено '.$done.' аккаунтов');
				$this->redirect('client/wexAccounts', ['clientId'=>$clientId]);
			}
			else
				$this->error(WexAccount::$lastError);
		}
		elseif($_POST['updateWexAccount'])
		{
			if($model = WexAccount::getModel(['id'=>$params['accountId']]))
			{
				if($model->updateAccount())
				{
					$this->success('аккаунт '.$model->login.' обновлен');
					$this->redirect('client/wexAccounts', ['clientId'=>$clientId]);
				}
				else
					$this->error('ошибка обновления аккаунта');
			}
			else
				$this->error('аккаунт не найден');
		}
		elseif($_POST['updateWexHistory'])
		{
			$count = WexAccount::updateClientAccounts($params['clientId']);

			if(WexAccount::$lastError)
				$this->error(WexAccount::$lastError);

			$this->success('обновлено '.$count.' аккаунтов');

			if($count > 0)
				$this->redirect('client/wexAccounts', ['clientId'=>$clientId]);
		}
		elseif($_POST['getWexHistory'])
		{
			if($account = WexAccount::getModel(['id'=>$params['accountId']]))
			{
				$hist = $account->getHistory();

				if($hist)
					$history[$account->id] = $hist;
				else
				{
					if($hist === false)
						$this->error('ошибка получения истории');
					elseif(count($hist) == 0)
						$this->error('история пуста');
					else
						$this->error('ошибка');

				}
			}
			else
				$this->error('account not found');
		}

		$cond = "`role` IN ('".User::ROLE_MANAGER."', '".User::ROLE_FINANSIST."')";

		if($clientId > 0)
			$cond .= " AND `client_id`=$clientId";

		$users = User::model()->findAll([
			'condition' => $cond,
			'order' => "`client_id` ASC, `id` DESC",

		]);


		$this->render('wexAccounts', [
			'users' => $users,
			'client' => Client::getModel($clientId),
			'history' => $history,
		]);
	}

	/**
	 * общая статистика платежей яндекс через векс, общая сумма и сумма по манам, клиентам, добавлены действия
	 */
	public function actionYadStat()
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$user = User::getUser();

		$clients = Client::clientList();

		$clientsYadArr = [];

		$totalAmountRu = 0;
		$totalAmountBtc = 0;
		$totalAmountZec = 0;
		$totalAmountUsd = 0;
		$totalAmount = 0;

		if($clients)
		{
			foreach($clients as $key=>$client)
			{
				$cond = "`role` IN ('".User::ROLE_MANAGER."', '".User::ROLE_FINANSIST."')";
				$cond .= " AND `client_id`=$client->id";

				$users = User::model()->findAll([
					'condition' => $cond,
					'order' => "`client_id` ASC, `name` ASC",

				]);

				$amountClientRu = 0;
				$amountClientBtc = 0;
				$amountClientZec = 0;
				$amountClientUsd = 0;
				$amountClient = 0;

				foreach($users as $user)
				{
					if($user->wexAccount)
					{
						$amountClientRu += $user->wexAccount->balance_ru;
						$amountClientBtc += $user->wexAccount->balance_btc;
						$amountClientZec += $user->wexAccount->balance_zec;
						$amountClientUsd += $user->wexAccount->balance_usd;
						$amountClient += $user->wexAccount->balance_total;
						$clientsYadArr[$key]['client'] = $client;
						$clientsYadArr[$key]['users'][] = $user;
					}
				}

				if($clientsYadArr[$key]['client'])
				{
					$clientsYadArr[$key]['clientAmountRu'] = $amountClientRu;
					$totalAmountRu += $amountClientRu;

					$clientsYadArr[$key]['clientAmountBtc'] = $amountClientBtc;
					$totalAmountBtc += $amountClientBtc;

					$clientsYadArr[$key]['clientAmountZec'] = $amountClientZec;
					$totalAmountZec += $amountClientZec;

					$clientsYadArr[$key]['clientAmountUsd'] = $amountClientUsd;
					$totalAmountUsd += $amountClientUsd;

					$clientsYadArr[$key]['clientAmount'] = $amountClient;
					$totalAmount += $amountClient;
				}
			}
		}

		$params = $_POST['params'];
		if($_POST['updateWexAccount'])
		{
			if($model = WexAccount::getModel(['id'=>$params['accountId']]))
			{
				if($model->updateAccount())
				{
					$this->success('аккаунт '.$model->login.' обновлен');
					$this->redirect('client/yadStat');
				}
				else
					$this->error('ошибка обновления аккаунта');
			}
			else
				$this->error('аккаунт не найден');
		}
		elseif($_POST['addAccounts'])
		{
			$count = WexAccount::addMany($params);

			$this->success('добавлено: ' . $count . ' аккаунтов ');

			if(WexAccount::$lastError)
				$this->error('Ошибка: '. WexAccount::$lastError);
			else
				$this->redirect('client/yadStat');
		}
		elseif($_POST['rebootSelenium'])
		{
			$sender = new Sender;
			$sender->followLocation = false;

			$content = $sender->send('http://94.140.125.237/selenium/index.php?key=testtest&method=RebootSelenium');

			if(preg_match('!Перезагружен Selenium!iu', $content))
				$this->success('Перезагружен Selenium');
			else
				$this->error(strip_tags($content));
		}
		elseif($_POST['replaceProxy'])
		{
			if(WexAccount::getNewProxy($params['wexAccountId']))
				$this->success('Прокси сменен');
			else
				$this->error('Ошибка замены прокси');
		}
		elseif($_POST['addNewYandexPayWalletStr'])
		{
			$user = User::getUser();
			if(NewYandexPay::setWalletStr($params['newYandexPayWalletStr'], $user->id))
				$this->success('Кошельки добавлены');
			else
				$this->error('Ошибка добавления кошельков '.NewYandexPay::$lastError);
		}
		elseif($_POST['addInfoProductWalletStr'])
		{
//			prrd($params['newYandexPayInfoProductWalletStr']);
			$user = User::getUser();
			if(NewYandexPay::setWalletStr($params['newYandexPayInfoProductWalletStr'], $user->id, true))
				$this->success('Кошельки добавлены');
			else
				$this->error('Ошибка добавления кошельков '.NewYandexPay::$lastError);
		}
		elseif($_POST['addPersonalYandexWalletCl11'])
		{
			//сохраняет персональный номер яда для кл11
			$user = User::getUser();

			if($user->role !== User::ROLE_ADMIN and !$user->is_wheel)
			{
				self::$lastError = 'может изменять рулевой или админ';
				return false;
			}

			config('personalYandexWalletCl11', trim($_POST['personalYandexWalletCl11']));

			$this->success('Кошелек заменен на номер '.$_POST['personalYandexWalletCl11']);

		}
		elseif($_POST['addPersonalYandexWalletCl13'])
		{
			//сохраняет персональный номер яда для кл13
			$user = User::getUser();

			if($user->role !== User::ROLE_ADMIN and !$user->is_wheel)
			{
				self::$lastError = 'может изменять рулевой или админ';
				return false;
			}

			config('personalYandexWalletCl13', trim($_POST['personalYandexWalletCl13']));

			$this->success('Кошелек заменен на номер '.$_POST['personalYandexWalletCl13']);

		}
		elseif($_POST['addPersonalYandexWalletKr42'])
		{
			//сохраняет персональный номер яда для kr42
			$user = User::getUser();

			if($user->role !== User::ROLE_ADMIN and !$user->is_wheel)
			{
				self::$lastError = 'может изменять рулевой или админ';
				return false;
			}

			config('personalYandexWalletKr42', trim($_POST['personalYandexWalletKr42']));

			$this->success('Кошелек заменен на номер '.$_POST['personalYandexWalletKr42']);

		}
		elseif($_POST['addPersonalYandexWalletKr46'])
		{
			//сохраняет персональный номер яда для kr42
			$user = User::getUser();

			if($user->role !== User::ROLE_ADMIN and !$user->is_wheel)
			{
				self::$lastError = 'может изменять рулевой или админ';
				return false;
			}

			config('personalYandexWalletKr46', trim($_POST['personalYandexWalletKr46']));

			$this->success('Кошелек заменен на номер '.$_POST['personalYandexWalletKr46']);

		}
		elseif($_POST['addPersonalYandexWalletCl19'])
		{
			//сохраняет персональный номер яда для Cl19
			$user = User::getUser();

			if($user->role !== User::ROLE_ADMIN and !$user->is_wheel)
			{
				self::$lastError = 'может изменять рулевой или админ';
				return false;
			}

			config('personalYandexWalletCl19', trim($_POST['personalYandexWalletCl19']));

			$this->success('Кошелек заменен на номер '.$_POST['personalYandexWalletCl19']);

		}

		$walletStr = config('newYandexPayWalletStr');
		$yandexWalletArr = [];

		if($walletStr)
		{
			if(preg_match_all(cfg('regExpAccountAddYandex'), $walletStr, $matches))
			{
				foreach($matches[1] as $key => $number)
					$yandexWalletArr[] = trim($matches[1][$key]);
			}

			$walletStatsArr = [];

			foreach($yandexWalletArr as $wallet)
			{
				$model = NewYandexPay::getStatsInDay($wallet);
				$walletStatsArr[$wallet] = $model;
			}
		}

		$walletInfoProductStr = config('newYandexPayInfoProductWalletStr');
		$yandexWalletInfoProductArr = [];

		if($walletInfoProductStr)
		{
			if(preg_match_all(cfg('regExpAccountAddYandex'), $walletInfoProductStr, $matches))
			{
				foreach($matches[1] as $key => $number)
					$yandexWalletInfoProductArr[] = trim($matches[1][$key]);
			}

			$walletStatsInfoProductArr = [];

			foreach($yandexWalletInfoProductArr as $wallet)
			{
				$model = NewYandexPay::getStatsInDay($wallet);
				$walletStatsInfoProductArr[$wallet] = $model;
			}
		}

		$this->render('yadStat', [
			'clientsYadArr' => $clientsYadArr,
			'totalAmountRu' => $totalAmountRu,
			'totalAmountBtc' => $totalAmountBtc,
			'totalAmountZec' => $totalAmountZec,
			'totalAmountUsd' => $totalAmountUsd,
			'totalAmount' => $totalAmount,
			'countFreeAccounts' => WexAccount::getCountFreeAccounts(),
			'newYandexPayWallet' => config('newYandexPayWallet'),
			'newYandexPayWalletInfoProduct' => config('newYandexPayWalletInfoProduct'),
			'newYandexPayWalletStr' => $walletStr,
			'newYandexPayInfoProductWalletStr' => $walletInfoProductStr,
			'walletStatsArr' => $walletStatsArr,
			'walletStatsInfoProductArr' => $walletStatsInfoProductArr,
		]);
	}

	/**
	 * @param $wexAccountId
	 * @param $user
	 * вывод чистой истории с биржи по приходу яндекса, для сверки транзакций
	 */
	public function actionYandexHistory($wexAccountId, $user)
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$history = [];

		if($account = WexAccount::getModel(['id'=>$wexAccountId]))
		{
			$history = $account->getHistory();

			if($history === false)
				$this->error('ошибка получения истории');
			elseif(count($history) == 0)
				$this->error('история пуста');
		}
		else
			$this->error('account not found');

		$this->render('yandexHistory', [
			'user'=>$user,
			'history' => $history,
		]);
	}

	/**
	 * @param $wexAccountId
	 * @param $user
	 * вывод чистой истории с биржи по всем операциям
	 */
	public function actionYandexHistoryAdmin($wexAccountId, $user, $pageNum = 1)
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$history = [];

		$exchange = 'exchange';
		$out = 'out';
		$error = 'error';

		$params = $_POST['params'];

		if($account = WexAccount::getModel(['id'=>$wexAccountId]))
		{
			//делаем повторную отправку письма с подтверждением вывода или отмену вывода

			if($_POST['cancel'])
			{
				if($account->withdrawControl($params['transactionId'], WexCurlBot::ACTION_CANCEL))
				{
					$this->success('Транзакция успешно отменена id = '.$params['transactionId']);
					//пока убрал тк не видно сообщения после редиректа
					//$this->redirect('client/YandexHistoryAdmin', ['user'=>$user,'wexAccountId'=>$wexAccountId]);
				}
				else
					$this->error('Ошибка отмены транзакции id = '.$params['transactionId']);
			}
			elseif($_POST['resend'])
			{
				if($account->withdrawControl($params['transactionId'], WexCurlBot::ACTION_RESEND))
				{
					$this->success('Письмо отправлено повторно id = '.$params['transactionId']);
					//пока убрал тк не видно сообщения после редиректа
					//$this->redirect('client/YandexHistoryAdmin', ['user'=>$user,'wexAccountId'=>$wexAccountId]);
				}
				else
					$this->error('Ошибка повторной отправки письма');
			}
			elseif($_POST['buyBtcRu'])
			{
				$result = $account->buyBtcRu();
				sleep(1);
				$account->updateAccount();

				if($result)
					$this->success($result);
				else
					$this->error('Ошибка обмена Руб в BTC '. WexCurlBot::$lastError);
			}
			elseif($_POST['withdrawBtc'])
			{
				$result = $account->withdrawBtc($params[$account->user_id]['address']);
				$account->updateAccount();
				if($result)
					$this->success('Создан запрос на вывод BTC '.arr2str($result));
				else
					$this->error('Ошибка создания запроса на отправку BTC '. WexCurlBot::$lastError);
			}
			elseif($_POST['confirmPaymentTutanota'])
			{
				$result = $account->confirmPaymentTutanota();
				$account->updateAccount();
				if($result)
					$this->success('Вывод подтвержден: '.arr2str($result));
				elseif(!Tools::threader('selenium'))
					$this->error('Selenium занят, попробуйте еще раз '. WexCurlBot::$lastError);
				else
					$this->error('Ошибка подтверждения вывода '. WexCurlBot::$lastError);

			}
			elseif($_POST['buyZec'])
			{
				$result = '';
				if($account->balance_ru > 400)
				{
					$result = $account->buyUsdRu();
					sleep(2);
				}

				$result .= ' <br> '.$account->buyZecUsd();
				sleep(1);
				$account->updateAccount();

				if($result)
					$this->success($result);

				if(WexCurlBot::$lastError)
					$this->error('Ошибка обмена в ZEC '. WexCurlBot::$lastError);
			}
			elseif($_POST['withdrawZec'])
			{
				$result = $account->withdrawZec($params[$account->user_id]['address']);
				$account->updateAccount();
				if($result)
					$this->success('Создан запрос на вывод ZEC '.arr2str($result));
				else
					$this->error('Ошибка создания запроса на отправку ZEC '. WexCurlBot::$lastError);

			}
			elseif($_POST['buyUsdt'])
			{
				$result = '';
				if($account->balance_ru > 400)
				{
					$result = $account->buyUsdRu();
					sleep(2);
				}

				$result .= ' <br> '.$account->buyUsdtUsd();
				sleep(1);
				$account->updateAccount();

				if($result)
					$this->success($result);

				if(WexCurlBot::$lastError)
					$this->error('Ошибка обмена в USDT '. WexCurlBot::$lastError);
			}
			elseif($_POST['withdrawUsdt'])
			{
				$result = $account->withdrawUsdt($params[$account->user_id]['address']);
				$account->updateAccount();
				if($result)
					$this->success('Создан запрос на вывод USDT '.arr2str($result));
				else
					$this->error('Ошибка создания запроса на отправку USDT '. WexCurlBot::$lastError);

			}
			elseif($_POST['updateWexAccount'])
			{
				if($account->updateAccount())
				{
					$this->success('аккаунт '.$account->login.' обновлен');
				}
				else
					$this->error('ошибка обновления аккаунта');

			}

			$history = $account->getHistoryAdmin($pageNum);

			if($history === false)
				$this->error('ошибка получения истории');
			elseif(count($history) == 0)
				$this->error('история пуста');

			if($_POST['show'])
			{
				$yandex = $_POST['yandex'];

			}
		}
		else
			$this->error('account not found');



		$this->render('yandexHistoryAdmin', [
			'user'=>$user,
			'history' => $history,
			'wexAccountId' => $wexAccountId,
			'yandex' => $yandex,
			'exchange' => $exchange,
			'out' => $out,
			'error' => $error,
			'pageNum' => $pageNum,
			'account' => $account,
			'params' => $params,
		]);
	}

	/**
	 * @param $userId
	 * @param $wexAccountId
	 *
	 * редактируем пользователя векса в отдельной странице
	 */
	public function actionEditWexAccount($userId, $wexAccountId)
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$wexAccount = WexAccount::model()->findByPk($wexAccountId);
		$user = User::model()->findByPk($userId);

		$params = $_POST['params'];

		if($_POST['save'])
		{
			$done = WexAccount::saveMany($params);

			if(!WexAccount::$lastError)
			{
				WexCurlBot::clearCookie($wexAccount->login);
				$this->success('сохранено, очищены cookie: '.$wexAccount->login);
				$this->redirect('client/editWexAccount', ['userId'=>$user->id,'wexAccountId'=>$wexAccount->id]);
			}
			else
				$this->error(WexAccount::$lastError);
		}
		elseif($_POST['buyBtcRu'])
		{
			$result = $wexAccount->buyBtcRu();
			if($result)
				$this->success($result);
			else
				$this->error('Ошибка обмена Руб в BTC '. WexCurlBot::$lastError);
		}
		elseif($_POST['withdrawBtc'])
		{
			$result = $wexAccount->withdrawBtc($params[$user->id]['address']);
			if($result)
				$this->success('Создан запрос на вывод BTC '.arr2str($result));
			else
				$this->error('Ошибка создания запроса на отправку BTC '. WexCurlBot::$lastError);
		}
		elseif($_POST['confirmPaymentTutanota'])
		{
			$result = $wexAccount->confirmPaymentTutanota();
			if($result)
				$this->success('Вывод подтвержден: '.arr2str($result));
			else
				$this->error('Ошибка подтверждения вывода '. WexCurlBot::$lastError);
		}
		elseif($_POST['sendMessageToConfirmEmail'])
		{
			$result = $wexAccount->sendMessageToConfirmEmail();

			if($result)
				$this->success('Отправлено письмо с подтверждением привязки');
			else
				$this->error('Ошибка отправки письма с подтверждением привязки '. WexCurlBot::$lastError);
		}
		elseif($_POST['confirmLinkMailTutanota'])
		{
			$result = $wexAccount->confirmLinkMailTutanota();

			if($result)
				$this->success('Почта прикреплена к акканту');
			else
				$this->error('Ошибка привязки почты к аккаунту '. WexCurlBot::$lastError);
		}

		$this->render('editWexAccount', [
			'wexAccount'=>$wexAccount,
			'user'=>$user,
			'params'=>$params,
		]);
	}

	/**
	 * метод для управления заявками Яндекс манов
	 * позволяет отметить заявку оплаченой либо отменить оплату
	 */
	public function actionYandexPayGlobalFin($userId, $wexAccountId)
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$session = &Yii::app()->session;
		$interval = isset($session['yandexStats']) ? $session['yandexStats'] : [];

		$user = User::getUser($userId);

		$params = $_POST['params'];


		if($_POST['cancel'])
		{
			if(YandexPay::cancelPayment($params['id'], $user->id))
				$this->success('Платеж '.$params['id'].' отменен');
			else
				$this->error('Ошибка отмены платежа '. YandexPay::$lastError);
		}
		elseif($_POST['confirm'])
		{
			if(YandexPay::confirmPayment($params['id'], $user->id, $params['wexId'], strtotime($params['datePay'])))
				$this->success('Платеж '.$params['id'].' подтвержден');
			else
				$this->error('Ошибка подтверждения платежа '. YandexPay::$lastError);
		}
		elseif($params['dateStart'] and $params['dateEnd'])
		{
			$interval = $params;

			$session['yandexStats'] = $interval;
			$this->redirect('client/yandexPayGlobalFin', ['userId'=>$userId, 'wexAccountId'=>$wexAccountId]);
		}
		elseif($_POST['updateHistory'])
		{
			if(
				$wexAccount = WexAccount::getModel(['id'=>$wexAccountId])
				and
				$wexAccount->user_id == $user->id
			)
			{
				if(time() - $wexAccount->date_check > 60)
				{
					if(YandexPay::startUpdateHistory(0, $wexAccount->id))
					{
						$this->success('платежи обновлены');
						$this->redirect('client/yandexPayGlobalFin', ['userId'=>$userId, 'wexAccountId'=>$wexAccountId]);
					}
					else
						$this->error('ошибка обновления');
				}
				else
					$this->error('обновление доступно не чаще чем раз в минуту');

			}
			elseif($_POST['urlSearch'])
			{

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

		$userId = $user->id;
		$clientId = $user->client_id;

		$models = YandexPay::getModels(
			strtotime($interval['dateStart']),
			strtotime($interval['dateEnd']),
			$userId,
			$clientId,
			false
		);

		$history = [];

		if($account = WexAccount::getModel(['id'=>$wexAccountId]))
		{
			//обновить историю, дальше не юзаем
			$history = $account->getHistory();

			if($history === false)
				$this->error('ошибка получения истории');
			elseif(count($history) == 0)
				$this->error('история пуста');

			$history = TransactionWex::getModels(
				strtotime($interval['dateStart']), strtotime($interval['dateEnd']),
				$userId
			);

		}
		else
			$this->error('account not found');

		$this->render('yandexPayAdmin', [
			'models'=>$models,
			'stats'=>YandexPay::getStats($models),
			'history'=>$history,
			'params'=>$params,
			'interval'=>$interval,
			'wexAccount' => $user->wexAccount,

		]);
	}

	public function actionQiwiNewAccounts($clientId = 0)
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];

		$history = [];

		if($_POST['save'])
		{
			$done = PayeerAccount::saveMany($params);

			if(!WexAccount::$lastError)
			{
				$this->success('сохранено '.$done.' аккаунтов');
				$this->redirect('client/qiwiNewAccounts', ['clientId'=>$clientId]);
			}
			else
				$this->error(PayeerAccount::$lastError);
		}
		elseif($_POST['updateQiwiNewAccount'])
		{
			if($model = PayeerAccount::getModel(['id'=>$params['accountId']]))
			{
				if($model->updateAccount())
				{
					$this->success('аккаунт '.$model->login.' обновлен');
					$this->redirect('client/qiwiNewAccounts', ['clientId'=>$clientId]);
				}
				else
					$this->error('ошибка обновления аккаунта');
			}
			else
				$this->error('аккаунт не найден');
		}

		$cond = "`role` IN ('".User::ROLE_MANAGER."', '".User::ROLE_FINANSIST."')";

		if($clientId > 0)
			$cond .= " AND `client_id`=$clientId";

		$users = User::model()->findAll([
			'condition' => $cond,
			'order' => "`client_id` ASC, `id` DESC",

		]);


		$this->render('qiwiNewAccounts', [
			'users' => $users,
			'client' => Client::getModel($clientId),
			'history' => $history,
		]);
	}

	/**
	 * общая статистика платежей qiwi new, общая сумма и сумма по манам, клиентам
	 */
	public function actionQiwiNewStat()
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$clients = Client::clientList();

		$clientsQiwiNewArr = [];

		$totalAmountRu = 0;

		if($clients)
		{
			foreach($clients as $key=>$client)
			{
				$cond = "`role` IN ('".User::ROLE_MANAGER."', '".User::ROLE_FINANSIST."')";
				$cond .= " AND `client_id`=$client->id";

				$users = User::model()->findAll([
					'condition' => $cond,
					'order' => "`client_id` ASC, `name` ASC",

				]);

				$amountClientRu = 0;

				foreach($users as $user)
				{
					if($user->payeerAccount)
					{
						$amountClientRu += $user->payeerAccount->balance_ru;
						$clientsQiwiNewArr[$key]['client'] = $client;
						$clientsQiwiNewArr[$key]['users'][] = $user;
					}
				}

				if($clientsQiwiNewArr[$key]['client'])
				{
					$clientsQiwiNewArr[$key]['clientAmountRu'] = $amountClientRu;
					$totalAmountRu += $amountClientRu;
				}
			}
		}

		$params = $_POST['params'];
		if($_POST['updateQiwiNewAccount'])
		{
			if($model = PayeerAccount::getModel(['id'=>$params['accountId']]))
			{
				if($model->updateAccount())
				{
					$this->success('аккаунт '.$model->login.' обновлен');
					$this->redirect('client/qiwiNewStat');
				}
				else
					$this->error('ошибка обновления аккаунта');
			}
			else
				$this->error('аккаунт не найден');
		}
		elseif($_POST['setWalletForWithdraw'])
		{
			if($params['walletForWithdraw'])
			{
				if($this->_checkQiwiNewLoginFormat($params['walletForWithdraw']))
				{
					config('walletForWithdraw', $params['walletForWithdraw']);
					$this->success('Задан логин для приема вывода');
				}
				else
					$this->error('Неверный формат логина получателя = '.config('walletForWithdraw'));

			}
			else
			{
				$this->error('Ошибка задания логина для приема вывода');
			}

		}
		elseif($_POST['withdraw'])
		{
			if($model = PayeerAccount::getModel(['id'=>$params['accountId']]))
			{
				$amount = 0;
				$minSum = 1;
				$maxSum = 15000;

				if($this->_checkQiwiNewLoginFormat(config('walletForWithdraw')))
				{
					$receiver = config('walletForWithdraw');
					$balance = $model->getBalance();
					$result = [];
					$successWithdrawAmount = 0;

					if($balance > 0)
					{
						for($i = 0; $i < ceil($balance/$maxSum); $i++)
						{
							$balance = $model->getBalance();
							if($balance > $minSum)
							{
								if($balance > $maxSum)
									$amount = $maxSum;
								else
									$amount = $balance;
								$result[$i]['fullAmount'] = $amount;
								$result[$i] = $model->sendQiwiMoneyRu([
									'amount' => $amount,
									'receiver' => $receiver,
								]);
								if($result[$i]['amount'])
									$successWithdrawAmount += $result[$i]['amount'];
								sleep(2);

								if(!$result)
									break;
							}
							else
								break;
						}
					}
					else
						$this->error('error balance = '.$balance);

					if($successWithdrawAmount == 0)
						$this->error('Ошибка вывода на '.$receiver);
					else
					{
						$model->balance_ru = $model->getBalance();
						$model->save();
						$this->success('Выведено '.$successWithdrawAmount.' руб на '.$receiver);
					}
				}
				else
					$this->error('Неверный формат логина получателя = '.config('walletForWithdraw'));
			}
			else
				$this->error('аккаунт не найден');
		}
		elseif($_POST['rebootSelenium'])
		{
			$sender = new Sender;
			$sender->followLocation = false;

			$content = $sender->send('http://94.140.125.237/selenium/index.php?key=testtest&method=RebootSelenium');

			if(preg_match('!Перезагружен Selenium!iu', $content))
				$this->success('Перезагружен Selenium');
			else
				$this->error(strip_tags($content));
		}
		$this->render('qiwiNewStat', [
			'clientsQiwiNewArr' => $clientsQiwiNewArr,
			'totalAmountRu' => $totalAmountRu,
		]);
	}

	protected function _checkQiwiNewLoginFormat($account)
	{
		if(!preg_match('!P[\d]{10}!iu', trim($account)))
		{
			$message = ' неверный формат логина '.$account;
			toLogError($message);
			return false;
		}
		else
			return true;
	}

	/**
	 * @param $userId
	 * @param $qiwiNewAccountId
	 *
	 * редактируем пользователя QiwiNew в отдельной странице
	 */
	public function actionEditQiwiNewAccount($userId, $qiwiNewAccountId)
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$qiwiNewAccount = PayeerAccount::model()->findByPk($qiwiNewAccountId);
		$user = User::model()->findByPk($userId);

		$params = $_POST['params'];

		if($_POST['save'])
		{
			$done = PayeerAccount::saveMany($params);

			if(!PayeerAccount::$lastError)
			{
				PayeerBot::clearCookie($qiwiNewAccount->login);
				$this->success('сохранено, очищены cookie: '.$qiwiNewAccount->login);
				$this->redirect('client/editQiwiNewAccount', ['userId'=>$user->id,'qiwiNewAccountId'=>$qiwiNewAccount->id]);
			}
			else
				$this->error(PayeerAccount::$lastError);
		}
		elseif($_POST['createApiParams'])
		{
			if($qiwiNewAccount->createApiParams())
			{
				$this->success('созданы параметры Api login = '.$qiwiNewAccount->login);
				$this->redirect('client/editQiwiNewAccount', ['userId'=>$user->id,'qiwiNewAccountId'=>$qiwiNewAccount->id]);
			}
			else
				$this->error(PayeerAccount::$lastError);
		}

		$this->render('editQiwiNewAccount', [
			'qiwiNewAccount'=>$qiwiNewAccount,
			'user'=>$user,
			'params'=>$params,
		]);
	}


	/**
	 * @param $qiwiNewAccountId
	 * @param $user
	 * вывод истории по всем операциям
	 */
	public function actionQiwiNewHistoryAdmin($qiwiNewAccountId, $user, $pageNum = 1)
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$history = [];

		$error = 'error';

		$params = $_POST['params'];

		if($account = PayeerAccount::getModel(['id'=>$qiwiNewAccountId]))
		{
			if($_POST['updateWexAccount'])
			{
				if($account->updateAccount())
				{
					$this->success('аккаунт '.$account->login.' обновлен');
				}
				else
					$this->error('ошибка обновления аккаунта');
			}

			$history = $account->getApiHistory(100);
			$balance = $account->getApiBalance();

			if($balance > 0 OR $balance == 0)
			{
				$account->balance_ru = $balance;
				$account->save();
			}

			if($history === false)
				$this->error('ошибка получения истории');
			elseif($history['errors'])
			{
				$this->error('Необходимо создать параметры входа Api. В таблице QiwiNew кнопка Edit->Создать параметры API ');
				$history = [];
			}
			elseif(count($history['history']) == 0)
				$this->error('история пуста');
		}
		else
			$this->error('account not found');



		$this->render('qiwiNewHistoryAdmin', [
			'user'=>$user,
			'history' => $history,
			'qiwiNewAccountId' => $qiwiNewAccountId,
			'error' => $error,
			'account' => $account,
			'params' => $params,
		]);
	}

	/**
	 * @return mixed
	 *
	 * управляем модулями клиента через веб-интерфейс
	 */
	public function actionModuleRule()
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$params = [];
		$models = Client::getModels();

		if($_POST['saveRules'])
		{
			$params = $_POST['params'];
			$result = Client::saveModuleRule($params);

			if($result)
				$this->success($result);

			if(ClientModuleRule::$lastError)
				$this->error('error: '.ClientModuleRule::$lastError);
			else
				$this->redirect('client/moduleRule');
		}

		return $this->render('moduleRule', [
			'models' => $models,
		]);
	}
}