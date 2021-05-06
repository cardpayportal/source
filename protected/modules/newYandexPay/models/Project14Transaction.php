<?php

/**
 * сохраняем историю яда для сверки с сайта project14
 *
 * Class Project14Transaction
 * @property string sender
 * @property float amount
 * @property int time_pay
 * @property string unique_id
 * @property string wallet
 */
class Project14Transaction extends Model
{
	const SCENARIO_ADD = 'add';

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{project14_transaction}}';
	}

	public function rules()
	{
		return [
			['unique_id', 'unique', 'className'=>__CLASS__, 'attributeName'=>'unique_id', 'message'=>'unique_id уже был добавлен',
				'on'=>self::SCENARIO_ADD],
			['sender, amount, time_pay, wallet', 'safe'],

		];
	}

	/**
	 * @param $wallet
	 * @return array
	 * делаем парсинг всех транзакций по кошельку яда с project14
	 * записываются в таблицу project14_transaction
	 * может понадобиться для сверки
	 * работает преобразование часового пояса в транзакциях
	 */
	public static function saveAllTransactions($wallet)
	{
		//$wallet = '410017416902914';
		$url = 'http://project14.paypro.is/?wallet='.$wallet.'&all_payment';
		$postData = 'login=seogid&password=WFwqarfq2er213e21&google_code=';

		$sender = new Sender;
		$sender->followLocation = true;

		$sender->additionalHeaders = [
			'Host: project14.paypro.is',
			'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.13; rv:65.0) Gecko/20100101 Firefox/65.0',
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate',
			'Referer: http://project14.paypro.is/?wallet='.$wallet.'&all_payment',
			'Content-Type: application/x-www-form-urlencoded',
			'Content-Length: '.strlen($postData),
		];

		$content = $sender->send($url, $postData);

		$pregStr = '!<a class="list-group-item">\s+<i class="fa fa-money fa-fw"></i>(.+?) (\d+\.\d+) руб\s+<span '.
			'class="pull-right text-muted small"><em>(.+?)<(br>ID: |)((.+?)<|)!iu';

		$payments = [];
		if(preg_match_all($pregStr, $content, $res))
		{
			foreach($res[1] as $key=>$name)
			{
				$payments[$key]['sender'] = $name;
				$payments[$key]['amount'] = $res[2][$key];
				$timePay = DateTime::createFromFormat('d.m.y H:i:s',$res[3][$key])->getTimestamp();
				//двигаем часовой пояс
				if($timePay)
					$payments[$key]['time_pay'] = $timePay + 60*60*3;
				$payments[$key]['unique_id'] = $res[6][$key];
				$payments[$key]['wallet'] = $wallet;

				if(self::model()->findByAttributes(['unique_id'=>$payments[$key]['unique_id']]))
				{
					unset($payments[$key]);
					continue;
				}

				$model = new self;
				$model->sender = $payments[$key]['sender'];
				$model->amount = $payments[$key]['amount'];
				$model->time_pay = $payments[$key]['time_pay'];
				$model->unique_id = $payments[$key]['unique_id'];
				$model->wallet = $payments[$key]['wallet'];
				$model->save();
			}

			return $payments;
		}
		else
		{
			$errorMessage = 'Ошибка контента для регулярного выражения';
			toLogError($errorMessage);
			self::$lastError = $errorMessage;
			return $payments;
		}
	}


}