<?php

class TransactionController extends Controller
{
	public $layout='//layouts/main';
	public $defaultAction = 'list';

	//список транзакций
	public function actionListTest()
	{
		if(!$this->isManager() and !$this->isFinansist())
			$this->redirect(cfg('index_page'));

		$user = User::getUser();

		if(!$user->client or !$user->client->checkRule('sim'))
			$this->redirect(cfg('index_page'));

		$session = &Yii::app()->session;
		$request = &Yii::app()->request;

		$user = User::getUser();
		$params = $request->getPost('params');

		$filter = isset($session['simStats']) ? $session['simStats'] : [];

		$payUrl = '';

		if($request->getPost('pay'))
		{
			
		}
		elseif($params['dateStart'] and $params['dateEnd'])
		{
			$filter = $params;

			$session['yandexStats'] = $filter;
			$this->redirect('manager/newYandexPay');
		}
		else
		{
			if($session['yandexStats'])
				$filter = $session['yandexStats'];
			else
			{
				$filter = [
					'dateStart' => date('d.m.Y H:i', Tools::startOfDay(time())),
					'dateEnd' => date('d.m.Y H:i', time()+24*3600),
				];

				$session['yandexStats'] = $filter;
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
					'interval'=>$filter,
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
					strtotime($filter['dateStart']),
					strtotime($filter['dateEnd']),
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
					strtotime($filter['dateStart']),
					strtotime($filter['dateEnd']),
					$userId,
					$clientId,
					false
				);

				$pages = [];
			}

		}

		$this->render('list', [
			'models'=>$models,
			'statsYandex'=>NewYandexPay::getStats($models),
			'params'=>$params,
			'filter'=>$filter,
			'payUrl' => $payUrl,
			'pages' => $pages,
		]);

	}

	//списоко транзакций кошелька
	public function actionTransactions($accountId, $type='successIn')
	{
		$user = User::getUser();

		if(!$user->client->checkRule('sim'))
			$this->redirect(cfg('index_page'));

		if(!$this->isManager() and !$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$account = CardAccount::getModel(['id'=>$accountId, 'user_id'=>$user->id]);

		if(!$account)
			$this->redirect('card/account/list');

		$timestampMin = time() - 3600*24*30;

		$params = $_POST['params'];

		if($_POST['deleteTransaction'])
		{
			if($account->deleteTransaction($params['transactionId']))
			{
				$this->success('платеж удален');
				$this->redirect('card/account/transactions', ['accountId'=>$accountId]);
			}
			else
				$this->error('ошибка удаления платежа');
		}
		elseif($_POST['filter'])
		{
			$this->redirect('card/account/transactions', [
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

		$account = CardAccount::getModel(['id'=>$accountId]);

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

	public function actionList()
	{

	}

}