<?php

class RillTransaction extends Model
{
	const STATUS_SUCCESS = 'success';
	const STATUS_WAIT = 'wait';
	const STATUS_ERROR = 'error';

	const TYPE_IN = 'in';
	const TYPE_OUT = 'out';

	const SCENARIO_ADD = 'add';

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return array(

		);
	}

	public function tableName()
	{
		return '{{rill_transaction}}';
	}

	public function beforeValidate()
	{
		if(!$this->user_id)
			$this->user_id = '';

		$this->amount = str_replace(',', '.', $this->amount);

		return parent::beforeValidate();
	}

	public function rules()
	{
		return array(
			array('account_id', 'exist', 'className'=>'Account', 'attributeName'=>'id', 'allowEmpty'=>false),
			array('user_id', 'exist', 'className'=>'User', 'attributeName'=>'id', 'allowEmpty'=>true),
			array('type', 'in', 'range'=>array_keys(self::typeArr())),
			array('amount', 'numerical', 'allowEmpty'=>false),
			array('status', 'in', 'range'=>array_keys(self::statusArr())),
			array('comment, error, wallet, from_used,qiwi_id,stat_label', 'safe'),
		);
	}

	protected function beforeSave()
	{
		if($this->error)
			$this->error = strip_tags($this->error);

		if($this->comment)
			$this->comment = strip_tags($this->comment);

		if($this->wallet)
			$this->wallet = strip_tags($this->wallet);

		return parent::beforeSave();
	}

	public static function statusArr($key=false)
	{
		$result = array(
			self::STATUS_SUCCESS => 'оплачен',
			self::STATUS_WAIT => 'ожидание',
			self::STATUS_ERROR => 'ошибка',
		);

		if($key)
			return $result[$key];
		else
			return $result;
	}

	public function getStatusStr()
	{
		if($this->status==self::STATUS_SUCCESS)
			return '<font color="green">'.self::statusArr(self::STATUS_SUCCESS).'</font>';
		elseif($this->status==self::STATUS_WAIT)
			return '<font color="orange">'.self::statusArr(self::STATUS_WAIT).'</font>';
		elseif($this->status==self::STATUS_ERROR)
			return '<font color="red">'.self::statusArr(self::STATUS_ERROR).': '.$this->error.'</font>';
	}

	public static function typeArr($key=false)
	{
		$result = array(
			self::TYPE_IN => 'приход',
			self::TYPE_OUT => 'расход',
		);

		if($key)
			return $result[$key];
		else
			return $result;
	}

	public function getTypeStr()
	{
		if($this->type==self::TYPE_IN)
			return self::typeArr(self::TYPE_IN);
		elseif($this->type==self::TYPE_OUT)
			return self::typeArr(self::TYPE_OUT);
	}

	public function getAmountStr()
	{
		return formatAmount($this->amount, 2);
	}

	public function getCommentStr()
	{
		return shortText($this->comment, 80);
	}

	public function getErrorStr()
	{
		return shortText($this->error, 80);
	}

	public function getDateAddStr()
	{
		return date('d.m H:i:s', $this->date_add);
	}

	public function getWalletStr()
	{
		if($this->type==self::TYPE_IN)
			return 'с &nbsp;&nbsp;'.$this->wallet;
		elseif($this->type==self::TYPE_OUT)
			return 'на '.$this->wallet;
	}

	public function getUser()
	{
		if($this->user_id)
			return User::model()->findByPk($this->user_id);
	}

	public function getUserStr()
	{
		if($this->user_id)
			return $this->getUser()->name;
	}

	/*
	 * зеркало функции Transaction::controlStatsIn() для RillTransaction
	 */
	public static function controlStatsIn($timestampFrom, $timestampTo, $userId=false, $justSum=false)
	{
		$limit = 2000;

		$userCond = '';

		if($justSum)
			$result = 0;
		else
			$result = array();

		if($userId  and $user = User::getUser($userId) and $user->role==User::ROLE_USER)
		{
			$userCond = " AND `user_id`='{$user->id}'";
		}

		if($timestampFrom and $timestampTo >= $timestampFrom)
		{
			/*
			 * старый алг
			#######################################
			//пятница
			$dayXCond = " AND (`date_add`<=1449194400 OR `date_add`>=1449280800)";
			$dayXCond
			#######################################
			*/
			$condition = "
					 `type`='".self::TYPE_IN."'
					 AND `status`='".self::STATUS_SUCCESS."'
					 AND `date_add`>=$timestampFrom AND `date_add`<$timestampTo
					 $userCond
					 ";

			$count = self::model()->count($condition);

			//array('account_id'=>amount)
			$preRes = array();

			for ($i = 0; $i < ($count / $limit); $i++)
			{
				$models = self::model()->findAll(array(
					'select'=>"`amount`,`account_id`",
					'condition' => $condition,
					'order'=>"`id` DESC",
					'limit' => $limit,
					'offset' => $i * $limit,
				));


				if($justSum)
				{
					foreach ($models as $model)
						$result += $model->amount;
				}
				else
				{
					foreach ($models as $model)
						$preRes[$model->account_id] += $model->amount;
				}
			}

			if($preRes)
			{
				foreach($preRes as $accountId=>$amount)
				{
					$result[Account::model()->findByPk($accountId)->login] = $amount;
				}
			}
		}

		return $result;
	}

	/*
	 * сколько за текущий день отправлено в ручей
	 */
    public static function todayLimit()
    {
        $timestampFrom = strtotime(date('d.m.Y')) + 5*3600;

        if(intval(date('H')) < 5)
            $timestampFrom -= 3600*24;

        $timestampTo = $timestampFrom + 3600*24;

        return self::controlStatsIn($timestampFrom, $timestampTo, false, true);
    }

	public static function isTodayLimit()
	{
		$timestampFrom = strtotime(date('d.m.Y')) + 5*3600;

        if(intval(date('H')) < 5)
            $timestampFrom -= 3600*24;

        $timestampTo = $timestampFrom + 3600*24;

        $cfg = cfg('rill');

        if(config('rill_day_limit')!==false)
            $cfg['day_limit'] = config('rill_day_limit');

        if(self::controlStatsIn($timestampFrom, $timestampTo, false, true) >= $cfg['day_limit'])
            return true;
        else
            return false;
	}

	/*
	 * сколько пришло на кошельки менеджеров за текущий день
	 */
	public static function todayInAmount()
	{
		$timestampFrom = strtotime(date('d.m.Y')) + 5*3600;

		if(intval(date('H')) < 5)
			$timestampFrom -= 3600*24;

		$timestampTo = $timestampFrom + 3600*24;

		return Transaction::controlStatsIn($timestampFrom, $timestampTo, false, true);
	}

}