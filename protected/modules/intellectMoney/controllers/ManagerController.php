<?php

class ManagerController extends Controller
{
	public $layout='//layouts/main';
	public $defaultAction='accountList';

	/**
	 * список использованных кошельков для манагера
	 * возрождение киви
	 */
	public function actionTransactionList()
	{
		$cfg = cfg('intellectMoney');

//		if(!$this->isManager() and !$this->isFinansist() and !$this->isAdmin())
//			$this->redirect(cfg('index_page'));

		$session = &Yii::app()->session;
		$request = &Yii::app()->request;

		$interval = isset($session['intellectStats']) ? $session['intellectStats'] : [];
		$user = User::getUser();

		//не включен модуль у клиентов
//		if($user->client->checkRule('intellectMoney'))
//			Yii::app()->getRequest()->redirect(url(cfg('index_page')));

		$params = Yii::app()->request->getPost('params');

		$payUrl = '';

		if($request->getPost('getPayParams'))
		{
			$client = $user->client;

			$params = [
					'amount' => $params['amount'],
					'orderId' => uniqid(rand(),1),
				];

			$apiKey = $user->api_key;
			$payUrl = IntellectTransaction::getPayUrl($params, $apiKey);

			if(!$payUrl)
				$this->error(IntellectTransaction::$lastError);
		}

		if($params['dateStart'] and $params['dateEnd'])
		{
			$interval = $params;
			$session['intellectStats'] = $interval;
			$this->redirect('intellectMoney/manager/transactionList');
		}
		else
		{
			if($session['intellectStats'])
				$interval = $session['intellectStats'];
			else
			{
				$interval = [
					'dateStart' => date('d.m.Y H:i', Tools::startOfDay(time())),
					'dateEnd' => date('d.m.Y H:i', time()+24*3600),
				];

				$session['intellectStats'] = $interval;
			}
		}

		$models = IntellectTransaction::getModels(
			strtotime($interval['dateStart']),
			strtotime($interval['dateEnd']),
			$user->client_id,
			$user->id,
			false
		);

		$this->render('transaction_list', [
			'models'=>$models,
			'statsIntellect'=>IntellectTransaction::getStatsByInterval(strtotime($interval['dateStart']),
				strtotime($interval['dateEnd']), $user->id, $user->client_id),
			'params' => $params,
			'interval'=>$interval,
			'payUrl' => $payUrl,
		]);
	}

	public function render($tpl, $params = null, $return = false)
	{
		return  parent::render($tpl, $params);
	}
}