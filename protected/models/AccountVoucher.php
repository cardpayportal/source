<?php

/**
 * если при отправке платежа не получили ответ то создается запись, после обновления истории привязывается к Transaction
 * пока добавляем сюда только исходящие платежи
 * Class AccountVoucher
 * @property int id
 * @property int account_id
 * @property int client_id
 * @property string code
 * @property float amount
 * @property int date_add
 * @property int date_pick
 * @property int date_activate
 * @property Client client
 */
class AccountVoucher extends Model
{
	private $_clientCAche = null;

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{account_voucher}}';
	}

	public function rules()
	{
		return [
			['account_id, client_id, code, amount, date_add, date_pick, date_activate', 'safe'],
		];
	}

	protected function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD and !$this->date_add)
			$this->date_add = time();

		return parent::beforeSave();
	}

	/**
	 * неактивированные ваучеры
	 * @param int $clientId
	 * @param int $dateStart
	 * @param int $dateEnd
	 * @return self[]
	 */
	public static function getModelsForView($clientId, $dateStart, $dateEnd)
	{
		$limit = 1000;

		return self::model()->findAll([
			'condition' => "
				`client_id`=$clientId
				AND `date_add`>=$dateStart AND `date_add`<$dateEnd
			",//`date_activate`=0
			'order' => "`id` DESC",
			'limit' => $limit,
		]);
	}

	/**
	 * @return Client|null
	 */
	public function getClient()
	{
		if(!$this->_clientCAche)
			$this->_clientCAche = Client::modelByPk($this->client_id);

		return $this->_clientCAche;
	}


	/**
	 * @param int $accountId
	 * @param array $vouchers [['code'=>'DFDV7234623...', 'amount'=>233.23, 'date'=>'13.04.2016', 'timestamp'=>213123123]]
	 * @return true
	 */
	public static function addMany($accountId, $vouchers)
	{
		$account = Account::modelByAttribute(['id'=>$accountId]);

		foreach($vouchers as $voucher)
		{
			if(!self::model()->find("`code`=>'{$voucher['code']}'"))
			{
				$model = new self;
				$model->scenario = self::SCENARIO_ADD;
				$model->code = $voucher['code'];
				$model->amount = $voucher['amount'];
				$model->date_add = $voucher['timestamp'];
				$model->account_id = $accountId;
				$model->client_id = $account->client_id;

				$model->save();
			}
		}

		return true;
	}

	/**
	 * делает несколько потоков на создание ваучеров, возвращает массив проблемных кошельков
	 * @param $wallets
	 * @return array|false
	 */
	public static function createAllFromWallets($wallets)
	{
		$threads = 10;
		$maxTime = 50;
		$startTime = time();
		$url = 'http://'.cfg('siteIp').'/?r=thread/voucher&login={login}';

		$sender = new Sender;
		$sender->timeout = $maxTime;

		$result = [];

		if(preg_match_all('!([73]\d{10,11})!is', $wallets, $res))
		{
			$urls = [];

			foreach($res[1] as $login)
				$urls['+'.$login] = str_replace('{login}', $login, $url);

			$chunkArr = array_chunk($urls, $threads, true);

			//уберем их из $urls  и вернем остаток
			$successArr = [];

			foreach($chunkArr as $chunk)
			{
				if(time() - $startTime > $maxTime)
					break;

				$contents = $sender->send($chunk);

				foreach($contents as $login=>$content)
				{
					if($content == 'OK')
						$successArr[] = '+'.$login;
				}
			}

			foreach($urls as $login=>$url)
			{
				if(!isset($successArr[$login]))
					$result[] = $login;
			}

		}
		else
		{
			self::$lastError = 'кошельков не найдено';
			return false;
		}
	}



}