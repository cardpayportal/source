<?php
/**
 * работа с апи мерчантом киви
 */

class QiwiController extends Controller
{
	public $defaultAction = 'history';
	public $layout='//layouts/main';

	public function actionHistory()
	{
		if(!$this->isManager() and !$this->isFinansist() and !$this->isAdmin())
			$this->redirect(cfg('index_page'));

		session_write_close();

		$session = &Yii::app()->session;
		$request = &Yii::app()->request;

		$user = User::getUser();

		$wallets = $user->qiwiMerchantWallets;

		if($user->theme == 'flat' and $user->saveProfile(['theme'=>'basic']))
		{
			$this->redirect('merchant/qiwi/history');
		}

		$params = $request->getPost('params');

		$interval = isset($session['qiwiMerchantStats']) ? $session['qiwiMerchantStats'] : [];

		if($params['dateStart'] and $params['dateEnd'])
		{
			$interval = $params;

			$session['qiwiMerchantStats'] = $interval;
			$this->redirect('merchant/qiwi/history');
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

				$session['qiwiMerchantStats'] = $interval;
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

		$walletType = ['qiwi_card', 'qiwi_wallet'];

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

		if($_POST['assingWallet'])
		{
			if(MerchantUser::assignWallet($_POST['id']))
			{
				$this->success('Успешно взят новый номер, Обновите страницу');
				$this->redirect('merchant/qiwi/history');
			}
			else
			{
				$this->error('Ошибка, не удалось взять номер '.MerchantUser::$lastError);
				$this->redirect('merchant/qiwi/history');
			}

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