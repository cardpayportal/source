<?php

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
			$this->redirect(cfg('index_page'));

		$session = &Yii::app()->session;
		$request = &Yii::app()->request;

		$params = $request->getPost('params');

		$user = User::getUser();

		if(!$user->client->checkRule('p2pService'))
			$this->redirect(cfg('index_page'));

		$interval = isset($session['p2pServiceStats']) ? $session['p2pServiceStats'] : [];

		if($params['dateStart'] and $params['dateEnd'])
		{
			$interval = $params;
			$session['p2pServiceStats'] = $interval;
			$this->redirect('p2pService/manager/list');
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

		if($_POST['exchange'])
		{
			if(!RisexTransaction::createDeal($user, $params['amount']*1))
			{
				toLogError('p2pService: '.RisexTransaction::$lastError);
				if(preg_match('!No deal available!iu', RisexTransaction::$lastError, $matches))
				{
					$this->error('Ошибка, за получением карты обратитесь к оператору');
				}
				else
					$this->error('Ошибка, обратитесь к оператору');
			}
			else
			{
				$this->success('Создана сделка на сумму '.$params['amount']*1);
				$this->redirect('p2pService/manager/list');
			}
		}
		elseif($_POST['acceptPayment'])
		{
			if(!RisexTransaction::acceptPayment($params['transId']))
			{
				toLogError('p2pService: '.RisexTransaction::$lastError);
				$this->error('Ошибка подтверждения, обратитесь к оператору');
			}
			else
			{
				$this->success('Платеж подтвержден');
				$this->redirect('p2pService/manager/list');
			}
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
				$this->redirect('p2pService/manager/list');
			}
		}

		if($user->client->checkRule('pagination'))
		{
			$transactionsArr = RisexTransaction::getModelsForPagination(
				strtotime($interval['dateStart']),
				strtotime($interval['dateEnd']),
				$user->client_id,
				$user->id,
				false
			);

			$transactions = $transactionsArr['models'];
			$pages = $transactionsArr['pages'];
		}
		else
		{
			$transactions = RisexTransaction::getModels(
				strtotime($interval['dateStart']),
				strtotime($interval['dateEnd']),
				$user->client_id,
				$user->id,
				false
			);

			$pages = [];
		}

		$this->render('list', [
			'transactions' => $transactions,
			'stats'=>RisexTransaction::getStats(strtotime($interval['dateStart']),
				strtotime($interval['dateEnd']), $user->client_id, $user->id),
			'params'=>$params,
			'interval'=>$interval,
			'pages' => $pages,
			'user' => $user,
		]);

	}
}