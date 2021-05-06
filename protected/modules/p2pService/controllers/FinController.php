<?php

class FinController extends Controller
{
	public $defaultAction = 'list';
	public $layout='//layouts/main';

	public function render($tpl, $params = null, $return = false)
	{
		$result = parent::render($tpl, $params);
		Yii::app()->end();
		return $result;
	}

	public function actionStatistic()
	{
		if(!$this->isFinansist() and !$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$session = &Yii::app()->session;
		$request = &Yii::app()->request;

		$params = Yii::app()->request->getPost('params');

		$user = User::getUser();

		if(!$user->client->checkRule('p2pService'))
			$this->redirect(cfg('index_page'));

		$interval = isset($session['p2pServiceStats']) ? $session['p2pServiceStats'] : [];

		if($_POST['statsByDate'])
		{
			$interval = $params;
			$session['p2pServiceStats'] = $interval;

			$this->redirect('p2pService/fin/statistic', [
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
			'dateStart' => ($_GET['dateStart']) ? $_GET['dateStart'] : date('d.m.Y H:i', Tools::startOfDay()),
			'dateEnd' => ($_GET['dateEnd']) ? $_GET['dateEnd'] : date('d.m.Y H:i', Tools::startOfDay(time()+3600*24)),
		];

		$timestampStart = strtotime($filter['dateStart']);
		$timestampEnd = strtotime($filter['dateEnd']);
		$timestampMin = time() - 3600*24*30;

		if($timestampStart < $timestampMin)
		{
			$timestampStart = $timestampMin;
			$filter['dateStart'] = date('d.m.Y H:i', $timestampStart);
			$this->error('максимальный интервал 30 дней');
		}

		$users = $user->client->users;
		$stats = [];

		foreach($users as $user)
		{
			$userTransactions = RisexTransaction::getModels(
				strtotime($interval['dateStart']),
				strtotime($interval['dateEnd']),
				$user->client->id,
				$user->id,
				false
			);

			if(count($userTransactions) < 1)
				continue;

			$userStats = RisexTransaction::getStatsUser($userTransactions);
			$stats[$user->name]['transactions'] = $userTransactions;
			$stats[$user->name]['stats'] = $userStats;

		}

		$this->render('statistic', [
			'filter'=>[
				'dateStart' => $filter['dateStart'],
				'dateEnd' => $filter['dateEnd'],
			],
			'params'=>$params,
			'stats'=>$stats,
			'interval'=>$interval,
			'user' => $user,
		]);
	}
}