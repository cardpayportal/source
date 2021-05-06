<?php

class CronController extends CController
{

    public function actionCheckAccount($pass, $clientId, $groupId)
    {
		session_write_close();
		$this->checkAccess($pass);

		if($count = Account::startCheckBalance($clientId, $groupId))
			echo "проверено: $count \n";
		else
			echo "проверено: $count \n";

		if(Account::$lastError)
			echo "ошибка: ".Account::$lastError." \r\n";
    }

	public function actionTransAccount($pass, $clientId, $groupId)
	{
		session_write_close();
		//sleep(rand(10, 30));
		$this->checkAccess($pass);
		if($count = Account::startTransBalance($clientId, $groupId))
			echo "проверено: $count \r\n";
		else
			echo "проверено: $count \r\n";

		if(Account::$lastError)
			echo "ошибка: ".Account::$lastError." \r\n";
	}

    /**
     * проверка выходных кошельков
     */
	public function actionCheckOut($pass, $clientId)
	{
		session_write_close();
		//sleep(rand(10, 30));

		$this->checkAccess($pass);

		if($count = Account::startCheckOut($clientId))
			echo "проверено: $count \r\n";
		else
			echo "проверено: $count \r\n";

		if(Account::$lastError)
			echo "ошибка: ".Account::$lastError." \r\n";

	}

    /**
     * помечает кошельки использованными(отпрвляет в отстойник)
     */
    public function actionCheckUsed($pass)
    {
		session_write_close();
		if($count = Account::startCheckUsed())
			echo "проверено: $count \r\n";
		else
			echo "проверено: $count \r\n";

		if(Account::$lastError)
			echo "ошибка: ".Account::$lastError." \r\n";
    }

    private function checkAccess($pass)
    {
        session_write_close();

        if(YII_DEBUG and !Tools::isAdminIp())
            die('тех работы');

        $configPass = cfg('cron_pass');

        if($pass===$configPass)
        {
			/*
            #безопасная очистка куки всех акков каждый час
            $clearInterval = cfg('clear_cookie_interval');

            if(time() - config('cookie_last_clear') > $clearInterval)
            {
                if(
                    Tools::threadExist('!checkAccount!', true)
                    or
                    Tools::threadExist('!transAccount!', true)
                    or
                    Tools::threadExist('!checkOut!', true)
                    or
                    Tools::threadExist('!fullCheck!', true)
                )
                {
                    die('wait while clear cookie');
                }
                else
                {
                    if(Tools::threader('clearCookies'))
                    {
                        if(QiwiBot::clearAllCookies())
                        {
                            config('cookie_last_clear', time());
                            toLog('куки очищены');
                        }
                        else
                            toLog('ошибка очистки куки');
                    }
                    else
                        die('clearCookie already run');
                }
            }
			*/
            
            return true;
        }
        else
            die('access denied');
    }

	public function actionFullCheck($pass, $clientId, $groupId)
	{
		session_write_close();
		$this->checkAccess($pass);

		//проверять не чаще чем раз в час
		$dateCheck = time() - 600;


		//'condition'=>"`date_check`<$dateCheck and (`error`='identify_anonim' or `error`='check_wait')",
		$dateFullCheck = time() - 3600*2;
		$dateCheckStart = time() - 3600*10;
		//'condition'=>"`date_full_check`<$dateFullCheck and `date_check`>$dateCheckStart and `type` in('transit', 'out') and `error`='' and `limit`>0 and `is_old`=0",

		//(`date_check`<$dateCheck and (`error`='identify_anonim' or `error`='check_wait'))
		//or
		//(`date_full_check`<$dateFullCheck and `date_check`>$dateCheckStart and `type` in('out') and `error`='' and `limit`>0 and `is_old`=0)

		$condition = "`client_id`=$clientId AND `group_id`=$groupId AND `date_check`<$dateCheck AND `enabled`=1";

		$accountsNew = Account::model()->findAll(array(
			'condition'=> "$condition AND `error`='".Account::ERROR_IDENTIFY."'",
			'order'=>"`date_add`",
		));

		shuffle($accountsNew);

		$accountsCheck = Account::model()->findAll(array(
			'condition'=> "$condition AND `error`='".Account::ERROR_CHECK."'",
			'order'=>"`date_last_request`",
		));

		shuffle($accountsCheck);
//
		$accounts = array_merge($accountsNew, $accountsCheck);

		/**
		 * @var Account[] $accounts
		 */

		if(!$accounts)
			echo 'нечего проверять';

		/*
		foreach($accounts as $account)
			echo $account->login.'<br>';
		die;
		*/

		echo "last: ".count($accounts);

		shuffle($accounts);

		$doneCount = 0;

		//if($accounts)
		//	toLog('startFullCheck clientId='.$clientId.' groupId='.$groupId);

		foreach($accounts as $model)
		{
			if(Tools::timeOut())
				break;

			echo '<br>проверяем: '.$model->login;

			if($model->fullCheck())
			{
				$doneCount++;
			}
			else
			{
				break;
			}

			//Tools::threaderClear('group'.$model->group_id);
		}

		echo '<br>done: '.$doneCount;

		if(Account::$lastError)
			echo '<br>error: '.Account::$lastError;
	}

	public function actionProxyChecker($pass)
	{
		$this->checkAccess($pass);

		echo 'проверено '.Proxy::startCheck().' прокси';
	}

	/*
	 * проверка мыл на рабочесть
	 */
	public function actionCheckEmail($pass, $thread)
	{
		$threadCount = 2;
		$randomOrder = true;
		$limit = 100;
		$threadName = 'checkEmail';

		session_write_close();

		$this->checkAccess($pass);

		if(Tools::threader($threadName.$thread))
		{
			//привязанные не проверять
			$linkCondition = "`id` NOT IN(SELECT `email_id` FROM `".Account::model()->tableSchema->name."` WHERE `email_id`!=0)";

			$threadCondition = Tools::threadCondition($thread, $threadCount);

			$condition = "`error`='' AND `date_check`<".(time() - AccountEmail::CHECK_INTERVAL)." AND $linkCondition AND $threadCondition";

			$models = AccountEmail::model()->findAll(array(
				'condition'=>$condition,
				'order'=>"`date_check` ASC",
				'limit'=>$limit,
			));

			/**
			 * @var AccountEmail[] $models
			 */

			if($randomOrder)
				shuffle($models);

			echo "<br> осталось".AccountEmail::model()->count($condition);

			$checkCount = 0;

			foreach($models as $model)
			{
				if(Tools::timeOut())
					break;

				echo "<br> проверяем {$model->email} {$model->pass}";

//				$login = $model->login;
//				$additional = '';

//				if($model->server == 'mail.ru')
//					$login = $model->email;

				//if($model->server == 'yandex.ru')
				//	$additional = '/imap/notls';


				$isWork = $model->getIsWork();

				if($isWork)
				{
					$msg = " Email {$model->email}: checked";
					//toLog($msg);
					echo '<br>'.$msg;
				}
				else
				{
					$msg = " Email {$model->email}: ".$model->botError;
					echo '<br>'.$msg;
				}

				$checkCount++;
				sleep(5);
			}

		}
		else
			die('<br>поток уже запущен');

		echo '<br>checked: '.$checkCount;
	}


	/**
	 * прикрепление мыл к аккаунтам
	 *
	 */
	public function actionLinkEmail($pass, $thread)
	{
		/**
		 * @var Account[] $models
		 */

		//if(!YII_DEBUG)
		//	die('ff');

		session_write_close();
		$this->checkAccess($pass);

		$threadName = 'linkEmail';
		$threadCount = 5;
		$isRandomOrder = true;
		$emailWarnCount = 100;
		$workTime = 50;

		//если за это время не пришло письмо то привязываем другое мыло + логируем смену
		$changeEmailInterval = AccountEmail::CHANGE_EMAIL_INTERVAL;

		if(Tools::threader($threadName . $thread))
		{
			$freeEmailCount = AccountEmail::freeModelCount();

			if($freeEmailCount <= $emailWarnCount)
			{
				if($freeEmailCount == 0)
					toLogError($threadName.': закончились email-ы', true);
				elseif(!AccountEmail::model()->find("`date_check`=0"))	//если нет непроверенных
					toLogError($threadName.': Внимание! осталось '.$freeEmailCount.' email-ов');
			}

			$threadCondition = Tools::threadCondition($thread, $threadCount);

			$minLimit = 1000;

			//is_ecomm - личные кошельки юзеров, к ним не крепить
			$models = Account::model()->findAll(array(
				'condition'=>"
					`error`=''
					AND `date_used`=0
					AND `limit_in`>=$minLimit
					AND `is_ecomm` = 0
					AND `enabled`=1
					AND `is_email` = 0 AND $threadCondition
				",
				'order'=>"`email_link_date` DESC, `date_check` DESC",	//сначала прикрепить рабочие аккаунты
				'limit'=>10,
			));

			if($isRandomOrder)
				shuffle($models);

			$doneCount = 0;

			foreach($models as $model)
			{
				if(Tools::timeIsOut($workTime))
					break;

				//паузы между авторизациями
				sleep(5);

				if($bot = $model->bot)
				{
					//проверить прикреплено ли мыло сейчас
					$isLinked = $bot->isEmailLinked();


					if($isLinked === true)
					{
						Account::model()->updateByPk($model->id, array('is_email'=>1));
						$doneCount++;
						continue;
					}
					elseif($isLinked !== false)
					{
						$msg = $threadName.'('.$model->login.'): '.$bot->error;
						echo '<br>'.$msg;
						toLog($msg);
						break;
						//continue;
					}

					$accountEmail = $model->email;

					//если небыло запроса на прикрепление
					if(!$model->email_link_date)
					{
						//если у аккаунта нет мыла то резервируем
						if(!$accountEmail)
						{
							if(!$accountEmail = AccountEmail::getFreeEmail())
							{
								$msg = $threadName.'('.$model->login.'): '.AccountEmail::$lastError;
								echo '<br>'.$msg;
								toLog($msg);
								break;
							}

							Account::model()->updateByPk($model->id, array('email_id'=>$accountEmail->id));
						}

						if($bot->linkEmail($accountEmail->email))
						{
							Account::model()->updateByPk($model->id, array('email_link_date'=>time()));

							$msg = $threadName.'('.$model->login.'): прикрепление почты '.$accountEmail->email;
							echo '<br>'.$msg;
							toLogRuntime($msg);
							sleep(30);
						}
						else
						{
							$msg = $threadName.'('.$model->login.'): '.$bot->error;
							echo '<br>'.$msg;
							toLog($msg);
							break;
							//continue
						}
					}
					elseif(time() - $model->email_link_date > $changeEmailInterval or $model->email->error)
					{

						//смена мыла по таймауту или если есть ошибка на мыле
						//todo: устранить этот дубльблок кода
						//todo: смена емейла если на мыло зайти не получается
						if(!$accountEmail = AccountEmail::getFreeEmail())
						{
							$msg = $threadName.'('.$model->login.'): '.AccountEmail::$lastError;
							echo '<br>'.$msg;
							toLog($msg);
							break;
						}

						Account::model()->updateByPk($model->id, array(
							'email_id'=>$accountEmail->id,
							'email_link_date'=>0,
						));

						$msg = $threadName.'('.$model->login.'): смена email';
						echo '<br>'.$msg;
						toLog($msg);
						continue;
					}

					//непрочитанные письма от qiwi
					$messages = $accountEmail->getMessages('qiwi.com', true);

					if($messages!==false)
					{
						if(count($messages)>0)
						{
							$message = current($messages);

							if(!preg_match('!email/create\.action\?code=(.+?)"!', $message['text'], $res))
								$message['text'] = base64_decode($message['text']);

							if(preg_match('!email/create\.action\?code=(.+?)"!', $message['text'], $res))
							{
								$emailCode = $res[1];

								if($bot->completeLinkEmail($emailCode))
								{
									Account::model()->updateByPk($model->id, array(
										'is_email'=>1,
									));

									$msg = $threadName.'('.$model->login.'): привязка мыла завершена';
									echo '<br>'.$msg;
									//toLog($msg);
									$doneCount++;
								}
								else
								{
									$msg = $threadName.'('.$model->login.'): ошибка завершения прикрепления '.$bot->error;
									echo '<br>'.$msg;
									toLog($msg);
								}
							}
							else
							{
								$msg = $threadName.'('.$model->login.'): link code not found '.$accountEmail->email.', text: '.$message['text'];
								echo '<br>'.$msg;
								toLog($msg);
							}
						}
						else
						{
							$msg = $threadName.'('.$model->login.'): unread messages not found: '.$accountEmail->email;
							echo '<br>'.$msg;
							toLog($msg);
							continue;
						}
					}
					else
					{
						$msg = $threadName.' model->login '.'('.$model->login.'): '.$accountEmail->botError;
						echo $msg;
						toLog($msg);
						break;
					}
				}
				else
				{
					$msg = $threadName.' '.$model->login.' error bot: '.$model->botError;
					echo '<br>'.$msg;
					toLog($msg);
					break;
					//continue;
				}
			}
		}
		else
			die('<br>поток уже запущен');

		echo '<br>done: '.$doneCount;
	}


	/**
	 * выплаты по StoreApi (в 1 поток)
	 * @param $pass
	 */
	public function actionStoreApiWithdraw($pass)
	{
		/**
		 * @var Account[] $models
		 */

		session_write_close();
		$this->checkAccess($pass);

		StoreApiWithdraw::startWithdraw();
	}

	/*
	 * авто-добавление
	 */
	public function actionAccountAutoAdd($pass)
	{

		session_write_close();
		$this->checkAccess($pass);

		Account::startAutoAdd();
	}

	/**
	 * обновляет курс usd(finam, btce-lastPrice) и btc-lastPrice
	 * @param string $pass
	 * @return null;
	 */
	public function actionUpdateRates($pass)
	{
		session_write_close();
		$this->checkAccess($pass);

		ClientCalc::startUpdateRates();
	}

	public function actionProxyReset($pass)
	{
		session_write_close();
		$this->checkAccess($pass);

		if(Proxy::startReset())
			echo 'ок';
		else
			echo 'ошибка: '.Proxy::$lastError;
	}

	public function actionAntiCaptcha($pass)
	{
		session_write_close();
		$this->checkAccess($pass);


		if(AntiCaptcha::startSolving())
			echo 'ок';
		else
		{
			echo 'ошибка: '.AntiCaptcha::$lastError;
		}
	}
/*
 * продление несгоревших сессий
 */
	public function actionSessionExtend($pass, $thread)
	{
		session_write_close();
		$this->checkAccess($pass);

		Account::sessionExtend($thread);
	}

	public function actionBanChecker($pass, $thread=0)
	{
		session_write_close();
		$this->checkAccess($pass);

		$checkCount = BanChecker::startCheck($thread);

		echo 'проверено: '.$checkCount.' кошельков';

		die(BanChecker::$lastError);
	}

	/**
	 * обновление информации по платежам qiwi на payeer
	 * @param $threadNumber
	 */
	public function actionQiwiCheck($pass, $threadNumber)
	{
		session_write_close();
		$this->checkAccess($pass);

		QiwiPay::startUpdateHistory($threadNumber);

		echo "\n done";
	}

	public function actionNextQiwiCheckOrders($pass)
	{
		session_write_close();
		$this->checkAccess($pass);
		NextQiwiPay::startCheckOrders();

		echo "\n done";
	}

	/**
	 * обновление информации по платежам qiwi на payeer
	 * @param $threadNumber
	 */
	public function actionNextQiwiBanChecker($pass, $threadNumber)
	{
		session_write_close();
		$this->checkAccess($pass);

		NextQiwiPay::checkWalletsBan($threadNumber);

		echo "\n done";
	}

	/**
	 *
	 * обновление информации по платежам qiwi merchant
	 * @param $threadNumber
	 */
	public function actionUpdateQiwiMerchant($pass)
	{
		session_write_close();
		$this->checkAccess($pass);
		MerchantTransaction::updateAllTransactions();

		echo "\n done";
	}


}