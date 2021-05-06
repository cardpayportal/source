<?php
/**
 *
 * @property int id
 * @property int internal_id
 * @property string login
 * @property string name
 * @property string email
 * @property int uni_user_id
 * @property int uni_client_id
 * @property string address_qiwi
 * @property float balance_qiwi
 * @property array() walletList
 * @property int walletCount
 * @property User user
 * @property Client client
 *
 */

class MerchantUser extends Model
{
	protected static $bot;
	public $clientObj = null;
	public $userObj = null;



	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return array(
			'login'=>'Логин',
		);
	}

	public function rules()
	{
		return [
			['internal_id, login, name, email, uni_user_id, uni_client_id, address_qiwi, balance_qiwi', 'safe'],
		];

	}

	public function tableName()
	{
		return '{{merchant_user}}';
	}

	public function beforeValidate()
	{
		return parent::beforeValidate();

	}

	protected static function getBot($test = false)
	{
		if($test)
			$config = cfg('qiwiMerchantTest');
		else
			$config = cfg('qiwiMerchant');

		if(!self::$bot)
			self::$bot = new MerchantApi($config['clienId'], $config['clienSecret'], $config['proxy'], $test);

		return self::$bot;
	}

	/**
	 * @param $params
	 *
	 * @return mixed
	 *
	 * регистрация нового пользователя
	 */
	public static function addUser($params)
	{
		$bot = self::getBot();

		/**
		 * @var User $user
		 */
		$user = User::model()->findByPk($params['user_id']);

		if(!$user)
			return false;

		$login = $name = str_replace('man', '', $user->name);

		$registerData = $bot->registerUser($login, $name, $params['email']);

		if(!$bot::$lastError)
		{
			$model = new MerchantUser;
			$model->internal_id = $registerData['user_id'];
			$model->login = $login;
			$model->name = $name;
			$model->email = $params['email'];
			$model->uni_client_id = $user->client_id;
			$model->uni_user_id = $user->id;
			$model->address_qiwi = $registerData['accounts'][1]['address'];
			$model->balance_qiwi = $registerData['accounts'][1]['balance'];
			return $model->save();
		}
		else
			return false;

	}

	/**
	 * @param $userId
	 *
	 * @return bool
	 *
	 * удаляем пользователя в базе и в сервисе
	 */
	public static function deleteUser($userId)
	{
		$bot = self::getBot();

		$model = self::model()->findByAttributes(['internal_id'=>$userId]);

		if(!$model)
		{
			$errorMessage = 'Не найден пользователь с internal_id = '.$userId;
			self::$lastError = $errorMessage;
			toLogError($errorMessage);
			return false;
		}

		$responce = $bot->deleteUser($userId);

		if(!$bot::$lastError)
		{
			if($responce['message'] == 'User has been deleted')
			{
				$model->delete();
				return true;
			}
			else
				return false;
		}
		else
			return false;
	}

	public static function getAll()
	{
		return self::model()->findAll([
			'order' => "`uni_client_id` ASC",
		]);
	}


	/**
	 * @param string $userId
	 *
	 * @return array
	 *
	 * пока что метод берет список в реальном времени
	 */
	public static function directWalletList($userId='')
	{
		$bot = self::getBot();

		$responce = $bot->directWalletList($userId='');

		$walletArr = [];

		if(!$bot::$lastError and $responce)
		{
			foreach($responce as $key=>$wallet)
			{
				$walletArr[$key]['id'] = $wallet['_id'];
				$walletArr[$key]['wallet_name'] = $wallet['wallet_name'];
				$walletArr[$key]['balance'] = formatAmount($wallet['rub'], 2);
				$walletArr[$key]['date'] = date('d.m.Y H:i',strtotime($wallet['ctime']));
				$walletArr[$key]['wallet'] = $wallet['tel'];
			}
		}
		return $walletArr;
	}

	/**
	 * @param string $userId
	 *
	 * @return array
	 *
	 * список всех присвоенных кошей (информация обновляется каждый раз по апи)
	 */
	public static function walletList($showFilter='', $showType='', $showForMerchantId='')
	{
		$bot = self::getBot();

		$responce = $bot->walletList();

		if(YII_DEBUG)
		{
			print_r('YII DEBUG');
			var_dump($responce);
			die;
		}

		$walletArr = [];

		if(!$bot::$lastError and $responce)
		{
			foreach($responce as $key=>$wallet)
			{
				if($showForMerchantId and $showForMerchantId !== $wallet['merchant_user_id'])
					continue;

				$walletArr[$key]['id'] = $wallet['_id'];
				$walletArr[$key]['wallet_name'] = $wallet['wallet_name'];
				$walletArr[$key]['balance'] = formatAmount($wallet['rub'], 2);
				$walletArr[$key]['last_sync_time'] = strtotime($wallet['last_sync_date']);
				$walletArr[$key]['last_sync_date'] = date('d.m.Y H:i',strtotime($wallet['last_sync_date']));
				$walletArr[$key]['qiwi_blocked'] = $wallet['qiwi_blocked'];
				$walletArr[$key]['wallet'] = $wallet['tel'];
				$walletArr[$key]['merchant_internal_user_id'] = $wallet['merchant_user_id'];
				$walletArr[$key]['protocol_type'] = $wallet['protocol_type'];

				/**
				 * @var MerchantWallet $model
				 */
				if($model = MerchantWallet::model()->findByAttributes(['login'=>$wallet['tel']]))
				{
					/**
					 * @var MerchantTransaction $lastTransaction
					 */
					$lastTransaction = MerchantTransaction::model()->findByAttributes(['wallet'=>$wallet['tel']]);

					$walletArr[$key]['client_name'] = $model->user->client->name;
					$walletArr[$key]['user_name'] = $model->user->name;
					$walletArr[$key]['last_user_name'] = ($lastTransaction)?$lastTransaction->userStr : '';
					$walletArr[$key]['card_number'] = $model->card_number;
					$walletArr[$key]['limit_in'] = $model->getManagerLimitStr();
					$walletArr[$key]['amountStr'] = $model->amountStr;
					$walletArr[$key]['error'] = $model->error;


					if($showFilter == 'free' and $model->error != '')
					{
						unset($walletArr[$key]);
						continue;
					}
				}

				if($showFilter == 'free' and isset($wallet['merchant_user_id']))
				{
					unset($walletArr[$key]);
					continue;
				}
				elseif($showFilter == 'busy' and $wallet['merchant_user_id'] == '')
				{
					unset($walletArr[$key]);
					continue;
				}
				elseif($showFilter == 'new' and $wallet['merchant_user_id'] != '' and isset($model->InAmount) and $model->InAmount > 0)
				{
//					prrd('works');
//					if($model->amount_in > 0)
//					{
						unset($walletArr[$key]);
						continue;
//					}
				}

				//фильтр отображения: видно карты или кошельки
				if($showType == 'onlyCard' and $walletArr[$key]['card_number'] == '')
				{
					unset($walletArr[$key]);
					continue;
				}
				elseif($showType == 'onlyWallet' and $walletArr[$key]['card_number'] !== '')
				{
					unset($walletArr[$key]);
					continue;
				}

			}
		}
		return $walletArr;
	}

	/**
	 *
	 * разделение на группы всех присвоенных кошей (свободные и занятые)
	 * информация берется с обновлением по апи
	 *
	 * @return array
	 */
	public static function walletGroups()
	{
		$bot = self::getBot();

		$responce = $bot->walletList();

		$walletArr = [];

		$freeWalletArr = [];
		$busyWalletArr = [];

		if(!$bot::$lastError and $responce)
		{
			foreach($responce as $wallet)
			{
				//массив свободных кошельков и карт
				if($wallet['merchant_user_id'] == '')
					$walletArr = &$freeWalletArr;
				else
					$walletArr = &$busyWalletArr;

				$key = count($walletArr);

				$walletArr[$key]['id'] = $wallet['_id'];
				$walletArr[$key]['wallet_name'] = $wallet['wallet_name'];
				$walletArr[$key]['balance'] = formatAmount($wallet['rub'], 2);
				$walletArr[$key]['last_sync_time'] = strtotime($wallet['last_sync_date']);
				$walletArr[$key]['last_sync_date'] = date('d.m.Y H:i',strtotime($wallet['last_sync_date']));
				$walletArr[$key]['qiwi_blocked'] = $wallet['qiwi_blocked'];
				$walletArr[$key]['wallet'] = $wallet['tel'];
				$walletArr[$key]['merchant_internal_user_id'] = $wallet['merchant_user_id'];
				$walletArr[$key]['protocol_type'] = $wallet['protocol_type'];

				/**
				 * @var MerchantWallet $model
				 */
				if($model = MerchantWallet::model()->findByAttributes(['login'=>$wallet['tel']]))
				{
					/**
					 * @var MerchantTransaction $lastTransaction
					 */
					$lastTransaction = MerchantTransaction::model()->findByAttributes(['wallet'=>$wallet['tel']]);

					$walletArr[$key]['client_name'] = $model->user->client->name;
					$walletArr[$key]['user_name'] = $model->user->name;
					$walletArr[$key]['last_user_name'] = ($lastTransaction)?$lastTransaction->userStr : '';
					$walletArr[$key]['card_number'] = $model->card_number;
					$walletArr[$key]['limit_in'] = $model->getManagerLimitStr();
					$walletArr[$key]['amountStr'] = $model->amountStr;
					$walletArr[$key]['error'] = $model->error;
				}
			}
		}
		return [
			'free'=>$freeWalletArr,
			'busy'=>$busyWalletArr,
		];
	}

	/**
	 * @return bool
	 * ищет подходящий кошелек не привязанный к карте
	 */
	public static function findWalletWithoutCard()
	{
		$walletArr = self::directWalletList();
		$cardArr = MerchantCard::getAll();

		if(is_array($cardArr) and is_array($walletArr))
		{
			$cardWallets = [];
			foreach($cardArr as $cardInfo)
			{
				$cardWallets[] = $cardInfo['login'];
			}

			foreach($walletArr as $key=>$walletInfo)
			{
				if(!in_array($walletInfo['wallet'], $cardWallets))
					return($walletInfo['id']);
			}
			return false;
		}
		return false;
	}

	/**
	 * @param $id
	 *
	 * @return bool
	 * юзеру будет назначен свободный валидный кош автоматом
	 */
	public static function assignWallet($id)
	{
		/**
		 * @var MerchantUser $model
		 */
		$model = self::model()->findByPk($id);

		if(!$model)
			return false;

		$bot = self::getBot();
		$availableWalletId = self::findWalletWithoutCard();

		$responce = $bot->assignWallet($model->internal_id, $availableWalletId);

		if(!$bot::$lastError and $responce)
		{
//			if($responce['message'] == 'Successfully allocated wallet to Merchant user')
//			{
//				MerchantWallet::addInfo();
//				//$model->wallet_id = $responce['message'];
//				return true;
//			}
//			else
//				return false;
		}
		else
			return false;

	}

	/**
	 * @param $internalUserId
	 * @param $internalWalletId
	 *
	 * @return bool
	 * назначаем определенный кошелек
	 */
	public static function assignSpecialWallet($internalUserId, $internalWalletId)
	{
		$bot = self::getBot();
		$responce = $bot->assignWallet($internalUserId, $internalWalletId);

		if(!$bot::$lastError and $responce)
		{
			if($responce['message'] == 'Successfully allocated wallet to Merchant user')
			{
				MerchantWallet::addInfo();
				if($model = MerchantWallet::model()->findByAttributes(['internal_wallet_id'=>$internalWalletId]))
				{
					/**
					 * @var MerchantWallet $model
					 */
					$model->enabled = 1;
					$model->hidden = 0;
					$model->date_used = 0;
					$model->save();
				}
				return true;
			}
			else
				return false;
		}
		else
		{
			toLogError($responce);
			return false;
		}
	}


	/**
	 * @param $id
	 *
	 * @return bool
	 * отвязываем кош от юзера в общий пул кошей
	 */
	public static function deassignWallet($internalUserId, $internalWalletId)
	{
		$bot = self::getBot();

		$responce = $bot->deassignWallet($internalUserId, $internalWalletId);

		if(!$bot::$lastError and $responce)
		{
			if($responce['message'] == 'Successfully returned wallet to Merchant pool')
			{
				MerchantWallet::addInfo();
				return true;
			}
			else
				return false;
		}
		else
			return false;
	}

	/**
	 * выдает менеджеру список его кошельков, админу - список кошельков юзеров
	 * группирует кошельки по Имени юзера (у админа и фина)
	 * $used - если true, то возварщает только использованные кошельки, иначе - все остальные
	 * todo: убрать вывод кошельков админу в отдельную функцию
	 * @var bool $groupByUser
	 * @var bool $used
	 * @var int $limit
	 * @return Account[]
	 */
	public function getAccounts($groupByUser=true, $used=false, $limit = 0)
	{
		$result = array();

		$limit *= 1;

		if($used)
			$usedCond = "AND `date_used`>0";
		else
			$usedCond = "AND `date_used`=0";

		//$orderStr = "`status` DESC, `date_pick` DESC";

		$orderStr = "`status` DESC, `limit_in` DESC";

		if($used)
			$orderStr = "`date_used` DESC";

		$findParams = [];

		$userModel = User::model()->findByAttributes(['id'=>$this->uni_user_id]);

		if($userModel->role == User::ROLE_MANAGER or $userModel->role == User::ROLE_FINANSIST)
		{
			if($userModel->role == User::ROLE_MANAGER)
				$orderStr = "`hidden` ASC, `status` DESC, `limit_in` DESC";

			$findParams = [
				'condition'=>"`user_id`={$this->id} ".$usedCond,
				'order'=>$orderStr,
			];

			if($limit > 0)
				$findParams['limit'] = $limit;
		}
		elseif($userModel->role==User::ROLE_ADMIN)
		{
			//админ видит кошельки, с которыми работают менеджеры
			$findParams = [
				'condition'=>"
					`enabled`=1
					AND `user_id`!=0 $usedCond
				",
				//'limit'=>100,
				'order'=>"`status` DESC, `date_pick` DESC",
			];

			if($limit > 0)
				$findParams['limit'] = $limit;
		}
		else
			$models = array();

		if(!isset($models))
			$models = MerchantWallet::model()->findAll($findParams);


		if($models)
		{
			if($groupByUser)
			{
				//сначала кошельки текущего юзера, потом остальных
				foreach($models as $model)
				{
					if($model->user->id==$this->id)
						$result[$model->user->name][] = $model;
				}

				foreach($models as $model)
				{
					if($model->user->id!=$this->id)
						$result[$model->user->name][] = $model;
				}
			}
			else
				$result = $models;
		}

		return $result;
	}

	public function getWalletList()
	{
		return MerchantWallet::model()->findAll(
			[
				'condition'=>"`merchant_user_id`='{$this->id}' and `date_used`= '' ",
				'order' => "`date_add` DESC",
			]);
	}

	public function getWalletCount()
	{
		return count($this->getWalletList());
	}

	/**
	 * @return User
	 */
	public function getUser()
	{
		if($this->userObj === null)
			$this->userObj = User::model()->findByPk($this->uni_user_id);

		return $this->userObj;
	}

	/**
	 * @return Client
	 */
	public function getClient()
	{
		if($this->clientObj === null)
			$this->clientObj = Client::model()->findByPk($this->uni_client_id);

		return $this->clientObj;
	}

	/**
	 * подготавливает данные о мерчанте и кошах в json формате для отображения в древовидной форме
	 * @return string
	 */
	public static function getMerchantViewDataJson()
	{
		//пример данных
		//$jsonStr = '{"name": "clients","children": [{"name": "client7","children": [{"name": "man71","children": [{"name": "+79968002014","children": [{"name": "принято: 1 927 000"},{"name": "дата проверки: 10:16 15.01"},{"name": "баланс: 4 111.88 руб"},{"name": "суточный лимит: 33 000"},{"name": "общий лимит: 33 000"}]},{"name": "+79530264153","children": [{"name": "принято: 1 927 000"},{"name": "дата проверки: 10:16 15.01"},{"name": "баланс: 4 111.88 руб"},{"name": "суточный лимит: 33 000"},{"name": "общий лимит: 33 000"}]}]}]}]}';

		$dataArr = [];
		$dataArr['name'] = 'clients';

		/**
		 * @var Client[] $clients
		 */
		$clients = Client::getActiveClients();

		foreach($clients as $client)
		{
			$needAddWalletToClient = true;

			foreach($client->users as $user)
			{
				$merchantUser = $user->merchantUser;

				if($merchantWallets = $merchantUser->walletList)
				{
					$needAddWalletToUser = true;
					/**
					 * @var MerchantWallet[] $merchantWallets
					 */
					foreach($merchantWallets as $wallet)
					{
						if($wallet->getDayLimit() < 30000 or $wallet->limit_in < 150000)
						{
							$name = $wallet->login.' (проверить)';
							$needCheck = true;
						}
						else
						{
							$needAddWalletToUser = false;
							$needAddWalletToClient = false;
							$name = $wallet->login;
							$needCheck = false;
						}

						$walletArr[] = [
							'name'=>$name,
							'children'=>[
								['name'=>'принято '.$wallet->amountStr],
								['name'=>'дата проверки '.date('d.m H:i', $wallet->date_check)],
								['name'=>'баланс '.$wallet->balanceStr],
								['name'=>strip_tags($wallet->orderMsg)],
								['name'=>'суточный лимит '.$wallet->getDayLimit(), 'dayLimit'=>$wallet->getDayLimit()],
								['name'=>'общий лимит '.$wallet->limit_in, 'limitIn'=>$wallet->limit_in],
							]
						];
					}

				}

				if($merchantUser)
				{
					if($walletArr)
					{
						if($needAddWalletToUser)
							$userName = $user->name.' (заменить) ('.$merchantUser->walletCount.')';
						else
							$userName = $user->name.' ('.$merchantUser->walletCount.')';

						$userArr[] = [
							'name'=>$userName,
							'children'=>$walletArr,
						];
					}
					else
					{
						$userArr[] = [
							'name'=>$user->name,
						];
					}

					$walletArr = [];
				}
				else
					continue;

			}
			if($userArr)
			{
				if($needAddWalletToClient)
					$clientName = $client->name.' (проверить)';
				else
					$clientName = $client->name;

				$clientArr[] = [
					'name'=> $clientName,
					'children'=>$userArr,
				];
			}
			$userArr = [];

		}
		$dataArr['children'] = $clientArr;

		$jsonStr = json_encode($dataArr);

		return $jsonStr;
	}
}