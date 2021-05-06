<?php

class TransactionWalletSController extends Controller
{
	public $layout='//layouts/main';
	public $defaultAction = 'list';

	//список транзакций
	public function actionList()
	{
		$user = User::getUser();

		if(!$user->client->checkRule('walletS'))
			$this->redirect(cfg('index_page'));

		if(!$this->isGlobalFin() and !$this->isAdmin() and !$this->isManager())
			$this->redirect(cfg('index_page'));

		$timestampMin = time() - 3600*24*30;

		$params = $_POST['params'];

		$session = &Yii::app()->session;
		$request = &Yii::app()->request;

		$interval = isset($session['walletSStats']) ? $session['walletSStats'] : [];

		if($params['dateStart'] and $params['dateEnd'])
		{
			$interval = $params;
			$session['walletSStats'] = $interval;
			$this->redirect('walletS/TransactionWalletS/list');
		}
		else
		{
			if($session['walletSStats'])
				$interval = $session['walletSStats'];
			else
			{
				$interval = [
					'dateStart' => date('d.m.Y H:i', Tools::startOfDay(time())),
					'dateEnd' => date('d.m.Y H:i', time()+24*3600),
				];

				$session['walletSStats'] = $interval;
			}
		}

		$userId = (!$this->isManager()) ? 0 : $user->id;
		$clientId = (!$this->isManager()) ? $user->client_id : 0;

		if($user->client->checkRule('pagination'))
		{
			$walletSArr = WalletSTransaction::getModelsForPagination(
				strtotime($interval['dateStart']),
				strtotime($interval['dateEnd']),
				$clientId,
				$userId,
				false
			);

			$models = $walletSArr['models'];
			$pages = $walletSArr['pages'];
		}
		else
		{
			$models = WalletSTransaction::getModels(
				strtotime($interval['dateStart']),
				strtotime($interval['dateEnd']),
				$clientId,
				$userId
			);

			$pages = ['header' => '',];
		}

		$this->render('transactions', [
			'models' => $models,
			'pages' => $pages,
			'params' => $params,
			'interval'=>$interval,
		]);
	}

}