<?php

class ManagerController extends Controller
{
	public $layout='//layouts/main';
	public $defaultAction='accountList';

	/**
	 * список использованных кошельков для манагера
	 * возрождение киви
	 */
	public function actionAccountList()
	{
		$cfg = cfg('yandexAccount');

		if(!$this->isManager() and !$this->isFinansist() and !$this->isAdmin())
			$this->redirect(cfg('index_page'));

		session_write_close();

		$session = &Yii::app()->session;
		$request = &Yii::app()->request;

		$user = User::getUser();

		$wallets = $user->yandexAccounts;

		$params = Yii::app()->request->getPost('params');

		$interval = isset($session['yandexStats']) ? $session['yandexStats'] : [];

		if($params['dateStart'] and $params['dateEnd'])
		{
			$interval = $params;

			$session['yandexStats'] = $interval;
			$this->redirect('yandexAccount/manager');
		}
		else
		{
			if($session['qiwiMerchantStats'])
				$interval = $session['qiwiMerchantStats'];
			else
			{
				$interval = [
					'dateStart' => date('d.m.Y H:i', Tools::startOfDay(time())),
					'dateEnd' => date('d.m.Y H:i', time()+24*3600),
				];

				$session['yandexStats'] = $interval;
			}
		}

		$userId = (!$this->isManager()) ? 0 : $user->id;
		$clientId = (!$this->isManager()) ? $user->client_id : 0;

		//статистика: по меткам или просто За вчера и За сегодня
		$todayDateStart = strtotime(date('d.m.Y'));
		$todayDateEnd = $todayDateStart+3600*24 - 1;

		$yesterdayDateStart = $todayDateStart - 3600*24;
		$yesterdayDateEnd = $todayDateStart - 1;

		$stats = [];

		if($this->isFinansist())
		{
			$allUsers = $user->client->getUsers();
			foreach($allUsers as $oneUser)
			{
				$totalDayStat = YandexTransaction::getStats($todayDateStart, $todayDateEnd, 0, $oneUser->id);
				$stats['today'] += $totalDayStat['amountIn'];
				$totalMonthStat = YandexTransaction::getStats($yesterdayDateStart, $yesterdayDateEnd, 0, $oneUser->id);
				$stats['yesterday'] += $totalMonthStat['amountIn'];
			}
		}
		else
		{
			$totalDayStat = YandexTransaction::getStats($todayDateStart, $todayDateEnd, 0, $user->id);
			$stats['today'] += $totalDayStat['amountIn'];
			$totalMonthStat =  YandexTransaction::getStats($yesterdayDateStart, $yesterdayDateEnd, 0, $user->id);
			$stats['yesterday'] += $totalMonthStat['amountIn'];
		}

		$statsType = 'simple';

		$this->render('history', [
			'stats'=>$stats,
			'statsType'=>$statsType,
			'user'=>$user,
			'wallets'=>$wallets,
			'params'=>$params,
			'interval'=>$interval,
		]);
	}

	public function render($tpl, $params = null, $return = false)
	{
		return  parent::render($tpl, $params);
	}
}