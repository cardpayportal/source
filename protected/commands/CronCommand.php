<?php

/**
 * todo: придумать как упростить многопоточность/ чтобы не писать километровый кронтаб
 */
class CronCommand extends CConsoleCommand
{
	public function beforeAction($action, $params)
	{
		session_destroy();

		if (YII_DEBUG)
			die("/n tech works");

		return parent::beforeAction($action, $params);
	}

	public function actionCheckAccount($args)
	{
		$clientId = $args[0];
		$groupId = $args[1];

		if ($count = Account::startCheckBalance($clientId, $groupId))
			echo "проверено: $count \n";
		else
			echo "проверено: $count \n";

		if (Account::$lastError)
			echo "ошибка: " . Account::$lastError . " \r\n";
	}

	public function actionTransAccount($args)
	{
		$clientId = $args[0];
		$groupId = $args[1];

		if ($count = Account::startTransBalance($clientId, $groupId))
			echo "проверено: $count \r\n";
		else
			echo "проверено: $count \r\n";

		if (Account::$lastError)
			echo "ошибка: " . Account::$lastError . " \r\n";
	}

	public function actionCheckOut($args)
	{
		$clientId = $args[0];

		if ($count = Account::startCheckOut($clientId))
			echo "проверено: $count \r\n";
		else
			echo "проверено: $count \r\n";

		if (Account::$lastError)
			echo "ошибка: " . Account::$lastError . " \r\n";
	}

	public function actionCheckUsed()
	{
		Account::startCheckUsed();
	}


	public function actionFullCheck($args)
	{
		$clientId = $args[0] * 1;
		$groupId = $args[1] * 1;

		//проверять не чаще чем ..
		$dateCheck = time() - 600;

		$condition = "`client_id`=$clientId AND `group_id`=$groupId AND `date_check`<$dateCheck";

		$accountsNew = Account::model()->findAll(array(
			'condition' => "$condition AND `error`='".Account::ERROR_IDENTIFY."' AND `enabled`=1",
			'order' => "`date_add`",
		));

		shuffle($accountsNew);

		$accountsCheck = Account::model()->findAll(array(
			'condition' => "$condition AND `error`='".Account::ERROR_CHECK."' AND `enabled`=1",
			'order' => "`date_last_request`",
		));

		shuffle($accountsCheck);

		//сначала новые потом остальные
		$accounts = array_merge($accountsNew, $accountsCheck);

		/**
		 * @var Account[] $accounts
		 */

		if (!$accounts)
			echo 'нечего проверять';

		echo "last: " . count($accounts);

		shuffle($accounts);

		$doneCount = 0;

		foreach ($accounts as $model) {
			if (Tools::timeOut())
				break;

			if ($model->fullCheck())
				$doneCount++;
			else
				break;
		}

		echo 'done: ' . $doneCount;

		if (Account::$lastError)
			echo '<br>error: ' . Account::$lastError;
	}

	public function actionProxyChecker()
	{
		echo 'проверено ' . Proxy::startCheck() . ' прокси';
	}

	public function actionCheckEmail($args)
	{
		die('остановлено');
		$thread = $args[0];

		$threadCount = 1;
		$randomOrder = true;
		$limit = 100;
		$threadName = 'checkEmail';

		if (Tools::threader($threadName . $thread)) {
			//привязанные не проверять
			$linkCondition = "`id` NOT IN(SELECT `email_id` FROM `" . Account::model()->tableSchema->name . "` WHERE `email_id`!=0)";

			$threadCondition = Tools::threadCondition($thread, $threadCount);

			$condition = "`error`='' AND `date_check`<" . (time() - AccountEmail::CHECK_INTERVAL) . " AND $linkCondition AND $threadCondition";

			$models = AccountEmail::model()->findAll(array(
				'condition' => $condition,
				'order' => "`date_check` ASC",
				'limit' => $limit,
			));

			/**
			 * @var AccountEmail[] $models
			 */

			if ($randomOrder)
				shuffle($models);

			echo "<br> осталось" . AccountEmail::model()->count($condition);

			$checkCount = 0;

			foreach ($models as $model) {
				if (Tools::timeOut())
					break;

				echo "<br> проверяем {$model->email} {$model->pass}";

//				$login = $model->login;
//				$additional = '';

//				if($model->server == 'mail.ru')
//					$login = $model->email;

				//if($model->server == 'yandex.ru')
				//	$additional = '/imap/notls';


				$isWork = $model->getIsWork();

				if ($isWork) {
					$msg = " Email {$model->email}: checked";
					//toLog($msg);
					echo '<br>' . $msg;
				} else {
					$msg = " Email {$model->email}: " . $model->botError;
					echo '<br>' . $msg;
				}

				$checkCount++;
				sleep(7);
			}

		} else
			die('<br>поток уже запущен');

		echo '<br>checked: ' . $checkCount;

	}


	public function actionLinkEmail($args)
	{
		//test временно оставновлено чтобы параллельно не лезть на коши и ботом и по апи
		die('stop');

		$thread = $args[0];

		$threadName = 'linkEmail';
		$threadCount = 2;
		$isRandomOrder = true;
		$emailWarnCount = 100;
		$workTime = 50;

		//если за это время не пришло письмо то привязываем другое мыло + логируем смену
		$changeEmailInterval = AccountEmail::CHANGE_EMAIL_INTERVAL;

		if (Tools::threader($threadName . $thread)) {
			$freeEmailCount = AccountEmail::freeModelCount();

			if ($freeEmailCount <= $emailWarnCount) {
				if ($freeEmailCount == 0)
					toLogError($threadName . ': закончились email-ы', true);
				elseif (!AccountEmail::model()->find("`date_check`=0"))    //если нет непроверенных
					toLogError($threadName . ': Внимание! осталось ' . $freeEmailCount . ' email-ов');
			}

			$threadCondition = Tools::threadCondition($thread, $threadCount);

			$minLimit = 1000;

			//is_ecomm - личные кошельки юзеров, к ним не крепить
			$models = Account::model()->findAll(array(
				'condition' => "
					`error`=''
					AND `date_used`=0
					AND `limit_in`>=$minLimit
					AND `is_ecomm` = 0
					AND `enabled`=1
					AND `is_email` = 0 AND $threadCondition
				",
				'order' => "`email_link_date` DESC, `date_check` DESC",    //сначала прикрепить рабочие аккаунты
				'limit' => 10,
			));

			/**
			 * @var Account[] $models
			 */

			if ($isRandomOrder)
				shuffle($models);

			$doneCount = 0;

			foreach ($models as $model) {
				if (Tools::timeIsOut($workTime))
					break;

				//паузы между авторизациями
				sleep(5);

				if ($bot = $model->bot) {
					//проверить прикреплено ли мыло сейчас
					$isLinked = $bot->isEmailLinked();


					if ($isLinked === true) {
						Account::model()->updateByPk($model->id, array('is_email' => 1));
						$doneCount++;
						continue;
					} elseif ($isLinked !== false) {
						$msg = $threadName . '(' . $model->login . '): ' . $bot->error;
						echo '<br>' . $msg;
						toLog($msg);
						break;
						//continue;
					}

					$accountEmail = $model->email;

					//если небыло запроса на прикрепление
					if (!$model->email_link_date) {
						//если у аккаунта нет мыла то резервируем
						if (!$accountEmail) {
							if (!$accountEmail = AccountEmail::getFreeEmail()) {
								$msg = $threadName . '(' . $model->login . '): ' . AccountEmail::$lastError;
								echo '<br>' . $msg;
								toLog($msg);
								break;
							}

							Account::model()->updateByPk($model->id, array('email_id' => $accountEmail->id));
						}

						if ($bot->linkEmail($accountEmail->email)) {
							Account::model()->updateByPk($model->id, array('email_link_date' => time()));

							$msg = $threadName . '(' . $model->login . '): прикрепление почты ' . $accountEmail->email;
							echo '<br>' . $msg;
							toLogRuntime($msg);
							sleep(30);
						} else {
							$msg = $threadName . '(' . $model->login . '): ' . $bot->error;
							echo '<br>' . $msg;
							toLog($msg);
							break;
							//continue
						}
					} elseif (time() - $model->email_link_date > $changeEmailInterval or $model->email->error) {
						//смена мыла по таймауту или если есть ошибка на мыле
						//todo: устранить этот дубльблок кода
						//todo: смена емейла если на мыло зайти не получается
						if (!$accountEmail = AccountEmail::getFreeEmail()) {
							$msg = $threadName . '(' . $model->login . '): ' . AccountEmail::$lastError;
							echo '<br>' . $msg;
							toLog($msg);
							break;
						}

						Account::model()->updateByPk($model->id, array(
							'email_id' => $accountEmail->id,
							'email_link_date' => 0,
						));

						$msg = $threadName . '(' . $model->login . '): смена email';
						echo '<br>' . $msg;
						toLog($msg);
						continue;
					}

					//непрочитанные письма от qiwi
					$messages = $accountEmail->getMessages('qiwi.com', true);

					if ($messages !== false) {
						if (count($messages) > 0) {
							$message = current($messages);

							if (!preg_match('!email/create\.action\?code=(.+?)"!', $message['text'], $res))
								$message['text'] = base64_decode($message['text']);

							if (preg_match('!email/create\.action\?code=(.+?)"!', $message['text'], $res)) {
								$emailCode = $res[1];

								if ($bot->completeLinkEmail($emailCode)) {
									Account::model()->updateByPk($model->id, array(
										'is_email' => 1,
									));

									$msg = $threadName . '(' . $model->login . '): привязка мыла завершена';
									echo '<br>' . $msg;
									toLogRuntime($msg);
									$doneCount++;
								} else {
									$msg = $threadName . '(' . $model->login . '): ошибка завершения прикрепления ' . $bot->error;
									echo '<br>' . $msg;
									toLog($msg);
								}
							} else {
								$msg = $threadName . '(' . $model->login . '): link code not found ' . $accountEmail->email . ', text: ' . $message['text'];
								echo '<br>' . $msg;
								toLog($msg);
							}
						} else {
							$msg = $threadName . '(' . $model->login . '): unread messages not found: ' . $accountEmail->email;
							echo '<br>' . $msg;
							toLog($msg);
							continue;
						}
					} else {
						$msg = $threadName . ' model->login ' . '(' . $model->login . '): ' . $accountEmail->botError;
						echo $msg;
						toLog($msg);
						break;
					}
				} else {
					$msg = $threadName . ' ' . $model->login . ' error bot: ' . $model->botError;
					echo '<br>' . $msg;
					toLog($msg);
					break;
					//continue;
				}
			}
		} else
			die('<br>поток уже запущен');

		echo '<br>done: ' . $doneCount;
	}


	public function actionStoreApiWithdraw()
	{
		StoreApiWithdraw::startWithdraw();
	}

	/*
	 * авто-добавление
	 */
	public function actionAccountAutoAdd()
	{
		if (config('autoAddEnabled'))
			Account::startAutoAdd();
	}

	/**
	 * обновляет курс usd(finam, btce-lastPrice) и btc-lastPrice
	 * @return null;
	 */
	public function actionUpdateRates()
	{
		ClientCalc::startUpdateRates();
	}

	public function actionProxyReset()
	{
		if (Proxy::startReset())
			echo 'ок';
		else
			echo 'ошибка: ' . Proxy::$lastError;
	}

	/*
	public function actionAntiCaptcha()
	{
		toLogRuntime('actionAntiCaptcha');

		if(AntiCaptcha::startSolving())
			echo 'ок';
		else
		{
			echo 'ошибка: '.AntiCaptcha::$lastError;
		}
	}
	*/

	public function actionSessionExtend($args)
	{
		$thread = $args[0];
		Account::sessionExtend($thread);
	}

	public function actionBanChecker($args)
	{
		$thread = $args[0];

		BanChecker::startCheck($thread);
	}

	public function actionNextQiwiBanChecker($args)
	{
		return false;
		$thread = $args[0];

		NextQiwiPay::checkWalletsBan($thread);
	}


	/**
	 * обновление кэшей
	 *
	 * @param array $args
	 *
	 */
	public function actionCacheUpdate($args)
	{
		Client::cacheUpdateRecalc();

		echo "\n done";
	}

	public function actionReIdent($args)
	{
		$threadNumber = $args[0];

		//comment idented: ''
		$comment = 'reident';
		$commentProcessed = 'reidented';

		$threadName = 'reident';
		$threadCount = 2;

		if (!Tools::threader($threadName . $threadNumber))
			die('thread already run');

		$threadCondition = Tools::threadCondition($threadNumber, $threadCount);

		$accounts = Account::model()->findAll([
			'condition' => "`comment`='$comment' AND `error` NOT IN('ban', 'ident_closed') AND $threadCondition",
			'order' => "`error` DESC",
			'limit' => 10,
		]);

		shuffle($accounts);

		/**
		 * @var Account[] $accounts
		 */

		$done = 0;

		foreach ($accounts as $account) {
			$attributes = [];

			if ($account->identify()) {
				$attributes['status'] = Account::STATUS_HALF;
				$attributes['comment'] = $commentProcessed;

				if (in_array($account->error, [Account::ERROR_LIMIT_OUT, Account::ERROR_IDENTIFY]) and $account->limit_in > 0) {
					$attributes['date_out_of_limit'] = 0;
					$attributes['error'] = '';
				}

				Account::model()->updateByPk($account->id, $attributes);

				$done++;
			} else {
				echo('error: ' . Account::$lastError);
				continue;
			}
		}

		echo 'done: ' . $done;
	}


	public function actionCheckFinOrders($args)
	{
		die('временно остановить на масс слив с кл');
		$models = FinansistOrder::model()->findAll("`status`='" . FinansistOrder::STATUS_WAIT . "' AND `for_cancel`=0");

		/**
		 * @var FinansistOrder[] $models
		 */

		$done = 0;

		foreach ($models as $model) {
			$transactions = Transaction::model()->findAll([
				//'select'=>"`amount`",
				'condition' => "
					`client_id`='{$model->client_id}'
					AND `date_add`>{$model->date_add}
					AND `status`='" . Transaction::STATUS_SUCCESS . "'
					AND `type`='" . Transaction::TYPE_OUT . "'
					AND `wallet`='{$model->to}'
				",
			]);

			/**
			 * @var Transaction[] $transactions
			 */

			$amountSend = 0;

			foreach ($transactions as $trans)
				$amountSend += $trans->amount;

			$oldAmount = $model->amount_send;

			if (floor($model->amount_send) < floor($amountSend)) {
				echo "\n" . floor($amountSend);
//				$model->amount_send = $amountSend;

//				if($model->amount_send >= $model->amount)
//					$model->status = FinansistOrder::STATUS_DONE;

				FinansistOrder::model()->updateByPk($model->id, ['amount_send' => $amountSend]);
				$done++;

				$msg = "исправлена заявка фина id= {$model->id}: сумма: $amountSend вместо $oldAmount";

				echo "\n $msg";
				toLogRuntime($msg);

			}
		}

		if (!$done)
			echo "\n нечего исправлять";

		echo "\n";
	}

	/**
	 * обновление кэшей
	 *
	 * @param array $args
	 *
	 */
	public function actionCouponActivate($args)
	{
		Coupon::startActivate();

		echo "\n done";
	}


	/**
	 * обновление информации по платежам с яндекса на векс
	 *
	 * @param $args
	 *
	 */
	public function actionWexCheck($args)
	{
		$threadNumber = $args[0];
		YandexPay::startUpdateHistory($threadNumber);

		echo "\n done";
	}


	/**
	 * обновление информации по платежам qiwi на payeer
	 *
	 * @param $args
	 *
	 */
	public function actionQiwiCheck($args)
	{
		$threadNumber = $args[0];
		QiwiPay::startUpdateHistory($threadNumber);

		echo "\n done";
	}

	/**
	 * создание реквизитов оплаты qiwi на payeer
	 *
	 * @param $args
	 *
	 */
	public function actionGetPayUrl($args)
	{
		//$threadNumber = $args[0];
		QiwiPay::getPayUrl();

		echo "\n done";
	}

	public function actionNextQiwiCheckOrders($args)
	{
		return false;
		//$threadNumber = $args[0];
		NextQiwiPay::startCheckOrders();

		echo "\n done";
	}


	public function actionStoreApiNotify($args)
	{
		NewYandexPay::startApiNotification();
		WalletSTransaction::startApiNotification();
		//todo: прописать уведомления по Sim-платежам
		//SimTransaction::startApiNotification();
	}

	public function actionChangeYandexWallet($args)
	{
		NewYandexPay::setRandomWallet();
		NewYandexPay::setRandomWallet(true);
	}

	public function actionYandexAccountCheck($args)
	{
		$threadNumber = $args[0];
		//YandexAccount::startCheck($threadNumber);
	}

	/**
	 * @param $args
	 * обновление всех транзакций мерчанта
	 */
	public function actionUpdateQiwiMerchant($args)
	{
		MerchantTransaction::updateMerchantTransactions();
	}

	/**
	 * @param $args
	 * обновление списка кошей мерчанта
	 */
	public function actionAddMerchantWalletInfo($args)
	{
		MerchantWallet::addInfo();
	}

	public function actionMegakassaClear($args)
	{
		MegakassaProxyRequest::startClear();
	}

	//глобальна очистка системы от мусора
	public function actionGlobalCleaning($args)
	{
		ManagerApiRequest::clean();
		NewYandexPay::startCancelOrders();
		SimTransaction::startCancelOldTransactions();
		//сюда дописывать вызов очистки
	}


	//запасной вариант подтверждения платежей яда
	public function actionYandexHistoryFromNikolas()
	{
		session_write_close();
		$url = 'http://85.25.109.85:661/v1/site/deposit-history?token=fhOIEHfpIEuh87fgoe98fto3i3fagoGF39rfeihMw&count=500';

		$sender = new Sender;
		$sender->followLocation = true;

		$content = $sender->send($url);

		$response = json_decode($content, true);

		if($response)
		{
			$successCount = 0;

			try
			{
				if(is_array($response) and $response['items'])
				{
					foreach($response['items'] as $data)
					{
						$paramsArr = [
							'amount' => $data['withdraw_amount'],
							'number' => ($data['notification_type'] == 'card-incoming') ? 'card' : $data['sender'],
							'paymentId' => $data['operation_id'],
							'orderId' => $data['label'],
							'notificationType' => $data['notification_type'],
						];

						if(NewYandexPay::confirmPayment($paramsArr))
							$successCount++;
						else
							continue;

					}
					echo($successCount);
					die;
				}
				else
					return false;
			}
			catch(Exception $e)
			{
				echo 'Error';
				return false;
			}
		}
		else
			return false;
	}

	//отправляем инфо о перелимитах яндекс опта
	public function actionNotifyOutOfLimit()
	{
		YandexAccount::notifyOutOfLimit();
		die;
	}

	public function actionRiseXList()
	{
		echo "\n ";
		echo(RisexTransaction::saveDealList());
		echo "\n done";
	}

}