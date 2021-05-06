<?php

class PanelController extends Controller
{
	
	public function actionLog($category = false)
	{
		if(!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$categories = Tools::logCategories();

		if(!$category)
			$category = current($categories);

		$logRows = count(explode("\n", file_get_contents(cfg('logDir').$category.'.txt')));

		//ajax-обновление логов
		if(isset($_GET['update']))
		{
			$rowCount = $_POST['rowCount'];

			if($logRows != $rowCount)
				$content = Tools::logOut($rowCount, $category);
			else
				$content = false;

			preg_match('!<strong>.+?</strong>\s+(.+)!', $content, $match);

			$this->renderPartial('//system/json', array(
				'result'=>array(
					'content'=>$content,
					'rowCount'=>$logRows,
					'msg'=>'',
				),
			));
		}
		else
		{
			//очистка логов
			if(isset($_GET['clear']))
			{
				file_put_contents(cfg('logDir').$category.'.txt', '');
				Tools::log('логи очищены', false, false, $category);
				$this->redirect('panel/log', ['category'=>$category]);
			}

			$this->render('log', array(
				'logs'=>Tools::logOut(0, $category),
				'rowCount'=>$logRows,
				'categories'=>$categories,
				'currentCategory'=>$category,
			));
		}
	}

	/**
	 * @param bool $category
	 * отличается от обычных логов тем, что ошибки сгруппированы и отсортированы
	 * TODO: добавить аякс обновление
	 */
	public function actionLogSorted($category = false)
	{
		if(!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$categories = Tools::logCategories();

		if(!$category)
			$category = current($categories);

		//очистка логов
		if(isset($_GET['clear']))
		{
			file_put_contents(cfg('logDir').$category.'.txt', '');
			Tools::log('логи очищены', false, false, $category);
			$this->redirect('panel/logSorted', ['category'=>$category]);
		}

		$this->render('logSorted',[
			'logs'=>Tools::logOutSorted(0, $category),
			'categories'=>$categories,
			'currentCategory'=>$category,
		]);
	}
	
	public function actionControl()
	{
		if(!YII_DEBUG)
			die('old');
		
		if(!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		//кошельки с ненулевым балансом
		$balanceOutModels = Account::model()->findAll("`error`='".Account::ERROR_NOT_NULL_BALANCE."'");

		$balanceOutAmount = 0;

		foreach($balanceOutModels as $model)
			$balanceOutAmount += $model->balance;

		$params = $_POST['params'];
			
		if($_POST['clear_account_cookies'])
		{
			//очистить все куки
			//if(YII_DEBUG)
			//{
				if(QiwiBot::clearAllCookies())
					$this->success('куки всех аккаунтов удалены');
				else
					$this->success('ошибка очистки куки');
			//}
			//else
			//	$this->error('сначала нужно отключить сайт');
		}
		elseif($_POST['update_limit'])
		{
			//обновить лимиты у аккаунтов
			
			if(!Tools::threader('update_limit'))
				die('already run');
			
			if(!is_array($_SESSION['temp']['accounts']))
				$_SESSION['temp']['accounts'] = array();
			
			if(YII_DEBUG)
			{
				$accounts = Account::model()->findAll("`type`='in' or `type`='transit'");
				
				$doneCount = 0;
				
				foreach($accounts as $account)
				{
					if(Tools::timeOut())
						break;
					
					if(!in_array($account->id, $_SESSION['temp']['accounts']))
					{
						if($account->updateQiwiLimit())
						{
							$doneCount++;
							$_SESSION['temp']['accounts'][] = $account->id;
						}
						else
						{
							$this->error($account->login.': '.$account::$lastError);
							break;
						}
					}
				}
				
				$this->success('обновлено лимитов: '.$doneCount);
			}
			else
				$this->error('сначала нужно отключить сайт');
		}
		elseif($_POST['balance_out'])
		{
			die('closed');
			//слить с balance_out-кошелей

			//чтобы скрипт не вылетел по таймауту
			$atOnceAmount = 100000;
			$sentAmount = 0;

			$config = cfg('balance_out');
			$minBalance = cfg('min_balance');

			$to = $config[array_rand($config)];

			foreach($balanceOutModels as $model)
			{
				if(Tools::timeOut())
					break;

				$bot = new QiwiBot($model->login, $model->pass);
				$bot->pause = 0;

				if(!$bot->error)
				{
					$balance = $bot->getBalance();

					if($balance!==false and $balance > $minBalance)
					{
						$amount = $balance;

						//die($balance.' '.$model->login);
						$security = $bot->hasLockedSecurity();

						if($security === false)
						{
							if($balance > $atOnceAmount)
								$amount = $atOnceAmount;
							elseif($amount < $minBalance)
								$amount = $minBalance;

							$successAmount = $bot->sendMoney($to, $amount);

							if($successAmount!==false)
							{
								$sentAmount += $successAmount;

								$allAmount = config('balance_out_amount');

								$model->updateBalance($sentAmount, 'withdraw');

								config('balance_out_amount', $allAmount + $sentAmount);
							}
							else
							{
								$this->error($bot->error);
								break;
							}
						}
						elseif($security===true)
						{
							$model::model()->updateByPk($model->id, array(
								'balance'=>$balance,
								'error'=>$model::ERROR_SMS,
							));

							$this->error('включены смс на '.$model->login);
							break;
						}
						else
						{
							$this->error('ошибка проверки смс, попробуйте еще раз'.$model->login);
							break;
						}
					}
					elseif($balance < $minBalance)
					{
						$model::model()->updateByPk($model->id, array(
							'balance'=>$balance,
							'error'=>$model::ERROR_IDENTIFY,
						));
					}
					else
					{
						$this->error($bot->error);
						break;
					}

				}
				elseif($balance!==false and $balance < $minBalance)
				{
					$model::model()->updateByPk($model->id, array(
						'balance'=>$balance,
						'error'=>$model::ERROR_IDENTIFY,
					));
				}
				else
				{
					$this->error($bot->error);
					break;
				}
			}

			$this->success('выведено на '.$to.': '.$sentAmount);

			$this->redirect('panel/control');

		}

		
		
		$this->render('control', array(
			'params'=>$params,
			'balanceOutModels'=>$balanceOutModels,
			'balanceOutAmount'=>$balanceOutAmount,
		));
	}

	public function actionSearchYandexPayment()
	{
		if(!$this->isGlobalFin() and !$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$session = &Yii::app()->session;
		$request = &Yii::app()->request;


		$user = User::getUser();
		$params = $request->getPost('params');

		$models = [];

		if($_POST['search'])
		{
			$searchStr = trim(strip_tags($_POST['searchStr']));

			$condition = "`comment` LIKE '%" . $searchStr . "%' OR `unique_id` LIKE '%" . $searchStr . "%'";

			$models = NewYandexPay::model()->findAll($condition);

		}
		if($_POST['search'])
		{
			$searchStr = trim(strip_tags($_POST['searchStr']));

			$condition = "`comment` LIKE '%" . $searchStr . "%' OR `unique_id` LIKE '%" . $searchStr . "%'";

			$models = NewYandexPay::model()->findAll($condition);

		}
		elseif($_POST['searchById'])
		{
			$models = NewYandexPay::model()->findAllByPk(trim($_POST['id']));
		}
		elseif($_POST['confirm'])
		{
			if($model = NewYandexPay::getModel(['id'=>$params['id']]))
			{
				if($model->confirmManual())
				{
					$this->success('платеж '.$model->unique_id.' подтвержден');
					$this->redirect('panel/SearchYandexPayment');
				}

				if($model::$lastError)
					$this->error($model::$lastError);
			}

		}

		$this->render('searchYandexPayment', [
			'models'=>$models,
			'statsYandex'=>NewYandexPay::getStats($models),
			'params'=>$params,
		]);
	}
}