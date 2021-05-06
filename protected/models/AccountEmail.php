<?php

/**
 * Class Client
 * @property int id
 * @property string server
 * @property string login
 * @property string pass
 * @property int date_check
 * @property int error
 * @property int date_add
 * @property string dateAddStr
 * @property string dateCheckStr
 * @property string email
 * @property MailBox _botObj
 * @property string botError
 * @property string botErrorCode
 *
 *

 * todo: при заходе на мыло обновлять дату проверки
 */

class AccountEmail extends Model
{
	const SCENARIO_ADD = 'add';
	const ADD_FORMAT = '!(.+?)@(\w+?\.[\w]+?):([^:]+)!';	//fedo32ja@rambler.ru:dbF5311gs2
	const CHECK_INTERVAL = 604800;	//раз в неделю проверять мыла на рабочесть
	const CHANGE_EMAIL_INTERVAL = 1800;	//смена мыла если код не приходит

	private $_botObj = false;
	public $botError = false;
	public $botErrorCode = false;


	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return array(
			'id'=>'ID',
			'server'=>'Адрес сервера',
			'login'=>'Логин',
			'pass'=>'Пароль',
			'date_check'=>'Проверен',
			'error'=>'Ошибка',
			'date_add'=>'Добавлен',
		);
	}

	public function tableName()
	{
		return '{{account_email}}';
	}

	public function beforeValidate()
	{
		if ($this->scenario == self::SCENARIO_ADD)
			$this->date_add = time();

		return parent::beforeValidate();
	}

	public function rules()
	{
		return array(
			array('server, login, pass, date_add', 'required'),
			array('server', 'length', 'max'=>50),
			array('login,pass,error', 'length', 'max'=>100),
			array('login', 'cloneValidator'),
			array('date_check,date_add', 'numerical', 'max'=>time()),
		);
	}

	public function cloneValidator($attribute=false, $params=false)
	{
		if(self::modelExist($this->server, $this->login))
		{
			self::$lastErrorCode = self::ERROR_EXIST;
			$this->addError('login', 'Email '.$this->getEmail().' уже был добавлен');
		}
	}

	public function beforeSave()
	{

		return parent::beforeSave();
	}

	public function getDateCheckStr()
	{
		return ($this->date_check) ? date('d.m.Y', $this->date_check) : '';
	}

	public function getDateAddStr()
	{
		return ($this->date_add) ? date('d.m.Y', $this->date_add) : '';
	}

	public function getEmail()
	{
		return $this->login.'@'.$this->server;
	}


	/**
	 * @return Email[]
	 *
	 */
	public static function modelArr($condition = '', $offset = 0, $limit = null)
	{
		return self::model()->findAll(array(
			'condition' => $condition,
			'offset' => $offset,
			'limit' => $limit,
			'order' => "`id`",
		));
	}

	public static function modelCount()
	{
		return self::model()->count();
	}

	public static function workModelCount()
	{
		return self::model()->count("`error`='' AND `date_check`>".(time() - self::CHECK_INTERVAL));
	}

	public static function notCheckModelCount()
	{
		return self::model()->count("`date_check`< ".(time() - self::CHECK_INTERVAL));
	}

	public static function freeModelCount()
	{
		//runtimeLog(__METHOD__.' (line '.__LINE__.')');
		$result = self::model()->count("`error`='' AND `date_check`>0 AND `id` NOT IN(SELECT `email_id` FROM `".Account::model()->tableSchema->name."` WHERE `email_id`!=0)");

		return $result;
	}

	/**
	 * @return int	//количество добавленых
	 * $params = array(
	 * 	'emails'=>'',	//мыла столбиком
	 * )
	 *
	 */
	public static function addMany(array $params)
	{
		$rows = explode(PHP_EOL, trim($params['emails']));

		$addCount = 0;

		foreach($rows as $num=>$row)
		{
			$row = trim($row);

			if(!$row)
				continue;

			if(preg_match(self::ADD_FORMAT, $row, $matches))
			{
				$model = new self;
				$model->scenario = self::SCENARIO_ADD;
				$model->login = $matches[1];
				$model->server = $matches[2];
				$model->pass = $matches[3];

				if($model->save())
					$addCount++;
				else
				{
					if(self::$lastErrorCode == self::ERROR_EXIST)	//при повторах не прерывать
					{
						self::$lastErrorCode = false;
						continue;
					}
					else
						break;
				}
			}
			else
			{
				self::$lastError = 'неверный email на строке '.($num+1);
				break;
			}
		}

		return $addCount;
	}

	public static function modelExist($server, $login)
	{
		return self::model()->count("`server`='$server' AND `login`='$login'");
	}

	/**
	 * свободный email
	 * @return AccountEmail
	 */
	public static function getFreeEmail()
	{
		$limit = 100;
		$isRandomOrder = true;

		$condition = "`id` NOT IN(SELECT `email_id` FROM `".Account::model()->tableSchema->name."` WHERE `email_id`!=0)";

		$models = self::model()->findAll(array(
			'condition'=>"`error`='' AND `date_check`>0 AND $condition",
			'limit'=>$limit,
		));

		if(!$models)
		{
			self::$lastError = 'нет свободных email';
			return false;
		}

		if($isRandomOrder)
			shuffle($models);

		return current($models);
	}

	/**
	 * проверка рабочий ли email
	 * @return bool
	 */
	public function getIsWork()
	{
		if($bot = $this->_getBot())
			return true;
		else
			return false;
	}

	/**
	 * список входящих(дата по убыванию)
	 * @return array|bool
	 */
	public function getMessages($from=false, $onlyUnread = true)
	{
		if($bot = $this->_getBot())
		{
			$messages = $bot->getMessages($from, $onlyUnread);

			if(!$bot->error)
				return $messages;
			else
			{
				toLog('AccountEmail: '.$this->getEmail().' ошибка получения списка писем');
				return false;
			}
		}
		else
			return false;
	}

	/**
	 * авторизует на кошельке через имап
	 * @return MailBox|bool
	 */
	private function _getBot()
	{
		if(!$this->_botObj)
		{
			$login = $this->login;
			$additional = '';

			if($this->server == 'mail.ru')
				$login = $this->getEmail();

			$box = new MailBox('{imap.'.$this->server.':143'.$additional.'}INBOX', $login, $this->pass);

			if(!$box->error)
			{
				$this->_botObj = $box;
			}
			else
			{
				$this->botError = $box->error;
				toLog(" Email ".$this->getEmail().": ".$box->error);

				//если такая ошибка то ошибкой не помечать
				$safeErrorArr = array(
					'MAP connection broken',
				);

				$error = $this->botError;

				foreach($safeErrorArr as $errorStr)
				{
					if(mb_strpos($box->error, $errorStr, 0, 'utf-8')!==false)
						$error = '';
				}

				if($error)
					self::model()->updateByPk($this->id, array('error'=>$this->botError));
			}

			self::model()->updateByPk($this->id, array('date_check'=>time()));
		}

		return $this->_botObj;
	}


}