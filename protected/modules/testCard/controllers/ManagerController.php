<?php
/**
 */

class ManagerController extends Controller
{
	public $defaultAction = 'list';
	public $layout='//layouts/main';

	public function render($tpl, $params = null, $return = false)
	{
		$result = parent::render($tpl, $params);
		Yii::app()->end();
		return $result;
	}

	public function actionList()
	{
		if(!$this->isManager() and !$this->isFinansist() and !$this->isAdmin())
		{
			$this->redirect(cfg('index_page'));
		}

		$user = User::getUser();

		if(!$user->client->checkRule('testCard'))
			$this->redirect(cfg('index_page'));

		$session = &Yii::app()->session;
		$request = &Yii::app()->request;

		$params = $request->getPost('params');

		$interval = isset($session['testCardStats']) ? $session['testCardStats'] : [];

		$params = Yii::app()->request->getPost('params');

		if($params['dateStart'] and $params['dateEnd'])
		{
			$interval = $params;
			$session['testCardStats'] = $interval;
			$this->redirect('testCard/manager/list');
		}
		else
		{
			if($session['testCardStats'])
				$interval = $session['testCardStats'];
			else
			{
				$interval = [
					'dateStart' => date('d.m.Y H:i', Tools::startOfDay(time())),
					'dateEnd' => date('d.m.Y H:i', time()+24*3600),
				];

				$session['testCardStats'] = $interval;
			}
		}

		$models = TestCardModel::getUserModels($user->id);

		$transactions = TestTransactionModel::getModels(
			strtotime($interval['dateStart']),
			strtotime($interval['dateEnd']),
			$user->client_id,
			$user->id);

		$stats = TestTransactionModel::getStatsUser($transactions);

		$this->render('list', [
			'models' => $models,
			'stats' => $stats,
			'params' => $params,
			'interval'=>$interval,
		]);
	}


}