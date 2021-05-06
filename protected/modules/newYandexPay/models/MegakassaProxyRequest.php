<?php

/**
 * статистика по колву запросов за интервал на мегакассу
 * на мегакассе можно с одного прокси 30 запросов в час отправить
 *
 * @property int id
 * @property int proxy_id
 * @property int date_add
 */
class MegakassaProxyRequest extends Model
{
	const SCENARIO_ADD = 'add';

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{megakassa_proxy_request}}';
	}

	public function rules()
	{
		return [
			['id, proxy_id, date_add', 'safe'],
		];
	}

	protected function beforeSave()
	{
		if ($this->scenario == self::SCENARIO_ADD)
			$this->date_add = time();

		return parent::beforeSave();
	}

	public static function addRequest($proxyId)
	{
		$model = new self;
		$model->scenario = self::SCENARIO_ADD;
		$model->proxy_id = $proxyId;

		return $model->save();
	}

	/**
	 * получить прокси для запроса запроса на мегакассу
	 * нандомный из $slice-ти лучших
	 * @return Proxy|false
	 */
	public static function getProxy()
	{
		$cfg = cfg('newYandexPayMegakassa');
		$slice = 10;	//из скольки прокси выдавать рандомный
		$safeCount = 5;	//запас по колву запросов

		$goodProxies = Proxy::getProxies("`category`='$cfg[proxyCategory]'");

		$proxies = [];

		$dateStart = time() - $cfg['proxyRequestInterval'];

		foreach($goodProxies as $goodProxy)
		{
			if(!$goodProxy->isGoodRating)
				continue;

			$requestCount = self::model()->count("`date_add` > $dateStart AND `proxy_id`='{$goodProxy->id}'");

			if($requestCount < $cfg['proxyRequestLimit'] - $safeCount)
				$proxies["$requestCount"] = $goodProxy;
		}

		if(!$proxies)
		{
			toLogError('MegaQiwi: закончились прокси');
			return false;
		}

		ksort($proxies);

		$proxies = array_slice($proxies, 0, $slice, true);

		return $proxies[array_rand($proxies)];
	}

	/**
	 * очистка таблицы от старых запросов
	 */
	public static function startClear()
	{
		$cfg = cfg('newYandexPayMegakassa');
		$dateStart = time() - $cfg['statsClearInterval'];
		$models = self::model()->findAll("`date_add` < $dateStart");

		foreach($models as $model)
		{
			if(!$model->delete())
				return false;
		}

		return true;
	}

}