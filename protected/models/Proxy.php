<?php
/**
 * todo: сделать колонку рейтинга чтобы искать по базе было удобнее
 * @property int id
 * @property string ip
 * @property string port
 * @property string login
 * @property string pass
 * @property int check_count_success
 * @property int check_count_fail
 * @property string last_response_msg
 * @property int last_response_date
 * @property string external_ip
 * @property string comment
 * @property int rating
 * @property int accountCount
 * @property string reset_url	ссылка для перезагрузки прокси
 * @property string reset_date	ссылка для перезагрузки прокси
 * @property string resetDateStr
 * @property string str	ip:port (возможно логин с паролем)
 * @property bool is_personal
 * @property int account_id
 * @property Account account
 * @property bool isGoodRating
 * @property bool enabled
 * @property string category	категория прокси для разных целей
 *
 */
class Proxy extends Model
{
	const SCENARIO_ADD = 'add';//
	const RATING_MIN = 55;//минимальная стабильность прокси для назначения в группу AccountProxy::editProxyId()

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'ip' => 'ip',
			'port' => 'port',
			'login' => 'Логин',
			'pass' => 'Пароль',
			'check_count_success' => 'Кол-во успешных проверок',
			'check_count_fail' => 'Кол-во неудачных проверок',
			'last_response_msg' => 'Последний ответ',
			'last_response_date' => 'Дата последнего ответа',
			'external_ip' => 'Внешний ip',
			'comment' => 'Коммент',
			'reset_url' => 'Ссылка для перезагрузки',
			'reset_date' => 'Дата перезагрузки',
			'category' => 'Категория',
		);
	}

	public function tableName()
	{
		return '{{proxy}}';
	}

	public function rules()
	{
		return array(
			[
				'ip, port, login, pass, date_bad_content, count_success,
			 	count_fail, last_response_msg, last_response_date, external_ip,
			 	comment, reset_url, reset_date, is_personal, is_yandex,account_id, enabled, category',
				'safe'
			]
		);
	}

	protected function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
		{
			$this->enabled = 1;
		}

		return parent::beforeSave();
	}

	public function getStr()
	{
		$result = "{$this->ip}:{$this->port}";

		if($this->login and $this->pass)
			$result = "{$this->login}:{$this->pass}@$result";

		return $result;
	}

	/*
	 * только ip и порт
	 */
	public function getStrShort()
	{
		$result = "{$this->ip}:{$this->port}";

		return $result;
	}

	/*
	 * процент успешных запросов
	 *
	 * return число от 0-100 или null (если нет данных - деление на 0)
	 */
	public function getRating()
	{
		$sum = $this->check_count_success + $this->check_count_fail;

		return ($sum > 0) ? round($this->check_count_success / $sum * 100) : null;
	}

	public function getRatingStr()
	{
		$res = $this->getRating();

		if($res !== null)
		{
			$resStr = $res.' % <br><nobr>('.($this->check_count_success + $this->check_count_fail).' проверок</nobr>)';

			if($res >= self::RATING_MIN)
				return '<span class="green">'.$resStr.'</span>';
			elseif($res < self::RATING_MIN)
				return '<span class="red">'.$resStr.'</span>';
		}

		return 'нет данных';
	}

	public function getLastResponseStr()
	{
		if($this->last_response_date)
		{
			$color = '';

			if(strpos($this->last_response_msg, 'success:')!==false)
				$color = 'green';
			elseif(strpos($this->last_response_msg, 'fail:')!==false)
				$color = 'red';

			return '<nobr><span class="'.$color.'">'.$this->last_response_msg.'</span></nobr> <br/>('.$this->getLastResponseDateStr().')';
		}
		else
			return '';
	}

	public function getLastResponseDateStr()
	{
		return ($this->last_response_date) ? date('H:i', $this->last_response_date) : '';
	}

	public function getExternalIpDublicateCount()
	{
		return ($this->external_ip) ? self::model()->count("`id`!={$this->id} and `external_ip`='{$this->external_ip}'") : 0;
	}

	/*
	 * количество аккантов на этом прокси
	 * учитывать толко аккаунты проверенные за 2 дня
	 */
	public function getAccountCount()
	{
		$date = time() - 3600*24;

		return Account::model()->count("`date_last_request`>$date AND `proxy`='".$this->getStr()."'");
	}

	public function clearStats()
	{
		self::model()->updateByPk($this->id, array(
			'check_count_success'=>0,
			'check_count_fail'=>0,
		));

		return true;
	}

	/**
	 * @return string
	 */
	public function getResetDateStr()
	{
		return ($this->reset_date) ? date('d.m.Y H:i:s', $this->reset_date) : '';
	}

	/*
	 * проверка по крону
	 *
	 * проверка проходит сразу для всех проксей
	 * очистка статистики раз в n секунд
	 */
	public static function startCheck()
	{
		$done = 0;

		$cfg = cfg('proxy');

		$models = self::model()->findAll(array(
			'condition'=>"",
			'order'=>"`last_response_date`",
		));

		/**
		 * @var self[] $models
		 */

		$checkArr = array();

		foreach($models as $model)
		{
			//добавление в массив проверки
			if(time() - $model->last_response_date >= $cfg['checkInterval'])
				$checkArr[$model->id] = $model;
		}

		$sender = new Sender;
		$sender->followLocation = false;
		$sender->timeout = $cfg['checkTimeout'];

		$urlArr = array();
		$proxyArr = array();

		foreach($checkArr as $id=>$model)
		{
			$urlArr[$id] = cfg('my_ip_url');
			$proxyArr[$id] = $model->str;
		}

		$contents = $sender->send($urlArr, false, $proxyArr);

		foreach($contents as $id=>$content)
		{
			if(YII_DEBUG)
				die($content);

			$model = self::modelByPk($id);

			//нельзя обнулять. при первой неудачной проверке считает прокси нерабочим
			if($model->check_count_success + $model->check_count_fail >= $cfg['clearStatsCheckCount'])
			{
				$model->check_count_success = round($model->check_count_success/2);
				$model->check_count_fail = round($model->check_count_fail/2);
			}

			$model->last_response_date = time();

			if($sender->info['httpCode'][$id] == 200 and preg_match('!^\d+\.\d+\.\d+\.\d+$!', $content))
			{
				$model->last_response_msg = 'success: '.formatAmount($sender->info['time'][$id], 2).' sec';
				$model->check_count_success++;
				$model->external_ip = $content;
			}
			else
			{
				$model->last_response_msg =  'fail: httpCode='.$sender->info['httpCode'][$id];
				$model->check_count_fail++;
			}

			$done++;
			$model->save();
		}

		//test отключил замену прокси(чтобы не путались на кошельках пркоси)
		//if(config('proxyReplaceEnabled'))
		//	self::replaceBadProxies();

		return $done;
	}

	public static function clearAllStats()
	{
		$models = self::model()->findAll();

		/**
		 * @var self[] $models
		 */

		foreach($models as $model)
		{
			if(!$model->clearStats())
				return false;
		}

		return true;
	}

	/**
	 * сортировка по убыванию рейтинга
	 * @param string $condition
	 * @return self[]
	 */
	public static function getProxies($condition = '')
	{
		return self::model()->findAll(array(
			'condition'=>"$condition",
			'order'=>"`check_count_success` DESC",
		));
	}

	/**
	 * замена проксей с плохим рейтингом
	 */
	private static function replaceBadProxies()
	{
		foreach(self::getProxies("`category`=''") as $proxyModel)
		{
			if($proxyModel->rating < self::RATING_MIN)
			{
				$newProxy = self::getProxyForReplace();

				if(!$newProxy)
					return false;

				AccountProxy::model()->updateAll(array('proxy_id'=>$newProxy->id), "`proxy_id`='{$proxyModel->id}'");
			}
		}

		return true;
	}

	/**
	 * наименее используемые живой прокси
	 * @return self|false
	 */
	private static function getProxyForReplace()
	{
		$proxyArr = array();

		foreach(self::getProxies("`category`=''") as $model)
		{

			if($model->rating >= self::RATING_MIN and strpos($model->last_response_msg, 'success:') !== false)
				$proxyArr[$model->accountCount] = $model;
		}

		if(!$proxyArr)
			return false;

		ksort($proxyArr);

		//чтобы разом все аккаунты не сменили прокси на один и тот же
		$slice = array_slice($proxyArr, 0, ceil(count($proxyArr)/2));

		return $slice[array_rand($slice)];
	}

	/**
	 * перезагрузка прокси(смена ip-ов на проксях)
	 * //todo:  сделать чтобы на киви чекало прокси
	 * @return bool
	 */
	public static function startReset()
	{

		if(!Tools::threader('proxyReset'))
			die('уже запущен');

		$cfg = cfg('proxy');

		$date = time() -$cfg['resetInterval'];

		$models = self::model()->findAll(array(
			'condition'=>"`reset_url` != '' AND `reset_date` < $date",
			'order'=>"`reset_date` ASC",
		));

		if($cfg['shuffleReset'])
			shuffle($models);

		$models = array_slice($models, 0, 5);

		/**
		 * @var self[] $models
		 */

		if(!$models)
			return true;

		$sender = new Sender;
		$sender->followLocation = false;
		$sender->timeout = 120;
		$sender->useCookie = false;

		$count = 0;

		$urlArr = [];

		foreach($models as $model)
			$urlArr[$model->id] = $model->reset_url;

		$contents = $sender->send($urlArr);

		foreach($contents as $id=>$content)
		{
			$model = self::modelByPk($id);

			if(
				$content == 'OK'
				or preg_match('!MODEM POWER RESET!is', $content)
				or preg_match('!lte1!isu', $content)
			)
			{
				$count++;
				echo "reset proxy {$model->ip}:{$model->port}\r\n";
				$model->reset_date = time();
				$model->save();
				sleep(2);
			}
			else
			{
				self::$lastError = 'Ошибка сброса прокси: '.$model->str.' content: '.$content;
				echo self::$lastError."\n";
				toLogError(self::$lastError);
				continue;
			}
		}

		toLogRuntime('перезагружено '.$count.' прокси');

		return true;
	}

	/**
	 * перезагрузка конкретного прокси
	 * @return bool
	 */
	public function reboot()
	{
		if(!$this->reset_url)
		{
			self::$lastError = 'у прокси нет урл для сброса';
			return false;
		}

		$sender = new Sender;
		$sender->followLocation = false;
		$sender->timeout = 60;
		$sender->useCookie = false;

		$content = $sender->send($this->reset_url);

		if($content == 'OK' or preg_match('!MODEM POWER RESET!is', $content))
		{
			$this->reset_date = time();
			return $this->save();
		}
		else
		{
			self::$lastError = 'неверный контент: '.$content;
			return false;
		}

	}


	/**
	 * @param int $id
	 * @return self
	 */
	public static function modelByPk($id)
	{
		return self::model()->findByPk($id);
	}

	/**
	 * @return bool
	 */
	public function getIsGoodRating()
	{
		$rating = $this->getRating();

		if($rating >= self::RATING_MIN)
			return true;
		else
			return false;
	}

	/**
	 * @return Account|StdClass
	 */
	public function getAccount()
	{
		if($this->account_id)
			return Account::model()->findByPk($this->account_id);
		else
			new stdClass;

	}

	/**
	 * @param string $proxyContent
	 * @param bool $isPersonal
	 * @param bool $isYandex
	 * @param int $accountId
	 * @param string $category
	 * @return int колво добавленных
	 */
	public static function addMany($proxyContent, $isPersonal=false, $accountId = 0, $isYandex=false, $category = '')
	{
		$addCount = 0;

		if(preg_match_all('!(([^:]+?):([^@]+?)@|)(.+?):(\d{2,7})!', $proxyContent, $res))
		{
			foreach($res[2] as $key=>$login)
			{
				$attributes = [
					'login'=>trim($login),
					'pass'=>trim($res[3][$key]),
					'ip'=>trim($res[4][$key]),
					'port'=>trim($res[5][$key]),
					'is_personal'=>($isPersonal) ? 1 : 0,
					'is_yandex'=>($isYandex) ? 1 : 0,
					'account_id'=>$accountId,
					'category'=>$category,
				];

				if($existModel = self::model()->find("`ip`='{$attributes['ip']}' AND `port`='{$attributes['port']}'"))
				{
					/**
					 * @var self $existModel
					 */

					if($accountId > 0)
					{
						/** test
						if($existModel->account_id > 0)
						{
							self::$lastError .= 'к прокси '.$existModel->id.' уже привязан другой кошелек';
							return $addCount;
						}
						*/

						self::model()->updateByPk($existModel->id, ['account_id'=>$accountId]);
						return 1;
					}
					else
						self::$lastError .= '<br> '.$attributes['ip'].':'.$attributes['port'].' уже добавлен';

					continue;
				}

				$model = new self;
				$model->scenario = self::SCENARIO_ADD;
				$model->attributes = $attributes;

				if($model->save())
					$addCount++;
				else
					break;
			}
		}
		else
			self::$lastError = 'неверный формат прокси: '.$proxyContent;

		return $addCount;
	}

	/**
	 * @param Proxy[] $models
	 * @return array
	 */
	public static function getStats($models)
	{
		$result = [
			'freePersonalCount' => 0,
		];

		foreach($models as $model)
		{
			if($model->is_personal and !$model->account_id)
				$result['freePersonalCount']++;
		}

		return $result;
	}

	/**
	 * существующие уже категории
	 * @return array
	 */
	public static function getCategories()
	{
		$result = [];

		 $models = self::model()->findAll([
			'select' => "category",
			'condition' => "`category` != ''",
			'group' => "`category`",
			'order' => "`category` ASC",
		 ]);

		/**
		 * @var self[] $models
		 */

		foreach($models as $model)
			$result[] = $model->category;

		return $result;
	}

	/**
	 * тут при удалении прокси можно добавить удаление всех связей
	 */
	public function beforeDelete()
	{
		return parent::beforeDelete();
	}
}