<?php

/**
 * расчеты клиентов - отчет о выдаче usd
 * @property int id
 * @property string answer
 *
 * @property int date_add
 * @property string dateAddStr
 *
 * @property int date_used
 * @property string dateUsedStr
 *
 * @property string used_by
 * @property string answerStr
 *
 * @property bool isFree	можно ли использовать текущий ответ
 *
 * todo: пометка старых кодов юзаными
 *
 */
class AntiCaptcha extends Model
{
	const SCENARIO_ADD = 'add';

	const ERROR_NOT_READY = 'captchaNotReady';

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{anti_captcha}}';
	}

	public function rules()
	{
		return array(
			array('answer', 'length', 'min'=>50, 'max'=>1000, 'allowEmpty'=>false),
			array('used_by', 'in', 'range' => self::getUsers(), 'allowEmpty'=>true),
			array('date_add, date_used', 'safe'),
		);
	}

	public function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
			$this->date_add = time();

		return parent::beforeSave();
	}

	public function getDateAddStr()
	{
		return ($this->date_add) ? date('H:i:s', $this->date_add) : '';
	}

	public function getDateUsedStr()
	{
		return ($this->date_used) ? date('H:i:s', $this->date_used) : '';
	}

	/**
	 * стартует по крону в несколько потоков, решает капчу
	 * если активно нес колько методов, то выбирается рандомно
	 *
	 * @return bool
	 */
	public static function startSolving()
	{
		$config = cfg('antiCaptcha');

		//не начинать распознавание если в бд хватает результатов
		if(self::getAnswerCount() >= $config['answerMaxCount'])
		{
			echo "в системе достаточно капчи\n";
			return true;
		}

		$methods = $config['methods'];

		if(!$methods)
		{
			self::$lastError = 'не указано ни одного метода';
			return false;
		}

		shuffle($methods);

		$method = null;
		$i=null;
		$threadName = '';

		foreach($methods as $method)
		{
			for($i=1; $i<=$config['threadCountMax']; $i++)
			{
				if(!Tools::threadExist($method['name'].$i))
				{
					$threadName = $method['name'].$i;
					break;
				}
			}
		}

		if(!$threadName)
		{
			self::$lastError = 'все потоки уже запущены';
			return false;
		}

		if(!Tools::threader($threadName))
		{
			self::$lastError = 'exception_001';
			toLog(self::$lastError.' '.__METHOD__);
			return false;
		}

		//все проверки закончились, решаем капчу, сохраняем в бд
		$methodName = 'method'.ucfirst($method['name']);

		$captchaCode = self::$methodName($config, $method);

		if($captchaCode)
		{
			$model = new self;
			$model->scenario = self::SCENARIO_ADD;
			$model->answer = $captchaCode;

			self::log($methodName.' успех: капча распознана');

			return $model->save();
		}
		else
			self::log($methodName.' ошибка: '.self::$lastError);


		return true;
	}

	/**
	 * @param array $config
	 * @param array $methodConfig
	 * @return string|bool
	 */
	public static function methodRucaptcha($config, $methodConfig)
	{
		$sender = new Sender;
		$sender->followLocation = false;
		$sender->timeout = 15;
		$sender->pause = 0;


		$postData = 'key='.$methodConfig['key'].'&method=userrecaptcha'
			.'&googlekey='.$config['googleKey'].'&pageurl='.$config['pageUrl'];

		$content = $sender->send($methodConfig['urlIn'], $postData);

		$startTime = time();

		if(preg_match('!OK\|(\d+)!', $content, $res))
		{
			$captchaId = $res[1];
			sleep($config['firstSleep']);

			while(time() - $startTime < $config['maxTime'])
			{
				$url = str_replace(array('{key}', '{captchaId}'), array($methodConfig['key'], $captchaId), $methodConfig['urlOut']);

				$content = $sender->send($url);

				if(preg_match('!OK\|(.+)!', $content, $res))
				{
					if(strlen($content) < 70)
					{
						self::$lastError = 'short answer';
						self::log('short answer: '.$content);
						return false;
					}

					self::$lastError = '';
					return $res[1];
				}
				elseif(strpos($content, 'CAPCHA_NOT_READY') !== false)
					self::$lastError = self::ERROR_NOT_READY;
				else
					self::$lastError = 'ошибка решения капчи: '.$content;

				sleep($config['sleepTime']);
			}

		}
		else
		{
			self::$lastError = 'не получен id капчи: '.$content.' (httpCode = '.$sender->info['httpCode'][0].')';

			return false;
		}


		return false;
	}

	/**
	 * отдает один неиспользованный результат
	 * @param string $user
	 * @return string|false
	 */
	public static function getAnswer($user)
	{
		$cfg = cfg('antiCaptcha');

		//не отдавать старые коды
		$dateAdd = time() - $cfg['answerLifeTime'];

		if($model = self::model()->find(array(
			'condition'=>"`date_used`=0 AND `date_add` > $dateAdd",
			'order'=>"`date_add` ASC"
		)))
		{
			$model->used_by = $user;
			$model->date_used = time();

			if($model->save())
				return $model->answer;
			else
				return false;
		}
		else
			return false;
	}

	public static function log($msg)
	{
		return Tools::log($msg, false, false, 'antiCaptcha');
	}

	/**
	 * уведомление в жабу
	 * @param string $msg
	 */
	public static function notice($msg)
	{

	}

	/**
	 * берет список юзеров из конфига которым разрешено запрашивать капчу
	 */
	public static function getUsers()
	{
		$config = cfg('antiCaptcha');

		return $config['users'];
	}

	/**
	 * @return int
	 */
	public static function getAnswerCount()
	{
		$cfg = cfg('antiCaptcha');

		$dateAdd = time() - $cfg['answerLifeTime'];

		return self::model()->count("`date_used`=0 AND `date_add`>$dateAdd");
	}


	/**
	 * статистика по таблице
	 * @param self[] $models
	 * @return array ['freeCount'=>0, 'usedCount'=>0...]
	 */
	public static function getStats($models)
	{
		$cfg = cfg('antiCaptcha');

		$result = array(
			'freeCount'=>0,		//свободные
			'usedCount'=>0,		//использованные
			'expiredCount'=>0,		//просроченые
		);

		foreach($models as $model)
		{
			if($model->date_used)
				$result['usedCount']++;
			elseif(time() - $model->date_add > $cfg['answerLifeTime'])
				$result['expiredCount']++;
			else
				$result['freeCount']++;
		}

		return $result;
	}

	public function getAnswerStr()
	{
		return Tools::shortText($this->answer, 20).'('.strlen($this->answer).')';
	}

	/**
	 * можно ли юзать ответ
	 * @return bool
	 */
	public function getIsFree()
	{
		$cfg = cfg('antiCaptcha');

		if(!$this->date_used and $this->date_add > time() - $cfg['answerLifeTime'])
			return true;
		else
			return false;
	}
}

