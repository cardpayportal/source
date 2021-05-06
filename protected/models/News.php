<?php

/**
 * @property int id
 * @property string title
 * @property string text
 * @property int author_id
 * @property string authorStr
 * @property int date_add
 * @property string user_role	роли читателей через запятую
 * @property array userRoles	массив ролей, кто может читать новость
 * @property string rolesArrStr	Строка читателей(для кого новость)
 * @property string shortText	для списка новостей
 * @property string fullText	для страницы с новостью
 * @property string dateAddStr
 */
class News extends Model
{
	const SCENARIO_ADD = 'add';

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'title' => 'Название',
			'text' => 'Текст',
			'author' => 'Автор',
			'date_add' => 'Дата добавления',
			'user_role' => 'Читатели',
		);
	}

	public function tableName()
	{
		return '{{news}}';
	}

	public function beforeValidate()
	{
		$this->title = strip_tags($this->title);

		return parent::beforeValidate();
	}

	public function rules()
	{
		return array(
			array('title', 'length', 'min' => 3, 'max' => 255, 'allowEmpty' => false),
			array('title', 'titleValidator'),
			array('text', 'length', 'min' => 3, 'max' => 1000, 'allowEmpty' => false),
			array('author_id', 'userValidator'),
			array('user_role', 'userRolesValidator'),
		);
	}

	/**
	 * стандартная unique проверка выдает баг при редактировании
	 * @return bool
	 */
	public function titleValidator()
	{
		if(
			$model = self::model()->findByAttributes(array('title'=>$this->title))
			and $model->id != $this->id
		)
		{
			$this->addError('login', 'Такое название уже есть');
			return false;
		}

		return true;
	}


	public function userValidator()
	{
		$cfg = cfg('news');

		if($user = User::getUser($this->author_id) and ($user->login == $cfg['editorLogin'] or $user->role == User::ROLE_ADMIN))
			return true;
		else
			$this->addError('login', 'Нет прав на добавление новостей');

		return false;
	}

	public function beforeSave()
	{
		if ($this->scenario == self::SCENARIO_ADD)
			$this->date_add = time();

		return parent::beforeSave();
	}

	public function afterSave()
	{
		//чтобы для себя не была как непрочитанная
		self::read($this->author_id, $this->id);

		return parent::afterSave();
	}

	public function getDateAddStr()
	{
		return ($this->date_add) ? date('d.m.Y H:i', $this->date_add) : '';
	}

	public function getAuthor()
	{
		return User::getUser($this->author_id);
	}

	public function getAuthorStr()
	{
		return $this->getAuthor()->name;
	}

	/*
	 * прочитана ли новость пользователем
	 */
	public function isRead($userId)
	{
		if(NewsLastRead::model()->find("`user_id`='$userId' AND `news_id`>='{$this->id}'"))
			return true;

		return false;
	}

	public static function getLastReadId($userId)
	{
		if($newsModel = NewsLastRead::model()->find("`user_id`='$userId'"))
			return $newsModel->news_id;
		else
			return 0;
	}

	/*
	 * количество непрочитанных новостей у юзера
	 */
	public static function newsCount($userId)
	{
		$lastReadId = self::getLastReadId($userId);

		$models = self::getList($userId);

		$count = 0;

		foreach($models as $model)
		{
			if($model->id > $lastReadId)
				$count++;
		}

		return $count;
	}

	/*
	 * пометить новость прочитанной
	 */
	public static function read($userId, $newsId)
	{
		if(!$newsModel = NewsLastRead::model()->find("`user_id`='$userId'"))
		{
			$newsModel = new NewsLastRead;
			$newsModel->scenario = NewsLastRead::SCENARIO_ADD;
			$newsModel->user_id = $userId;

			$oldNewsId = 0;
		}
		else
		{
			$oldNewsId = $newsModel->news_id;
		}

		$newsModel->news_id = $newsId;

		//запоминаем только самую последнюю по дате новость
		if($newsModel->news_id > $oldNewsId)
			return $newsModel->save();
		else
			return true;
	}

	/*
	 * редактировать или добавить новость в зависимости от $params['id']
	 */
	public static function updateNews($params, $userId)
	{
		$user = User::getUser($userId);

		$params['author_id'] = $user->id;

		if($params['id'])
		{
			if(!$model = self::model()->findByPk($params['id']))
			{
				self::$lastError = 'не найдена редактируемая новость';
				return false;
			}
		}
		else
		{
			$model = new self;
			$model->scenario = self::SCENARIO_ADD;
		}

		$model->attributes = $params;
		$model->userRoles = $params['userRoles'];	//в сеттер через атрибут не передается

		return $model->save();
	}

	/**
	 * воврящает массив из строки
	 * @return array
	 */
	public function getUserRoles()
	{
		return explode(',', trim($this->user_role, ','));
	}

	public function setUserRoles($arr)
	{
		if(is_array($arr))
			$this->user_role = implode(',', $arr);
		else
			$this->user_role = '';
	}

	public function userRolesValidator()
	{
		$roles = $this->getUserRoles();

		if(!$roles)
		{
			$this->addError('role_arr', 'неверные читатели');
			return false;
		}

		$allowedRoles = User::roleArr();

		foreach($roles as $role)
		{
			if(!$allowedRoles[$role])
			{
				$this->addError('role_arr', 'неверные читатели');
				return false;
			}
		}

		return true;
	}

	/**
	 * список новостей для юзера
	 * @param int $userId кто спрашивает
	 * @return array
	 */
	public static function getList($userId)
	{
		$cfg = cfg('news');

		$result = array();

		$models = News::model()->findAll(array(
			'condition' => "",
			'order' => "`date_add` DESC",
			'limit'=>$cfg['limit'],
		));

		/**
		 * @var self[] $models
		 */


		if(!$user = User::getUser($userId))
			return $result;

		foreach($models as $model)
		{
			if(in_array($user->role, $model->userRoles) or $user->login == $cfg['editorLogin'] or $user->role == User::ROLE_ADMIN)
				$result[] = $model;
		}

		return $result;
	}

	/**
	 * получить новость по id
	 * @param int $id
	 * @param int $userId кто спрашивает
	 * @return self|false
	 */
	public static function getModel($id, $userId)
	{
		$cfg = cfg('news');

		if(!$user = User::getUser($userId))
			return false;

		if($model = self::model()->findByPk($id)
			and (in_array($user->role, $model->userRoles) or $user->login == $cfg['editorLogin'] or $user->role == User::ROLE_ADMIN)
		)
			return $model;
		else
			return false;
	}

	/**
	 * массив ролей для новостей
	 * @return array
	 */
	public static function getRolesArr()
	{
		return array(
			'global_fin'=>'Оператор (Global Fin)',
			'moderator'=>'Финансист (Клиент)',
			'user'=>'Менеджер (Прием платежей)',
		);
	}

	/**
	 * Строка читателей(для кого новость)
	 * @return string
	 */
	public function getRolesArrStr()
	{
		$result = '';

		$allowedRoles = self::getRolesArr();
		$roles = $this->userRoles;

		foreach($roles as $role)
			$result .= $allowedRoles[$role].', ';

		return trim($result, ' ,');
	}

	/**
	 * для списка новостей
	 * @return string
	 */
	public function getShortText()
	{
		//сказали: отображай все полностью, а то читать не будут
		return $this->getFullText();

		//return Tools::shortText(preg_replace('/!http.+?!/isu', '*картинка*', $this->text), 200);
	}

	/**
	 * для страницы с новостью
	 * @return string
	 */
	public function getFullText()
	{
		return preg_replace('/!(http.+?)!/isu', '<img src="$1">', $this->text);
	}

	/**
	 * @param int $id
	 * @param int $userId
	 * @return bool
	 */
	public static function deleteNews($id, $userId)
	{
		$cfg = cfg('news');

		if(!$user = User::getUser($userId))
			return false;

		if(!$model = self::getModel($id, $userId))
			return false;

		if($user->login == $cfg['editorLogin'])
			return $model->delete();
		else
		{
			self::$lastError = 'у вас нет прав на это действие';
			return false;
		}
	}

}