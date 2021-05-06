<?php

class AccountController extends Controller
{
	public $layout='//layouts/main';
	public $defaultAction = 'list';

	//список кошельков
	public function actionList()
	{
		$user = User::getUser();

		if(!$this->isGlobalFin() and !$this->isAdmin() and !$this->isSim())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];

		if($_POST['add'])
		{
			$addCount = SimAccount::addMany($params['loginStr'], $params['client_id']);

			if(!SimAccount::$lastError)
			{
				$this->success('добавлено '.$addCount.' кошельков');
				$this->refresh();
			}
			else
				$this->error('ошибка: '.SimAccount::$lastError);
		}
		elseif($_POST['changeStatus'])
		{
			//работает через аякс или просто через обычную отправку
			$isAjax = $_POST['isAjax'];
			$accountIds = explode(',', $params['account_id']);

			$error = '';
			$msg = '';
			
			foreach($accountIds as $accountId)
			{
				if($account = SimAccount::getModel(['id'=>$accountId]))
				{
					if($account->setStatus($params['status']))
					{
						$msg = 'статус кошелька '.$account->login.' изменен';

						if(!$isAjax)
							$this->success($msg);
					}
					else
					{
						$error = 'ошибка смены статуса';

						if(!$isAjax)
							$this->error($error);
					}
				}
				else
				{
					$error = 'выбранный кошелек не найден';
					$this->error($error);
					break;
				}
			}

			if($isAjax)
			{
				$this->renderPartial('//system/json', ['result'=>[
					'success'=>(!$error) ? 'true' : '',
				]]);
				Yii::app()->end();
			}
			else
			{
				if(!$error)
					$this->refresh();
			}
		}
		elseif($_POST['withdraw'])
		{
			$transParams = $params;

			//если списание то сумма отрицательная
			$transParams['amount'] = (float)trim($transParams['amount'], '-') * -1;

			$isAjax = $_POST['isAjax'];
			$error = '';

			if($account = SimAccount::getModel(['id' => $transParams['account_id']]))
			{
				if($account->addTransaction($transParams))
				{
					if(!$isAjax)
					{
						$this->success('списание на сумму ' . formatAmount($transParams['amount']) . ' сохранено');
						$this->refresh();
					}
				}
				else
				{
					$error = SimAccount::$msg;

					if(!$isAjax)
						$this->error($error);
				}
			}
			else
			{
				$error = 'кошелек не найден';
				$this->error($error);
			}

			if($isAjax)
			{
				$this->renderPartial('//system/json', ['result'=>[
					'success'=>(!$error) ? 'true' : '',
				]]);

				Yii::app()->end();
			}
		}
		elseif($_POST['filterItems'])
		{
			//редирект с GET-параметрами
			$filterParams = $_POST['filter'];
			$redirectParams = [];

			if($filterParams['status'])
				$redirectParams['filter[status]'] = implode(',', $filterParams['status']);

			if($filterParams['client_id'])
				$redirectParams['filter[client_id]'] = $filterParams['client_id'];

			$this->redirect('sim/account/list', $redirectParams);
		}
		elseif($_POST['limit'])
		{
			$isAjax = $_POST['isAjax'];
			$error = '';

			if($account = SimAccount::getModel(['id' => $params['account_id']]))
			{
				if($account->setLimitIn($params['limit']))
				{
					if(!$isAjax)
					{
						$this->success('лимит кошелька ' . $account->login . ' изменен');
						$this->refresh();
					}
				}
				else
				{
					$error = ' '.SimAccount::$msg;

					if(!$isAjax)
						$this->error($error);
				}
			}
			else
			{
				$error = 'кошелек не найден';

				if(!$isAjax)
					$this->error($error);
			}

			if($isAjax)
			{
				$this->renderPartial('//system/json', ['result'=>[
					'success'=>(!$error) ? 'true' : '',
				]]);

				Yii::app()->end();
			}

		}

		$filter = [
			'status' => ($_GET['filter']['status']) ? explode(',', $_GET['filter']['status']) : [],
			'phone' => $params['searchStr'],
			'client_id' => $_GET['filter']['client_id'],
		];

		$models = SimAccount::getModels(0, 0, $filter);
		$accountStats = SimAccount::getStats();
		$transactionStats = SimTransaction::getStats(
			Tools::startOfDay(),
			Tools::startOfDay(time() + 24*3600),
			0);

		if($_POST['ajaxUpdate'])
		{
			//простой html возвращаем
			$data = [
				'accounts' => $this->renderPartial('_accounts', [
					'models'=>$models,
					'transactionStats'=>$transactionStats,
				], true),
				'accountStats' => $this->renderPartial('_accountStats', [
					'accountStats'=>$accountStats,
				], true),
			];

			$this->renderPartial('//system/json', ['result'=>$data]);
		}
		else
		{
			$this->render('list', [
				'models' => $models,
				'params' => $params,
				'filter' => $filter,
				'accountStats' => $accountStats,
				'transactionStats' => $transactionStats,
			]);
		}

	}

	//списоко транзакций кошелька
	public function actionTransactions($accountId, $type='successIn')
	{
		$user = User::getUser();

		if(!$this->isGlobalFin() and !$this->isAdmin() and !$this->isSim())
			$this->redirect(cfg('index_page'));

		$account = SimAccount::getModel(['id'=>$accountId]);

		if(!$account)
			$this->redirect('sim/account/list');

		$timestampMin = time() - 3600*24*30;

		$params = $_POST['params'];

		if($_POST['deleteTransaction'])
		{
			if($account->deleteTransaction($params['transactionId']))
			{
				$this->success('платеж удален');
				$this->redirect('sim/account/transactions', ['accountId'=>$accountId]);
			}
			else
				$this->error('ошибка удаления платежа');
		}
		elseif($_POST['filter'])
		{
			$this->redirect('sim/account/transactions', [
				'accountId'=>$accountId,
				'dateStart'=>$params['dateStart'],
				'dateEnd'=>$params['dateEnd'],
			]);
		}

		$filter = [
			'dateStart' => ($_GET['dateStart']) ? $_GET['dateStart'] : date('d.m.Y H:i', Tools::startOfDay()),
			'dateEnd' => ($_GET['dateEnd']) ? $_GET['dateEnd'] : date('d.m.Y H:i', Tools::startOfDay(time()+3600*24)),
		];

		$timestampStart = strtotime($filter['dateStart']);
		$timestampEnd = strtotime($filter['dateEnd']);

		if($timestampStart < $timestampMin)
		{
			$timestampStart = $timestampMin;
			$filter['dateStart'] = date('d.m.Y H:i', $timestampStart);
			$this->error('максимальный интервал 30 дней');
		}

		$account = SimAccount::getModel(['id'=>$accountId]);

		if(!$account)
			$this->error('кошелек не найден');

		$models = $account->getTransactions($timestampStart, $timestampEnd, $type);

		$this->render('transactions', [
			'account' => $account,
			'models' => $models,
			'params' => $params,
			'filter' => $filter,
			'transactionsType' => $type,
		]);
	}

}