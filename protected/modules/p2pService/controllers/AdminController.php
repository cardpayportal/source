<?php
/**
 */

class AdminController extends Controller
{
	public $defaultAction = 'list';
	public $layout='//layouts/main';

	public function render($tpl, $params = null, $return = false)
	{
		$result = parent::render($tpl, $params);
		Yii::app()->end();
		return $result;
	}

	public function actionList($clientId=false)
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$session = &Yii::app()->session;
		$request = &Yii::app()->request;

		$interval = isset($session['p2pServiceStats']) ? $session['p2pServiceStats'] : [];

		$user = User::getUser();

		$params = Yii::app()->request->getPost('params');

		if($_POST['statsByDate'])
		{
			$clientIds = ($params['clientId']) ? $params['clientId'] : [];

			$interval = $params;
			$session['p2pServiceStats'] = $interval;

//			var_dump($clientIds);die;

			$this->redirect('p2pService/admin/list', [
				'clientId'=>implode(',', $clientIds),
				'dateStart'=>$params['dateStart'],
				'dateEnd'=>$params['dateEnd'],
			]);
		}
		else
		{
			if($session['p2pServiceStats'])
				$interval = $session['p2pServiceStats'];
			else
			{
				$interval = [
					'dateStart' => date('d.m.Y H:i', Tools::startOfDay(time())),
					'dateEnd' => date('d.m.Y H:i', time()+24*3600),
				];

				$session['p2pServiceStats'] = $interval;
			}
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

		if($_POST['exchange'])
		{
			if(!RisexTransaction::createDeal($user, $params['amount']*1))
				$this->error(RisexTransaction::$lastError);
			else
				$this->success('Создана сделка на сумму '.$params['amount']*1);
		}
		elseif($_POST['cancelPayment'])
		{
			if(!RisexTransaction::cancelPayment($params['transId']))
			{
				toLogError('p2pService: '.RisexTransaction::$lastError);
				$this->error('Ошибка отмены, обратитесь к оператору');
			}
			else
			{
				$this->success('Платеж отменен');
				$this->redirect('p2pService/admin/list');
			}
		}

		$data = [];

		if(isset($clientId))
		{
			/**
			 * @var Client $client
			 */
			foreach($clientIds as $key=>$clientId)
			{
				$client = Client::modelByPk($clientId);

				if(!$client)
					continue;

				$users = $client->users;

				if(!$users)
					continue;

				$transactions = [];

				foreach($users as $user)
				{
					$userTransactions = RisexTransaction::getModels(
						strtotime($interval['dateStart']),
						strtotime($interval['dateEnd']),
						$client->id,
						$user->id,
						false
					);

					if(count($userTransactions) < 1)
						continue;

					$userStats = RisexTransaction::getStatsUser($userTransactions);

					$transactions[$user->name]['transactions'] = $userTransactions;
					$transactions[$user->name]['stats'] = $userStats;
				}

				$data[$client->name] = $transactions;
			}

		}
		else
			$clientIds = [];

		$filter = [];

		$this->render('list', [
			'filter'=>[
				'clientIds' => $clientIds,
				'dateStart' => $filter['dateStart'],
				'dateEnd' => $filter['dateEnd'],
			],
			'params'=>$params,
			'data'=>$data,
			'interval'=>$interval,
			'user' => $user,
		]);

	}



	/**
	 * метод вызывается ajax, нужен для динамической подгрузки пользователей в выпадающий список в форме
	 */
	public function actionLoadUsers()
	{
		$data = User::model()->findAll('client_id=:client_id and role in(:fin, :manager)', [
			':client_id' => (int)$_POST['client_id'],
			':fin' => User::ROLE_FINANSIST,
			':manager' => User::ROLE_MANAGER,
		]);

		$data = CHtml::listData($data, 'id', 'login');

		echo "<option value=''>Select User</option>";

		foreach($data as $value => $login)
		{
			echo CHtml::tag('option', ['value' => $value], CHtml::encode($login), true);
		}
	}

}