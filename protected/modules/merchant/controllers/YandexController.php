<?php
/**
 * работа с апи мерчантом яндекса
 */

class YandexController extends Controller
{
	public $layout='//layouts/main';
	public $defaultAction = 'history';

	public function actionHistory()
	{
		$user = User::getUser();

		if(!$this->isManager() and !$this->isFinansist() and !$this->isAdmin() and !$user->client->checkRule('walletS'))
			$this->redirect(cfg('index_page'));

		$session = &Yii::app()->session;
		$request = &Yii::app()->request;

		$user = User::getUser();

		$wallets = $user->yandexMerchantWallets;

		if($user->theme == 'flat' and $user->saveProfile(['theme'=>'basic']))
		{
			$this->redirect('merchant/yandex/history');
		}

		$params = $request->getPost('params');

		$interval = isset($session['yandexMerchantStats']) ? $session['yandexMerchantStats'] : [];

		if($params['dateStart'] and $params['dateEnd'])
		{
			$interval = $params;

			$session['yandexMerchantStats'] = $interval;
			$this->redirect('merchant/yandex/history');
		}
		else
		{
			if($session['yandexMerchantStats'])
				$interval = $session['yandexMerchantStats'];
			else
			{
				$interval = [
					'dateStart' => date('d.m.Y H:i', Tools::startOfDay(time())),
					'dateEnd' => date('d.m.Y H:i', time()+24*3600),
				];

				$session['yandexMerchantStats'] = $interval;
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

		$walletType = ['yandex'];

		if($this->isFinansist())
		{
			$allUsers = $user->client->getUsers();
			foreach($allUsers as $oneUser)
			{
				$stats['today'] += MerchantTransaction::managerStats($todayDateStart, $todayDateEnd, $oneUser->id, $walletType);
				$stats['yesterday'] += MerchantTransaction::managerStats($yesterdayDateStart, $yesterdayDateEnd, $oneUser->id, $walletType);
			}
		}
		else
		{
			$stats['today'] += MerchantTransaction::managerStats($todayDateStart, $todayDateEnd, $user->id, $walletType);
			$stats['yesterday'] += MerchantTransaction::managerStats($yesterdayDateStart, $yesterdayDateEnd, $user->id, $walletType);
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
}