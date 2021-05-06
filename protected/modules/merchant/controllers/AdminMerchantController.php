<?php
/**
 * админка с апи мерчантом
 */

class AdminMerchantController extends Controller
{
	public $defaultAction = 'userList';
	public $layout='//layouts/main';

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

	/**
	 * @param string $deleteUserId
	 *
	 * @return mixed
	 *
	 * операции с пользователями мерчанта
	 */
	public function actionUserList($deleteUserId='')
	{
		session_write_close();
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$user = User::getUser();

		$params = $_POST['params'];

		if($_POST['add'])
		{
			if(!$_POST['client_id'])
				$this->error('Выберите клиента');
			elseif(!$_POST['user_id'])
				$this->error('Выберите пользователя');
			elseif(!$_POST['params']['email'])
				$this->error('Введите почту');
			else
			{
				$params['user_id'] = $_POST['user_id'];

				if(MerchantUser::addUser($params))
				{
					$params['email'] = '';
					$this->success('Успешно добавлен новый пользователь');
				}
				else
					$this->error('Ошибка добавления пользователя: '.MerchantUser::$lastError);
			}
		}
		elseif($_POST['assingWallet'])
		{
			if(MerchantUser::assignWallet($_POST['id']))
				$this->success('Успешно привязан новый номер');
			else
				$this->error('Ошибка, не удалось привязать номер '.MerchantUser::$lastError);

		}

		if($deleteUserId)
		{
			if(MerchantUser::deleteUser($deleteUserId))
			{
				$this->success('Пользователь удален');
				$this->redirect('merchant/adminMerchant/userList');
			}
			else
			{
				$this->error('Ошибка удаления '.MerchantUser::$lastError);
				$this->redirect('merchant/adminMerchant/userList');
			}
		}

		$models = MerchantUser::getAll();

		return $this->render('userList', [
			'models' => $models,
			'params' => $params,
			'user'=>$user,
			'userName'=>$userName=$user->name,
		]);
	}

	/**
	 * @return mixed
	 * список входящих платежей мерчанта
	 */
	public function actionMerchantTransaction()
	{
		session_write_close();
		if(!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$models = MerchantTransaction::getTransactions();

		return $this->render('merchantTransaction', [
			'models' => $models,
		]);
	}

	/**
	 * @param string $userId
	 *
	 * @return mixed
	 * список кошей для прямого залива
	 */
	public function actionDirectWalletList($userId='')
	{
		session_write_close();
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];

		$wallets = MerchantUser::directWalletList($userId='');

		if($_POST['addQiwiCardStr'])
		{
			$user = User::getUser();
			if(MerchantCard::addManyQiwiCard($params['qiwiCardStr'], $user->id))
				$this->success('Карты добавлены');
			else
				$this->error('Ошибка добавления карт '.MerchantWallet::$lastError);
		}

		return $this->render('directWalletList', [
			'wallets' => $wallets,
		]);
	}

	/**
	 * @param string $userId
	 *
	 * @return mixed
	 * список всех кошей в работе
	 */
	public function actionWalletList()
	{
		session_write_close();
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];

		session_start();

		$wallets = MerchantUser::walletList($_SESSION['showData'], $_SESSION['showType']);

		if($_POST['deasignButton'])
		{
			if(MerchantUser::deassignWallet($params['internalUserId'], $params['internalWalletId']))
			{
				//$this->redirect('walletList', ['wallets' => $wallets,]);
				$this->success('Кошелек '.$params['internalWalletId'].' отвязан');
				return $this->render('walletList', [
					'wallets' => $wallets,
				]);
			}
			else
				$this->error('Ошибка отвязки кошелька');
		}
		elseif($_POST['assign'])
		{
			/**
			 * @var MerchantUser $wallet
			 */
			if($merchantUser = MerchantUser::model()->findByAttributes(['uni_user_id'=>(int)						$_POST['userId']]))
			{
				if(@MerchantUser::assignSpecialWallet($merchantUser->internal_id, 						$_POST['internaWalletlId']))
				{
					$this->success('Кошелек/карта '.$_POST['internalWalletId'].' привязан');
					$this->redirect('merchant/adminMerchant/walletList');
				}
				else
					$this->error('Ошибка привязки');
			}
			else
				$this->error('Ошибка привязки кошелька/карты: создайте сначала пользователя');

		}
		elseif($_POST['acceptFilter'])
		{
			$_SESSION['showData'] = $_POST['showData'];
			$_SESSION['showType'] = $_POST['showType'];
			$this->redirect('merchant/adminMerchant/walletList');
		}

		return $this->render('walletList', [
			'wallets' => $wallets,
		]);
	}

	/**
	 * получаем список кошельков привязанных к конкретному пользователю
	 */
	public function actionUserWalletList($merchantUserId)
	{
		session_write_close();
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		/**
		 * @var MerchantUser $model
		 */
		$model = MerchantUser::model()->findByPk($merchantUserId);

		$accounts = [];
		$userName = '';

		if($model)
		{
			$accounts = $model->getWalletList();
			$userName = $model->user->name;
		}

		if($_POST['markOld'])
		{
			if(MerchantWallet::markOld($_POST['id']))
			{
				$this->success('Кошелек отправлен в остойник');
				$this->redirect('merchant/adminMerchant/userWalletList', 					['merchantUserId'=>$merchantUserId]);
			}
			else
				$this->error('Кошелек не найден '.MerchantWallet::$lastError);
		}

		return $this->render('userWalletList', [
			'userName'=>$userName,
			'accounts'=>$accounts,
		]);

	}

	/**
	 * @return mixed
	 * удобнове отображение инфомации о мерчанте киви2, древовидная структура
	 */
	public function actionModernMerchantView()
	{
		session_write_close();
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$jsonStr = MerchantUser::getMerchantViewDataJson();

		return $this->render('modernMerchantView', [
			'jsonStr'=>$jsonStr,
		]);
	}
}