<?php

/**
 * Перелимитные кошельки
 * Отдельная проверка
 * Отдельный вывод
 * Добавляются в таблицу при обновлении платежей, либо при соответствующей ошибке при самом платеже
 * @property int id
 * @property int account_id
 * @property int date_limit_out
 * @property string dateLimitOutStr
 * @property Account account
 * @property float outAmountStr
 *
 */
class AccountLimitOut extends Model
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
			'account_id' => 'Аккаунт',
			'date_limit_out' => 'Дата перелимита',
			'out_amount' => 'Выведено',

		);
	}

	public function tableName()
	{
		return '{{account_limit_out}}';
	}

	public function beforeValidate()
	{
		if($this->scenario == self::SCENARIO_ADD and !$this->date_limit_out)
			$this->date_limit_out = time();

		return parent::beforeValidate();
	}

	public function rules()
	{
		return array(
			array('account_id, date_limit_out', 'required'),
			array('account_id', 'exist', 'className'=>'Account', 'attributeName'=>'id'),
			array('account_id', 'unique', 'className'=>__CLASS__, 'attributeName'=>'account_id', 'on'=>self::SCENARIO_ADD),
			array('date_limit_out', 'numerical'),
			array('out_amount', 'numerical'),
		);
	}

	public function getDateLimitOutStr()
	{
		if($this->date_limit_out)
			return date('d.m.Y', $this->date_limit_out);
	}

	public function getAccount()
	{
		return Account::model()->findByPk($this->account_id);
	}

	public function getOutAmountStr()
	{
		return formatAmount($this->out_amount, 0);
	}

	/*
	 * список моделей по дате перелимита
	 */
	public static function getModelsByDate($timestampStart, $timestampEnd, $clientId=false)
	{
		$models = self::model()->findAll(array(
			'condition'=> "`date_limit_out` >= $timestampStart AND `date_limit_out`<$timestampEnd",
			'order'=> "`date_limit_out` DESC",
		));

		if($clientId)
		{
			foreach($models as $key=>$model)
			{
				if($model->account->client_id != $clientId)
					unset($models[$key]);
			}
		}

		return $models;
	}

	public static function getModelByPk($id)
	{
		return self::model()->findByPk($id);
	}

	/**
	 * @return bool
	 * @param int $accountId
	 * @param int $dateLimitOut timestamp
	 */
	public static function add($accountId, $dateLimitOut)
	{
		$minLimit = 15000;

		$account = Account::modelByPk($accountId);

		//test пока отключил реидент для внедрения анонимов
		if($account->limit_in > $minLimit)
			Account::model()->updateByPk($account->id, ['comment'=>'reident']);


		if(!self::exist($accountId))
		{
			$model = new self;
			$model->scenario = self::SCENARIO_ADD;
			$model->account_id = $accountId;
			$model->date_limit_out = $dateLimitOut;

			$result = $model->save();

			if($result)
				toLog('добавлен перелимит: accountId='.$accountId);
			else
				toLog('ошибка добавления AccountLimitOut: accountId='.$accountId);
		}
	}

	public static function exist($accountId)
	{
		return self::model()->count("`account_id`='$accountId'");
	}

	/**
	 * отправить с выбранных перелимитных кошельков ($params[ids] - ID AccountLimitOut)
	 * @param array $params array('ids'=>array(), 'to'=>'+7...', 'amount'=>2133.22, extra=>'paypass')
	 * @return float
	 */
	public static function sendMoney($userId, $params)
	{
		$sendAmount = 0;

		if(
			$user = User::getUser($userId)
			and $user->role == User::ROLE_GLOBAL_FIN
			and PayPass::check($params['extra'], $userId)
		)
		{
			//найти все аккаунты
			if(is_array($params['ids']) and count($params['ids'])>0)
			{
				$accountArr = array();

				foreach($params['ids'] as $id)
				{
					//if($limitOutAccount = AccountLimitOut::)
				}
			}
			else
				self::$lastError = 'не выбраны кошельки';
		}
		else
			self::$lastError = 'проверьте платежный пароль';

		return $sendAmount;
	}



}