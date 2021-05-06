<?

/**
 * todo: доделать шаблоны, стату и тд для всех кл
 * правила для отображения модулей у клиентов
 * @property int id
 * @property int client_id
 * @property string module_id
 * @property string rule (on|off)
 */
Class ClientModuleRule extends Model
{
	const RULE_ON = 'on';
	const RULE_OFF = 'off';

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{client_module_rule}}';
	}

	public function rules()
	{
		return [

		];
	}

	/**
	 * @param array $params
	 * @return self
	 */
	public static function getModel($params)
	{
		return self::model()->findByAttributes($params);
	}


	public static function getRuleArr()
	{
		return [
			'sim',
			'qiwi1',
			'qiwi2',
			'yandex',
			'stats',
			'news',
			'profile',
			'pagination',
			'intellectMoney',
			'adgroupMerchYad',
			'testCard',
			'walletS',
			'p2pService',
		];
	}
}