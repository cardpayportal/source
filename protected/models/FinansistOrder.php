<?php

/**
 * @property int id
 * @property User user
 * @property float amount_send
 * @property int group_id номер цепочки с которой сливать (для гф)
 * @property int groupIdStr
 * @property int client_id
 * @property int user_id
 * @property string to
 * @property string comment
 * @property string status
 * @property int date_add
 * @property float amount
 * @property string amountStr
 * @property string amountSendStr
 * @property string dateAddStr
 * @property string userStr
 * todo: проверку при сливе: чтобы один кошель не попал под слив разных клиентов
 * @property int date_select	дата выборки - для ГФ, чтобы помечать кошельки
 * @property float completePercent	процент выполнения
 * @property int priority	приоритет
 * @property Client client	клиент с которого сливается
 * @property string statusStr	клиент с которого сливается
 * @property float estmatedAmount	сумма предположительных транзакций
 *
 */
class FinansistOrder extends Model
{
	const SCENARIO_ADD = 's_add';
	const STATUS_WAIT = 'wait'; //ожидание оплаты
	const STATUS_ERROR = 'error';	//при оплате произошла ошибка
	const STATUS_DONE = 'done';	//пометил заказ оплаченым

	//приоритет оплаты
	const PRIORITY_STD = 0;
	const PRIORITY_BIG = 1;

	private $clientObj;	//кэш клиент
	
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}
	
	public function tableName()
	{
		return '{{finansist_order}}';
	}
	
	public function attributeLabels()
	{
		return array(
			'user_id'=>'Пользователь',
			'client_id'=>'Клиент',
			'to'=>'Кому',
			'amount'=>'Сумма',
			'comment'=>'Комментарий',
			'amount_send'=>'Отправленная сумма',	//сколько уже перечислено
			'status'=>'Статус',
			'priority'=>'Приоритет',
			'date_select'=>'Дата выборки',
		);
	}
	
	public function beforeValidate()
	{
		$this->amount = str_replace(',', '.', $this->amount);
		$this->amount_send = str_replace(',', '.', $this->amount_send);
		
		return parent::beforeValidate();
	}
	
	
		
	public function rules()
	{
		return array(
			array('user_id', 'exist', 'className'=>'User', 'attributeName'=>'id', 'allowEmpty'=>false),
			array('client_id', 'exist', 'className'=>'Client', 'attributeName'=>'id', 'allowEmpty'=>false),
			array('to', 'toValidator', 'allowEmpty'=>false),
			array('amount', 'numerical', 'min'=>cfg('min_balance'), 'max'=>config('finansist_max_transaction'), 'allowEmpty'=>false),
			array('status', 'in', 'range'=>array_keys(self::statusArr()), 'on'=>'update'),
			array('error,comment,amount_send,error_count,priority, date_select', 'safe'),
			array('user_id', 'finValidator', 'on'=>self::SCENARIO_ADD),
		);
	}

	/*
	 * проверить является ли юзер фином
	 * если добавляет глобал фин то проверить стоит ли он у руля
	 */
	public function finValidator($attribute=false, $params=false)
	{
		$user = User::getUser($this->user_id);
		$сlient = Client::model()->findByPk($this->client_id);

		if($user->role != User::ROLE_FINANSIST and $user->role != User::ROLE_GLOBAL_FIN)
			$this->addError('user_id', 'У вас нет прав для добавления заявки');
		elseif($user->role == User::ROLE_FINANSIST and $this->client_id != $user->client_id)
			$this->addError('user_id', 'Вы не можете добавить заявку этому клиенту');
		elseif($user->role == User::ROLE_FINANSIST and $сlient->global_fin)
			$this->addError('user_id', 'На клиенте включен globalFin');
		elseif($user->role == User::ROLE_GLOBAL_FIN and !$user->is_wheel)
			$this->addError('user_id', 'Невозможно добавить заявку, у штурвала другой пользователь');
		elseif($user->role == User::ROLE_GLOBAL_FIN and !$сlient->global_fin)
			$this->addError('user_id', 'У этого клиента не включен globalFin');
	}

	public function toValidator($attribute=false, $params=false)
	{
		$regExpQiwi = cfg('wallet_reg_exp');
		$regExpYandex = cfg('regExpYandexWallet');

		if(!preg_match($regExpQiwi, $this->to) and !preg_match($regExpYandex, $this->to))
			$this->addError('to', 'Неверный кошелек получателя');

	}
	
	protected function beforeSave()
	{
		if($this->scenario==self::SCENARIO_ADD)
		{
			$this->status = self::STATUS_WAIT;
			$this->date_add = time();

			//задать group_id если gf
			$user = User::getUser($this->user_id);

			if($user->role === User::ROLE_GLOBAL_FIN)
			{
				$this->group_id = self::getGroup($this->client_id, $this->to);

				if(!$this->group_id)
					return false;
			}

			if($this->comment)
				$this->comment = strip_tags($this->comment);
		}

		return parent::beforeSave();
	}
	
	public static function statusArr($key=false)
	{
		$result = array(
			self::STATUS_WAIT=>'в процессе',
			self::STATUS_ERROR=>'ошибка',
			self::STATUS_DONE=>'завершен',
		);
		
		if($key)
			return $result[$key];
		else
			return $result;
	}
	
	/**
	 * массовое добавление платежей
     * $ignoreSamePayments - добавить платежи без проверки схожих платежей за 48 часов
	 */
	public static function add($params, $ignoreSamePayments=false)
	{
		$doneCount = 0;

		$user = User::getUser();
		
		if(self::checkPayPass($params['extra'], $user->id))
		{
			if($user and ($user->role==User::ROLE_FINANSIST OR $user->role==User::ROLE_GLOBAL_FIN))
			{

				$content = $params['transContent'];
				
				$sep = Tools::getSep($content);
				
				$rows = explode($sep, $content);
				
				$payments = array();

				$allAmount = 0;
				
				foreach($rows as $key=>$row)
				{
					$row = trim($row);
					
					if(!$row)
						continue;

					if(preg_match('!^(\+\d{11,12}|\d{15});([\d\.]+)(;flash|)$!', $row, $res))
					{
						$attributes = array(
							'user_id'=>$user->id,
							'client_id'=>($user->role == User::ROLE_GLOBAL_FIN) ? $params['clientId'] : $user->client_id,
							'to'=>$res[1],
							'amount'=>$res[2],
							'comment'=>$params['comment'],
						);

						if($res[3])
							$attributes['priority'] = self::PRIORITY_BIG;
						else
							$attributes['priority'] = self::PRIORITY_STD;
						
						$payments[] = $attributes;

						$allAmount += $attributes['amount'];
					}
					else
					{
						self::$lastError = 'ошибка в формате платежа на : '.($key+1).' строке';
						return false;
					}
				}
				
				if(!$payments)
				{
					self::$lastError = 'данных платежей не найдено';
					return false;
				}



				//отсекать одновременны платежи на один кошелек
				if(!cfg('ignoreCloneFinOrders'))
				{
					foreach($payments as $key=>$payment)
					{
						if(self::model()->find("`to`='$payment[to]'  and `status`='wait'"))
						{
							self::$lastError = 'на кошелек '.$payment['to'].' уже идет оплата';
							return false;
						}

						foreach($payments as $key1=>$payment1)
						{
							if($payment1['to'] === $payment['to'] and $key1 != $key)
							{
								self::$lastError = 'одновременные оплаты на один кошелек запрещены';
								return false;
							}
						}
					}
				}


				if(!$ignoreSamePayments)
                {
                    //поиск схожих платежей
                    $samePayments = array();

                    $dateStart = time()-48*3600;

                    foreach($payments as $payment)
                    {

                        if($models = self::model()->findAll(array(
                            'condition'=>"`to`='$payment[to]' and `user_id`='{$user->id}' and `amount`=$payment[amount] and `date_add`>$dateStart and (`amount_send`>0 or `status`='wait')",
                            'order'=>"`date_add` DESC",
                        )))
                        {
                            foreach($models as $model)
                                $samePayments[$model->id] = $model;
                        }
                    }

                    if($samePayments)
                    {
                        self::$someData = $samePayments;
                        self::$lastError = 'same_payments';
                        return false;
                    }
                }

				
				foreach($payments as $payment)
				{
					$model = new self;
					$model->scenario = self::SCENARIO_ADD;
					
					$model->attributes = $payment;
					
					if($model->save())
						$doneCount++;
					else
						return false;
				}	
			}
			else
				self::$lastError = 'у пользователя нет прав на добавление перевода';
		}
		else
			self::$lastError = 'неверный платежный пароль';

		if($doneCount)
			GlobalFinLog::add('добавлено '.$doneCount.' переводов', $user->id);
		
		return $doneCount;
	}
	
	public function getAmountStr()
	{
		return '<nobr>'.formatAmount($this->amount, 2).'</nobr>';
	}
	
	public function getAmountSendStr()
	{
		return '<nobr>'.formatAmount($this->amount_send, 2).'</nobr>';
	}
	
	public function getCommentStr()
	{
		return shortText($this->comment, 80);
	}
	
	public function getErrorStr()
	{
		return $this->error;
	}
	
	public function getStatusStr()
	{
		if($this->error)
			$errorStr = ' ('.$this->error.')';
		
		if($this->status==self::STATUS_ERROR)
			return '<span class="error">ошибка:'.$errorStr.'</span>';
		elseif($this->status==self::STATUS_WAIT)
		{
			//возможность поставить ордер на отмену
			$cancelStr = '';
			
			if($this->for_cancel)
			{
				$str = '<span class="wait">отменяется</span>';
			}
			else
			{
				$str = '<span class="wait">в процессе на: '.$this->getCompletePercent().'%</span>';

				$cancelStr = '
					<br>
					<form method="post">
						<input type="hidden" name="params[id]" value="'.$this->id.'"/>
						<input type="submit" name="cancelOrder" value="отменить"/>
					</form>
				';

			}

			return $str.$cancelStr;
		}
		elseif($this->status==self::STATUS_DONE)
			return '<span class="complete">завершен на '.$this->getCompletePercent().'%</span>';

	}
	
	public function getDateAddStr()
	{
		return date('d.m.Y H:i', $this->date_add);
	}

	/**
	 * @return string
	 */
	public function getCompletePercent()
	{
		return formatAmount($this->amount_send/$this->amount*100, 0);
	}

	public function getClient()
	{
		if(!$this->clientObj)
			$this->clientObj = Client::model()->findByPk($this->client_id);

		return $this->clientObj;
	}
	
	/**
	 * возвращяет незавершенные заявки для исполнения в Account::startCheckOut(),
	 * с момента добавления которых прошло 5 минут(чтобы фин мог отменить заявку)
	 * @param int $clientId - id клиента
	 * @param int $groupId группа (если у клиента включен global_fin)
	 * @return self[]
	 */
	public static function currentOrders($clientId, $groupId = null)
	{
		//если после добавления ордера не прошло 5 минут
		$dateNeed = time() - config('finansist_start_order_after');

		$groupCond = '';

		if($groupId)
			$groupCond = " AND `group_id`=$groupId";
		
		$models = self::model()->findAll(array(
			'condition'=>"`status`='".self::STATUS_WAIT."' AND `date_add` < $dateNeed".$groupCond,
			'order'=>"`priority` DESC, `id` ASC, `error` ASC",//"`priority` DESC, `date_add` ASC, `error_count` ASC, `error` ASC",
		));

		//фильтровать по $clientId
		foreach($models as $key=>$model)
		{
			if($model->for_cancel and $model->status == self::STATUS_WAIT)
			{
				$model->cancel();
				unset($models[$key]);
				continue;
			}

			if($model->client_id !== $clientId)
				unset($models[$key]);
		}

		return $models;
	}
	
	/**
	 * завершить заявку
	 */
	public function complete()
	{
		$this->status = self::STATUS_DONE;
		return $this->save();
	}
	
	public function getUser()
	{
		if($this->user_id)
			return User::model()->findByPk($this->user_id);
	}
	
	public function getUserStr()
	{
		if($user = $this->getUser())
			return $user->name;
	}
	
	/**
	 * информация для финансиста об ордерах
	 */
	public static function getInfo($models)
	{
		$result = array(
			'order_count'=>0,
			'wait_count'=>0,
			'error_count'=>0,
            'amount_send'=>0,
		);
		
		foreach($models as $model)
		{
			$result['order_count']++;
			
			if($model->status==self::STATUS_ERROR)
				$result['error_count']++;
				
			if($model->status==self::STATUS_WAIT)
				$result['wait_count']++;

            $result['amount_send'] += $model->amount_send;
		}
		
		return $result;
	}
	
	public static function checkPayPass($pass, $userId)
	{
		return PayPass::check($pass, $userId);
	}
	
	public static function forCancel($id, $userId)
	{
		$user = User::getUser($userId);
		$model = self::model()->findByPk($id);
		$client = $model->client;

		if($model)
		{
			if(
				($user->role == User::ROLE_FINANSIST and $model->user_id == $userId)
				or
				//если юзер - ГФ и у штурвала и на клиенте включен ГФ
				($user->role == User::ROLE_GLOBAL_FIN and $user->is_wheel and $client->global_fin)
			)
			{
				if($model->status==self::STATUS_WAIT)
				{
					if(!$model->for_cancel)
					{
						if(time() - $model->date_add < config('finansist_start_order_after'))
						{
							//отменить сразу
							$model->cancel();
						}
						else
						{
							//пометить для отмены
							self::model()->updateByPk($id, array(
								'for_cancel'=>1,
							));
						}

						return true;
					}
					else
						self::$lastError = 'платеж уже поставлен на отмену';

				}
				else
					self::$lastError = 'неверный статус перевода, возмжно платеж уже завершен или прерван с ошибкой';
			}
			else
				self::$lastError = 'у вас нет прав на отмену этого платежа';
		}
		else
			self::$lastError = 'перевод не найден';
	}
	
	public function cancel($error = 'отменено пользователем')
	{

		self::model()->updateByPk($this->id, array(
			'status'=>self::STATUS_ERROR,
			'error'=>$error,
		));
									
		toLogRuntime('перевод ID='.$this->id.' отменен : '.$error);
		
		return true;
	}

	/*
	 * считает общую сумму выполненных(отправлено) и в процессе(вся сумма) платежей выбранного фина
	 */
	public static function getPaymentsAmount($userId, $timestampFrom, $timestampTo)
	{
		$result = 0;

		$user = User::getUser($userId);

		if($user and ($user->role == User::ROLE_MODER or $user->role == USER::ROLE_CONTROL2))
		{
			if($timestampTo > $timestampFrom)
			{
				$models = self::model()->findAll("`user_id`='{$user->id}' and `date_add`>=$timestampFrom and `date_add`<$timestampTo");

				foreach($models as $model)
				{
					if($model->status==self::STATUS_DONE or $model->status==self::STATUS_ERROR)
						$result += $model->amount_send;
					elseif($model->status==self::STATUS_WAIT)
						$result += $model->amount;
				}
			}
			else
			{
				self::$lastError = 'неверный интервал дат';
				return false;
			}
		}
		else
		{
			self::$lastError = 'неверный тип пользователя';
			return false;
		}

		return $result;
	}

	/*
	 * установить у всех кошельков в платежном поле одинаковые суммы
	 */
	public static function contentSetAmount($amount, $content)
	{
		$result = '';

		if(!$amount or !$content)
		{
			self::$lastError = 'не указаны кошельки или сумма';
			return false;
		}

		$amount = str_replace(array(',', ' '), array('.', ''), $amount);
		$amount = $amount*1;

		$sep = Tools::getSep($content);

		$rows = explode($sep, $content);

		$wallets = array();

		foreach($rows as $row)
		{
			$row = trim($row);

			if(!$row)
				continue;

			if(!preg_match('!(\+\d{11,12})!', $row, $res))
			{
				self::$lastError = 'неверно указан один из кошельков';
				return false;
			}

			$wallets[] = $res[1].';'.$amount;
		}

		$result = implode($sep, $wallets);

		return $result;
	}

	/**
	 * @return string
	 */
	public function getGroupIdStr()
	{
		return ($this->group_id) ? '<span title="сливается только с этой цепочки" class="green">'.$this->group_id.'</span>' : '<span title="сливается со всех цепочек" class="warning">нет</span>';
	}

	/**
	 * получение группы для перевода gf
	 * @param int $clientId
	 * @param string $to кошелек на который идет слив
	 * @return int|false
	 */
	public static function getGroup($clientId, $to)
	{
		//одновременно с группы можно сливать на .. кошельков
		$groupWalletAtOnceCount = 10;

		//['group id1'=>300, 'group id2'=>4000, ...] - суммы балансов групп
		$groups = Client::getSumOutBalanceWithGroups($clientId);
		//сортируем по убыванию баланса групп
		arsort($groups);

		//тестовый алгоритм распределения кошельков в группы равномерно по их количеству в них
		if($clientId != 13)
		{
			//апи льет тока на один кош

			$newArr = array();

			foreach($groups as $groupId=>$balance)
				$newArr[$groupId] = self::getWalletCountAtGroup($clientId, $groupId);

			asort($newArr);
			$groups = $newArr;
		}

		foreach($groups as $groupId=>$val)
		{
			if(self::model()->count("`client_id`=$clientId AND `group_id`=$groupId AND `status`='".self::STATUS_WAIT."'") >= $groupWalletAtOnceCount)
				self::$lastError = 'со всех цепочек уже сливается по максимуму (по  '.$groupWalletAtOnceCount.' кошельков)';
			elseif($model = self::model()->find("`to`='$to' AND `group_id`>0 AND `amount_send`>0"))
			{
				//вспоминаем группу кошелька
				/**
				 * @var self $model
				 */
				self::$lastError = '';
				return $model->group_id;
			}
			else
				return $groupId;
		}

		if(!self::$lastError)
			self::$lastError = 'не удалось найти подходящую группу для этого коша';

		return false;
	}

	/**
	 * кол-во кошельков (to), на которые у $clientId в $groupId в данный момент идет слив
	 * @param int $clientId
	 * @param int $groupId
	 * @param bool $withCache
	 * @return int
	 */
	public static function getWalletCountAtGroup($clientId, $groupId, $withCache = false)
	{
		$cacheName = "WalletCountAtGroup_{$clientId}_{$groupId}";
		$cacheTime = 300;

		if($withCache)
		{
			if($count = Yii::app()->cache->get($cacheName))
				return $count;
		}

		$count = self::model()->count("`status`='".self::STATUS_WAIT."' AND `client_id`=$clientId AND `group_id`=$groupId");

		Yii::app()->cache->set($cacheName, $count, $cacheTime);

		return $count;
	}

	/**
	 * попытка распределить кошельки, на которые еще нет заливов, между группами, на которые не хватает
	 * помещает первый попавшийся свободный кошелек в группу $groupId
	 * @param int $clientId
	 * @param int $groupId
	 * @param bool $withoutReplace - если true то функция просто возвращает true и не производит замены(self::getWarnings())
	 * @return bool
	 */
	public static function recombineGroups($clientId, $groupId, $withoutReplace = false)
	{
		//найти все неначатые ордера клиента
		$orders = self::model()->findAll("
			`client_id`=$clientId
			AND `status`='".self::STATUS_WAIT."'
			AND `amount_send`=0
		");
		/**
		 * @var self[] $orders
		 */

		//убедиться что эти кошельки не юзались ранее и сформировать массив [groupId=>[order1,order2...], ...]
		$groupCountArray = array();
		$groupCountArraySort = array();	//чтобы отсортировать первый

		foreach($orders as $order)
		{
			if(!self::model()->find("`to`='{$order->to}' AND `amount_send`>0 AND `group_id`>0"))
			{
				$groupCountArray[$order->group_id][] = $order;
				$groupCountArraySort[$order->group_id]++;

			}
		}

		arsort($groupCountArraySort);
		$groupIdNeed = key($groupCountArraySort);	//нужная группа

		if(isset($groupCountArray[$groupIdNeed]))
		{
			$order = current($groupCountArray[$groupIdNeed]);

			if($withoutReplace)
				return true;

			//print_r($groupCountArray);
			//print_r($groupCountArraySort);
			//print_r($order);
			//die('ff');

			self::model()->updateByPk($order->id, array('group_id'=>$groupId));
			//toLog('рекомбинация: Client'.$clientId.': '.$order->group_id.' => '.$groupId.' (orderId='.$order->id.')');
			return true;
		}
		else
			return false;
	}

	/**
	 * массив предупреждений для глобал фина: о необходимости добавить еще кошельков на слив
	 *
	 * @return array
	 */
	public static function getWarnings()
	{
		$result = array();

		$groupArr = Client::getSumOutBalanceWithGroups();

		$criticalAmount = 30000;

		foreach($groupArr as $clientId=>$groups)
		{
			$clientBalance = 0;

			foreach($groups as $groupId=>$balance)
			{
				if(cfg('ignoreGroupsFinOrder'))
				{
					$clientBalance += $balance;
				}
				else
				{
					//слив по группам
					if(
						$balance > cfg('min_balance')
						//слив с остальных групп идет
						and self::model()->find("`client_id`=$clientId and `status`='".self::STATUS_WAIT."'")
						and !self::model()->find("`client_id`=$clientId and `status`='".self::STATUS_WAIT."' AND `group_id`=$groupId")
						and !self::recombineGroups($clientId, $groupId, true)
					)
					{
						$result[] = 'недостаточно кошельков для слива '.Client::modelByPk($clientId)->name.'(groupId='.$groupId.') ('.formatAmount($balance, 0).' руб)';
						break 1;
					}
				}

			}

			if(
				$clientBalance > $criticalAmount//cfg('minBalanceForTrans')
				and !self::model()->find("`client_id`=$clientId and `status`='".self::STATUS_WAIT."'")
			)
			{
				$result[] = 'недостаточно кошельков для слива '.Client::modelByPk($clientId)->name. ' ('.formatAmount($balance, 0).' руб)';
			}
		}

		return $result;
	}

	/**
	 * выдает кошельки с выбранных заявок
	 * @param self[] $models
	 * @param array $orderIds|null если передано то выбирает их из списка $models,
	 * 	если нет то выбирает все невыбранные из $models, на которые было чтото залито
	 * @return array
	 *
	 */
	public static function selectWallets(array $models, $orderIds = array())
	{
		$result = array();

		$date = time();

		foreach($models as $model)
		{

			if($orderIds and is_array($orderIds))
			{
				if(!in_array($model->id, $orderIds))
					continue;
			}
			else
			{
				if($model->date_select or $model->amount_send < 2)
					continue;
			}

			$model->date_select = $date;

			if($model->save())
			{
				$result[$model->to] = $model->to;
			}
			else
			{
				self::$lastError = 'ошибка сохранения';
				break;
			}
		}

		return $result;
	}

	/**
	 * выбрать все невыбранные и со прогрессом 100%
	 * @param self[] $models
	 * @return array
	 */
	public static function selectWalletsComplete(array $models)
	{
		$result = array();

		$date = time();

		foreach($models as $model)
		{
			if(
				!$model->date_select
				and (
					$model->status == FinansistOrder::STATUS_DONE
					or $model->completePercent == 100
				)
			)
			{
				$model->date_select = $date;

				if($model->save())
				{
					$result[$model->to] = $model->to;
				}
				else
				{
					self::$lastError = 'ошибка сохранения';
					break;
				}
			}
		}

		return $result;
	}

	/**
	 * история выборки кошелей, по дате
	 * @param int $dateStart
	 * @return array ['01.01.2017' => ['+782323823232', '+723263872362', ...]]
	 */
	public static function getSelectHistory($dateStart = 0)
	{
		$result = array();

		$models = self::model()->findAll(array(
			'condition'=>"`date_select` > $dateStart",
			'order'=>"`date_select` DESC",
		));

		/**
		 * @var self[] $models
		 */

		foreach($models as $model)
			$result[$model->date_select][$model->to] = $model->to;

		return $result;
	}

	/**
	 * платежи на эту заявку из таблицы TransactionEstmated
	 *
	 */
	public function getEstmatedAmount()
	{
		$result = 0;

		$models = TransactionEstmated::model()->findAll("
			`wallet`='{$this->to}' AND `date_add_db` > $this->date_add AND `is_actual`=1
		");

		/**
		 * @var TransactionEstmated[] $models
		 */

		foreach($models as $model)
			$result += $model->amount;

		return $result;
	}

}