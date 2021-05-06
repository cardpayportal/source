<?php

/**
 *
 * @property integer $id
 * @property string $to
 * @property string $text
 * @property string $type
 * @property integer $date_add
 * @property integer $date_send
 * @property string $error
 */
class Notice extends Model
{
	const SCENARIO_ADD = 's_add';
	const SCENARIO_SEND = 's_send';

	const TYPE_EML = 'eml';
	const TYPE_XMPP = 'xmpp';

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return 'notice';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('to, text, type', 'required'),
			array('date_add, date_send', 'numerical', 'integerOnly'=>true),
			array('to', 'length', 'max'=>100),
			array('error', 'safe'),
			array('type', 'in', 'range'=>array_keys(self::typeArr())),
			array('to, text', 'safe', 'on'=>'search'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		// NOTE: you may need to adjust the relation name and the related
		// class name for the relations automatically generated below.
		return array(
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'to' => 'To',
			'text' => 'Text',
			'type' => 'Type',
			'date_add' => 'Date Add',
			'date_send' => 'Date Send',
		);
	}

	public function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
			$this->date_add = time();
		elseif($this->scenario == self::SCENARIO_SEND and !$this->error)
		{
			$this->date_send = time();
		}

		return parent::beforeSave();
	}

	/**
	 * @return array types
	 */
	public static function typeArr()
	{
		return array(
			//self::TYPE_EML => 'eml',
			self::TYPE_XMPP => 'xmpp',
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 *
	 * Typical usecase:
	 * - Initialize the model fields with values from filter form.
	 * - Execute this method to get CActiveDataProvider instance which will filter
	 * models according to data in model fields.
	 * - Pass data provider to CGridView, CListView or any similar widget.
	 *
	 * @return CActiveDataProvider the data provider that can return the models
	 * based on the search/filter conditions.
	 */
	public function search()
	{
		$criteria=new CDbCriteria;

		$criteria->compare('to',$this->to,true);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}

	/**
	 * Returns the static model of the specified AR class.
	 * Please note that you should have this exact method in all your CActiveRecord descendants!
	 * @param string $className active record class name.
	 * @return Notice the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	/**
	 * добавление уведомления
	 * в зависимости от текста и интервала может выдавать ошибку
	 * @return bool
	 * @param string $type тип уведомления(eml,xmpp)
	 * @param string $to адресат(пр. jabber1@xmpp.jp)
	 * @param string $text текст умедомления
	 */
	public static function add($type, $to, $text)
	{
		$cfg = cfg('notice');

		//удалить старые(и неактуальные тоже)
		Notice::model()->deleteAll("`date_add`<".(time() - $cfg['delete_interval']));

		$intervalModel = NoticeInterval::model()->find("`to`='$to'");

		$interval = ($intervalModel) ? $intervalModel->interval : $cfg['std_interval'];

		$minDate = time() - $interval;

		if(self::model()->find("`to`='$to' and `text`='$text' and `type`='$type' and `date_send`=0"))
		{
			//если есть неотправленное сообщение
			self::$lastError = 'это сообщение еще не отправлено';
			return false;
		}
		elseif($noticeModel = self::model()->find(array('condition'=>"`to`='$to' and `text`='$text' and `type`='$type' and `date_add`>$minDate", 'order'=>"`date_add` DESC")))
		{
			//если недавно уже отправил такое сообщение
			self::$lastError = 'добавление этого сообщения возможно через: '.($interval - time() + $noticeModel->date_add).' сек';
			return false;
		}

		$model = new self;
		$model->scenario = self::SCENARIO_ADD;
		$model->type = $type;
		$model->to = $to;
		$model->text = $text;

		return $model->save();
	}

	/*
	 * обработка по расписанию
	 */
	public static function startSend()
	{
		$done = 0;

		$models = self::model()->findAll(array(
			'condition'=>"`date_send`=0 AND `error`=''",
			'order'=>"`date_add` ASC",
		));

		foreach($models as $model)
		{
			//пауза межд уведомлениями
			sleep(5);

			if($model->send())
				$done++;
			else
			{
				self::$lastError = $model->error;
				toLog('error sending notice id='.$model->id.': '.self::$lastError);
			}
		}

		return $done;
	}

	/*
	 * отправить сообщение
	 */
	public function send()
	{
		$cfg = cfg('notice');

		if($this->type == self::TYPE_XMPP)
		{
			$bot = JabberBot::getInstance($cfg['xmpp']['login'], $cfg['xmpp']['pass']);

			if(!$bot->error)
			{
				if(!$bot->sendMessage($this->to, $this->text))
				{
					toLog('notice error send (id='.$this->id.') error='.$bot->error.', text = '.$this->text);
					//$this->error = $bot->error;
				}
			}
			else
			{
				//$this->error = $bot->error;
				toLog('JabberBot: '.$bot->error);
			}

		}
		else
		{
			self::$lastError = 'недопустимый тип уведомления';
			return false;
		}

		$this->scenario = self::SCENARIO_SEND;
		return $this->save();
	}
}
