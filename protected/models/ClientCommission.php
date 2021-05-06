<?php

/**
 * Индивидуальные комиссии для клиентов
 * Если клиенту назначена индивидуальная комиссия и она активна то используется она при рассчете курса
 * Действия: добавить, редактировать в общей таблице, отключить
 * @property int id
 * @property int client_id Клиент (если пусто то это дефолтное правило) - уникальное поле
 * @property string rate_source Источник курса: 'finam'|'btce'
 * @property float bonus_percent Прибавляемый процент
 * @property float bonus_rub Прибавляемый рубль
 * @property int date_edit Дата добавления|редактирования
 * @property bool is_active Возможность отключить индивидуальный рассчет
 * @property string dateEditStr
 * @property Client client
 * @property string clientStr
 * @property string rateSourceSelect - html select
 * @property string rateSourceStr
 * @property string isActiveSelect - html select
 * @property float rateValue рассчитанное по формуле значение для правила
 * @property float rateValueSource чистый курс с источника
 * @property float fix если задан, то в результат выдается он, без процентов и добавок
 * @property float ym_card_percent
 * @property float qiwi_percent
 * @property float rateValueYmCard
 * @property float rateValueQiwi
 * @property float ym_card_bonus
 * @property float rise_x_bonus
 * @property float qiwi_yad_percent
 *
 */
class ClientCommission extends Model
{
	const SCENARIO_ADD = 'add';
	const SCENARIO_EDIT = 'edit';

	const RATE_SOURCE_FINAM = 'finam';
	const RATE_SOURCE_BTCE = 'btce';

	public static function model($className = __CLASS__)
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
		return '{{client_commission}}';
	}

	public function beforeValidate()
	{
		$this->bonus_percent = str_replace(',', '.', $this->bonus_percent);
		$this->bonus_rub = str_replace(',', '.', $this->bonus_rub);
		$this->fix = str_replace(',', '.', $this->fix);

		return parent::beforeValidate();
	}

	public function rules()
	{
		return array(
			array('client_id', 'exist', 'className'=>'Client', 'attributeName'=>'id', 'allowEmpty'=>true, 'message'=>'Клиент не найден', 'on'=>self::SCENARIO_ADD),
			array('client_id', 'unique', 'className'=>__CLASS__, 'attributeName'=>'client_id', 'on'=>self::SCENARIO_ADD, 'message'=>'Для этого клиента уже есть правило'),
			array('rate_source', 'in', 'range'=>array_keys(self::rateSourceArr()), 'allowEmpty'=>false),
			array('bonus_percent', 'numerical', 'min'=>0.001, 'max'=>20, 'allowEmpty'=>false, 'message'=>'Процент должен быть больше нуля'),
			array('bonus_rub', 'numerical', 'min'=>0, 'max'=>99, 'allowEmpty'=>true, 'message'=>'Бонус должен числом'),
			array('is_active', 'isActiveValidator', 'on'=>self::SCENARIO_EDIT, 'allowEmpty'=>true),
			array('fix', 'numerical', 'min'=>0, 'max'=>99, 'allowEmpty'=>true),
			['ym_card_percent, qiwi_percent, qiwi_yad_percent', 'numerical', 'min'=>0, 'max'=>99, 'allowEmpty'=>true],
			['ym_card_bonus, rise_x_bonus', 'numerical', 'min'=>-99, 'max'=>99, 'allowEmpty'=>true],
		);
	}

	public function isActiveValidator()
	{
		if($this->is_active != 0 and $this->is_active != 1)
			$this->addError('is_active', 'Недопустимое значение свойства is_active');

		if($this->client_id == 0 and $this->is_active == 0)
			$this->addError('is_active', 'Дефолтное правило не может быть отключено');
	}

	public function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
			$this->is_active = 1;

		$this->date_edit = time();

		return parent::beforeSave();
	}

	public static function rateSourceArr($key = false)
	{
		$arr = array(
			self::RATE_SOURCE_FINAM => 'Finam',
			self::RATE_SOURCE_BTCE => 'BTCE',
		);

		if($key)
			return $arr[$key];
		else
			return $arr;
	}

	public function getDateEditStr()
	{
		return ($this->date_edit) ? date(cfg('dateFormat'), $this->date_edit) : '';
	}

	/**
	 * список всех моделей
	 * @return ClientCommission[]
	 */
	public static function getList()
	{
		return self::model()->findAll(array(
			'condition'=>"",
			'order'=>"`client_id`",
		));
	}

	/**
	 * @return bool|Client
	 */
	public function getClient()
	{
		return ($this->client_id) ? Client::modelByPk($this->client_id) : false;
	}

	public function getClientStr()
	{
		if($client = $this->getClient())
			return $client->name;
		else
			return 'по-умолчанию';
	}

	/**
	 * выбор источника курса
	 * @return string
	 */
	public function getRateSourceSelect()
	{
		$result = '<select name="params['.$this->id.'][rate_source]">';

		foreach(self::rateSourceArr() as $key=>$val)
		{
			$selected = ($this->rate_source == $key) ? ' selected="selected"' : '';

			$result .= '<option value="'.$key.'"'.$selected.'>'.$val.'</option>';
		}

		$result .= '</select>';

		return $result;
	}

	/**
	 * выбор источника курса
	 * @return string
	 */
	public function getIsActiveSelect()
	{
		if($this->client_id)
		{
			//невозможно отключить дефолтное правило

			$result = '<select name="params['.$this->id.'][is_active]">';

			$result .= '<option value="0"'.((!$this->is_active) ? ' selected="selected"' : '' ).'>Выкл</option>';
			$result .= '<option value="1"'.(($this->is_active) ? ' selected="selected"' : '' ).'>Вкл</option>';

			$result .= '</select>';
		}
		else
			$result = 'Вкл';

		return $result;
	}

	/**
	 * получить рассчитанный курс USD
	 * @return float
	 */
	public function getRateValue()
	{
		//если задан fix то возвращать его
		if($this->fix > 0)
			return $this->fix;

		$usdRateSource = 0;

		if($this->rate_source == self::RATE_SOURCE_FINAM)
			$usdRateSource = config('usd_rate_parse_value');
		elseif($this->rate_source == self::RATE_SOURCE_BTCE)
			$usdRateSource = config('usd_rate_parse_value_btce');

		$percent = $this->bonus_percent/100 + 1;
		$bonusRub = $this->bonus_rub*1;
		$rate = $usdRateSource * $percent + $bonusRub;


		$result = $rate * 0.99;	//новое условие: минус 1 проц от результата


		return round($result, 2);
	}

	/**
	 * чистый курс с источника
	 * @return float
	 */
	public function getRateValueSource()
	{
		$result = 0;

		if($this->rate_source == self::RATE_SOURCE_FINAM)
			$result = config('usd_rate_parse_value');
		elseif($this->rate_source == self::RATE_SOURCE_BTCE)
			$result = config('usd_rate_parse_value_btce');

		return round($result, 2);
	}

	/**
	 * изменить все сразу
	 * @param array $params
	 * @return int сколько
	 */
	public static function editMany(array $params)
	{
		$editCount = 0;

		foreach($params as $commissionId=>$paramsArr)
		{
			if($model = self::model()->findByPk($commissionId))
			{
				/**
				 * @var ClientCommission $model
				 */

				$model->scenario = self::SCENARIO_EDIT;
				$model->attributes = $paramsArr;

				if($model->save())
					$editCount++;
				else
				{
					self::$lastError = 'Правило № '.$model->id.': '.self::$lastError;
					break;
				}
			}
			else
				self::$lastError = 'не найдена модель '.$commissionId;
		}

		return $editCount;

	}

	/**
	 * добавить правило
	 * @param $params
	 * @return bool
	 */
	public static function add($params)
	{
		$model = new self;
		$model->scenario = self::SCENARIO_ADD;
		$model->attributes = $params;

		return $model->save();
	}

	/**
	 * @return string
	 */
	public function getRateSourceStr()
	{
		return self::rateSourceArr($this->rate_source);
	}

	/**
	 * получить рассчитанный курс для Яндекс карт
	 * @return float
	 */
	public function getRateValueYmCard()
	{
		$rateValue = $this->getRateValue();

		$percent = $this->ym_card_percent/100 + 1;

		return round($rateValue * $percent, 2);
	}

	/**
	 * получить рассчитанный курс для Qiwi
	 * @return float
	 */
	public function getRateValueQiwi()
	{
		$rateValue = $this->getRateValue();

		$percent = $this->qiwi_percent/100 + 1;

		return round($rateValue * $percent, 2);
	}

	/**
	 * получить рассчитанный курс для Qiwi-Yad в обменнике
	 * @return float
	 */
	public function getRateValueQiwiYad()
	{
		$rateValue = $this->getRateValue();

		$percent = $this->qiwi_yad_percent/100 + 1;

		return round($rateValue * $percent, 2);
	}


	/**
	 * @param array $params
	 * @return self
	 */
	public static function getModel(array $params)
	{
		return self::model()->findByAttributes($params);
	}

	/**
	 * возвращает бонусы к приходам по направлениям в процентах
	 * @param int $clientId
	 * @return array ['newYandex'=>-20, 'wex'=>30...]
	 */
	public static function getBonus($clientId)
	{
		$result = [];

		if($model = self::getModel(['client_id'=>$clientId]))
		{
			$result['ym_card_bonus'] = $model->ym_card_bonus;
			$result['rise_x_bonus'] = $model->rise_x_bonus;
		}

		return $result;

	}
}