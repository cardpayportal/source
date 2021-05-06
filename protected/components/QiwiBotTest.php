<?php

class QiwiBotTest extends QiwiBot
{

	/**
	 * список всех платежей
	 *
	 * array(
	 * 	'id'=>'',//ID платежа в киви
	 * 	'type'=>'',	//тип (in, out)
	 * 	'wallet'=>'', //кошелек
	 * 	'comment'=>'', //коммент к платежу (cash - снятия с банкомата)
	 * 	'amount'=>'', //сумма включая комиссию
	 * 	'commission'=>0,	//комиссия
	 * 	'status'=>success,wait,error
	 * 	'timestamp'=>2137213612,//метка времени
	 * 	'date'=>22.12.2016,//форматированная дата
	 * 	'error'=>'',
	 * 	'errorCode'=>'',
	 * 	'currency'=>'RUB|KZT', //этот элемент доступен только если включен $allCurrencies
	 *  'operationType'=>'',	//тип операции: '' - обычный приход или расход, 'convert'-конвертация из одной валюты в другую(доп поля currencyFrom)
	 * 		работает только если включена $allCurrencies
	 * 	'currencyFrom'=>'RUB|KZT', //только если operationType == 'convert'. из какой валюты была конвертация
	 * 	'amountFrom'=>'', //сумма в валюте из которой конвертировали (работает только с $allCurrencies==true and operationType =='convert' )
	 * )
	 *
	 * @param string $content
	 * @param bool $allCurrencies если true то парсит все валюты а не только рубли и выдает 'currency'=>'...',
	 * @return array|false
	 */
	protected function getHistory($content, $allCurrencies = false)
	{

		if($this->sender->info['httpCode'][0] != 200)
		{
			$this->error = 'код ответа: '.$this->sender->info['httpCode'][0].' ';
			return false;
		}

		if(!preg_match('!</html>!', $content))
		{
			$this->error = 'контент не догружен (нет </html>)';
			return false;
		}


		//$this->updateBalance($content);
		$arResult = array();

		$contentAll = $content;

		$dom = phpQuery::newDocument($content);

		if($dom)
		{
			if($divs = $dom->find('div[data-container-name=item].status_SUCCESS,div[data-container-name=item].status_PROCESSED,div[data-container-name=item].status_ERROR'))
			{
				foreach($divs as $div)
				{
					$pq = pq($div);

					$content = $pq->html();

					if(strpos($pq->attr('class'), 'status_SUCCESS')!==false)
						$status = 'success';
					elseif(strpos($pq->attr('class'), 'status_PROCESSED')!==false)
						$status = 'wait';
					elseif(strpos($pq->attr('class'), 'status_ERROR')!==false)
						$status = 'error';
					else
						toLogError('error in payment status: '.$contentAll.$pq->attr('class'), 1);

					$errorCode = '';

					if($status=='error')
					{
						//текст ошибки
						if(preg_match('!<a href="\#" class="error" data-action="item-error" data-params=\'\{"message":"(.+?)"\}\'>!', $content, $res))
						{
							$error = trim($res[1]);

							if(preg_match('!Ежемесячный лимит платежей и переводов для статуса!iu', $error))
								$errorCode = self::TRANSACTION_ERROR_LIMIT;
						}
						else
							$error = '';
					}
					else
						$error = '';

					$comment = '';

					//если снятие
					if(preg_match('!<div class="provider">\s+<span>(.+?)</span>!us', $content, $res))
						$provider = $res[1];
					else
					{
						$provider = '';
					}

					$type = '';

					$isCash = 0;

					if(mb_strpos($provider, 'QVP: Снятие наличных в банкомате', 0, 'utf-8')!==false)
					{
						$type = 'out';
					}
					elseif(preg_match('!expenditure">\s+<div class="cash">(.+?)</div>!s', $content, $res))
					{
						$type = 'out';
						$amountFromText = $res[1];
					}
					elseif(preg_match('!income">\s+<div class="cash">(.+?)</div>!s', $content, $res))
					{
						$type = 'in';
						$amountFromText = $res[1];
					}
					else
					{
						$this->error = 'error payment type: '.$content;
						return false;
					}

					if(preg_match('!<div class="originalExpense">\s+<span>(.+?)</span>!s', $content, $res))
					{
						$amountText = $res[1];
					}
					else
					{
						$this->error = 'не найден <div class="originalExpense">'.$content;
						return false;
					}



					$amount = $this->parseAmount($amountText);

					if($amount===false)
					{
						$this->error = 'wrong amount: '.$amountText;
						return false;
					}


					//откуда или куда перевод
					if(preg_match('!<span class="opNumber">(.+?)</span>!u', $content, $res))
						$wallet = $res[1];
					else
					{
						$this->error = 'span class="opNumber"> not found on: '.$content;
						return false;
					}

					//валюта (если $allCurrencies)
					$currency = '';
					$operationType ='';
					$currencyFrom ='';

					//парсим только рубли, если не включен $allCurrencies
					if($allCurrencies)
					{
						$currency = $this->getCurrency($amountText);

						if(!$currency)
						{
							toLogError('неизвестная валюта: '.$amountText.' на кошельке '.$this->login);
							return false;
						}

						$currencyFrom1 = $this->getCurrency($amountFromText);

						if(!$currencyFrom1)
						{
							toLogError('неизвестная валюта: '.$amountFromText.' на кошельке '.$this->login);
							return false;
						}

						//если валюты отличаются и wallet == $this->login то это конвертация
						if($wallet == '+'.$this->login and $currency != $currencyFrom1)
						{
							$operationType = 'convert';
							$currencyFrom = $currencyFrom1;

							$amountFrom = $this->parseAmount($amountFromText);

							if($amount===false)
							{
								$this->error = 'wrong amount: '.$amountFromText;
								return false;
							}
						}

					}
					else
					{
						if(!preg_match('!руб\.!u', $amountText))
						{
							//toLog('currency error found: '.$this->login.': '.$amountText);
							continue;
						}
					}

					//комментарий
					if(!$comment)
					{
						if(preg_match('!<div class="comment">(.*?)</div>!su', $content, $res))
							$comment = $res[1];
						else
						{
							$this->error = 'div class="comment"> not found on: '.$content;
							return false;
						}
					}

					//дата
					if(preg_match('!<span class="date">(.+?)</span>!s', $content, $res))
						$date = trim($res[1]);
					else
					{
						$this->error = 'span class="date"> not found on: '.$content;
						return false;
					}

					//время
					if(preg_match('!<span class="time">(.+?)</span>!s', $content, $res))
						$time = trim($res[1]);
					else
					{
						$this->error = 'span class="time"> not found on: '.$content;
						return false;
					}

					//id
					if(preg_match('!<div class="transaction">(.+?)</div>!s', $content, $res))
						$id = trim($res[1]);
					elseif(preg_match('!href="/report/cheque\.action\?transaction=(\d+)&amp;direction=OUT" class="cheque"!is', $content, $res))
					{
						$id = trim($res[1]);
					}
					elseif(preg_match('!<div class="transaction" data-action="item-extra" data-params=\'\{"data":\{"txn":(\d+)\}\}\'>!is', $content, $res))
					{
						//снятие с карты
						$id = trim($res[1]);
					}
					else
					{
						$this->error = 'div class="transaction"> not found on: '.$content;
						return false;
					}

					$commission = 0;

					if(preg_match('!<div class="commission">(.+?)</div>!s', $content, $res))
					{
						$commissionStr = trim($res[1]);

						if(!empty($commissionStr))
						{
							$commission = $this->parseAmount($commissionStr);

							if($commission===false)
								toLogError('error parse amount on commission', 1);
						}

					}
					else
					{
						$this->error = 'div class="commission"> not found on: '.$content;
						return false;
					}


					if($comment=='cash1')
					{
						//проставить комиссии
						if(!$commission)
						{
							if($amount==10250 or $amount==10000)
							{
								$amount = 10000;
								$commission = 250;
							}
							elseif($amount==5150  or $amount==5000)
							{
								$amount = 5000;
								$commission = 150;
							}
							elseif($amount==4640 or $amount==4500)
							{
								$amount = 4500;
								$commission = 140;
							}
							elseif($amount==4125)
							{
								$amount = 4000;
								$commission = 125;
							}
							else
							{
								$this->error = 'неизвестная сумма снятия ('.$amount.'): '.$content;
								return false;
							}
						}
					}

					$timestamp = strtotime($date.' '.$time);

					$arr = array(
						'id'=>$id,
						'type'=>$type,
						'status'=>$status,
						'amount'=>$amount,
						'commission'=>$commission,
						'wallet'=>$wallet,
						'timestamp'=>$timestamp,
						'date'=>date('d.m.Y H:i', $timestamp),
						'comment'=>$comment,
						'error'=>$error,
						'errorCode'=>$errorCode,
						'is_cash'=>(preg_match('!, карта \d{4}\*\*\*\*\d{4}!iu', $wallet)) ? 1 : 0,
					);

					if($allCurrencies)
					{
						$arr['currency'] = $currency;

						if($operationType)
						{
							$arr['operationType'] = $operationType;
							$arr['amountFrom'] = $amountFrom;
							$arr['currencyFrom'] = $currencyFrom;
						}

					}

					$arResult[] = $arr;

				}

				Tools::multisort($arResult, 'timestamp', SORT_DESC);


			}
			else
			{
				//toLog('no history '.$content);
				return $arResult;
			}
		}
		else
		{
			toLogError('no dom on getHistory');
			return false;
		}

		return $arResult;
	}

	public function getBalance($currency = self::CURRENCY_RUB)
	{
		$tryCount = 2;

		for($i=1; $i<=$tryCount; $i++)
		{
			if($this->updateBalance($currency)===true)
				return $this->balance;

			sleep(rand(1, 2));
		}

		$this->error = 'http_code: '.$this->sender->info['httpCode'][0].$this->lastContent;
		$this->balance = false;

		return $this->balance;
	}

	/**
	 * обновление баланса указанной валюты
	 * @param string $currency
	 * @return bool
	 */
	protected function updateBalance($currency = self::CURRENCY_RUB)
	{
		$this->sender->additionalHeaders = array();

		$content = $this->request('https://qiwi.com/report.action');

		//рублевый счет должен быть полюбому иначе return false
		if(!preg_match('!Счет QIWI, RUB\s+</div>\s+<div class="account_current_amount">\s+(.+?)\s+<span class="account_currency_!isu', $content, $res))
		{
			$this->balance = false;
			return false;
		}

		if($currency === self::CURRENCY_RUB)
		{
			if(preg_match('!Счет QIWI, RUB\s+</div>\s+<div class="account_current_amount">\s+(.+?)\s+<span class="account_currency_!isu', $content, $res))
			{
				$amount = trim($res[1]);

				$amount = str_replace(array('&nbsp;', ',', ' '), array('', '.', ''), $amount);

				$amount = $amount*1;
				$this->balance = str_replace(',', '.', $amount);
				//var_dump($this->balance);
				return true;
			}
			else
			{
				$this->balance = false;
				return false;
			}
		}
		elseif($currency === self::CURRENCY_KZT)
		{
			if(preg_match('!Счет QIWI, KZT\s+</div>\s+<div class="account_current_amount">\s+(.+?)\s+<span class="account_currency_!isu', $content, $res))
			{
				$amount = trim($res[1]);

				$amount = str_replace(array('&nbsp;', ',', ' '), array('', '.', ''), $amount);

				$amount = $amount*1;
				$this->balance = str_replace(',', '.', $amount);
				//var_dump($this->balance);
				return true;
			}
			else
			{
				//если счета нет то баланс якобы = 0, когда деньги поступят то счет появится
				$this->balance = 0;
				return true;
			}
		}
		else
		{
			$this->error = 'неизвестная валюта: '.$currency;
			return false;
		}
	}


	/**
	 * курсы всех возможных направлений обмена на qiwi.com
	 * @return array|false ['KZT_RUB'=>0.176, ...] (0.176 - на сколько надо умножить KZT чтобы получить RUB)
	 *
	 * //todo: сделать остальные комбинации
	 */
	public function getRates()
	{
		$currencies = array(
			self::CURRENCY_KZT,
		);

		$result = array();

		foreach($currencies as $currency)
		{
			if($currency == self::CURRENCY_RUB)
				$result[$currency] = 1;
			elseif($currency == self::CURRENCY_KZT)
			{
				$url = 'https://qiwi.com/payment/form/calculate.action';
				$postData = 'provider=20175&integer=1&fraction=0&source=qiwi_KZT&currency=RUB';

				$referer = 'https://qiwi.com/settings/account/transfer.action?paymentModeType=QIWI&paymentModeValue=qiwi_RUB';

				$this->sender->additionalHeaders = array(
					'X-Requested-With: XMLHttpRequest',
					'Accept: application/json, text/javascript, */*; q=0.01',
					'Accept-Encoding: gzip, deflate, br',
				);

				$content = $this->request($url, $postData, false, $referer);

				if($json = json_decode($content, true))
				{
					$result[$currency.'_'.self::CURRENCY_RUB] = $json['data']['rate']['reverseValue'];
				}
				else
				{
					$this->error = 'ошибка в json: '.$json.', '.__METHOD__;
					return false;
				}
			}
		}

		return $result;
	}




	/**
	 * сконвертировать сумму из одной валюты в другую
	 * пока работает только KZT=>RUB
	 *
	 * @param string $currencyFrom из какой валюты
	 * @param string $currencyTo в какую валюту
	 * @param float $amountFrom сумма в валюте $currencyFrom
	 * @return float|false сумма в $currencyTo
	 */
	public function convert($currencyFrom, $currencyTo, $amountFrom)
	{
		$rates = $this->getRates();

		$currencyFromQiwi = '';	//название валюты в запросе

		if($currencyFrom === self::CURRENCY_KZT)
			$currencyFromQiwi = 'qiwi_KZT';
		else
		{
			$this->error = 'неизвестная валюта $currencyFrom: '.$currencyFrom;
			return false;
		}

		$currencyToQiwi = '';

		if($currencyTo === self::CURRENCY_RUB)
			$currencyToQiwi = 'qiwi_RUB';
		else
		{
			$this->error = 'неизвестная валюта $currencyTo: '.$currencyTo;
			return false;
		}


		if(!$rates)
			return false;

		$rate = $rates[$currencyFrom.'_'.$currencyTo];

		if($rate)
			$amountTo = floorAmount($rate * $amountFrom, 2);	//сумма в запросе
		else
		{
			$this->error = 'нет нужной пары для конвертации '.$currencyFrom.'_'.$currencyTo;
			return false;
		}

		$amountTo = $this->getAmountForTransaction($amountTo);

		if($amountTo < self::MIN_AMOUNT)
		{
			$this->error = 'сума меньше минимальной $amountTo = '.$amountTo;
			return false;
		}

		$amountInteger = floor($amountTo);
		$amountFraction = floor(($amountTo - floor($amountTo))) * 100;

		$result = ($amountInteger.'.'.$amountFraction)*1;

		if(strlen($amountFraction) === 1)
			$amountFraction = '0'.$amountFraction;


		$amountInteger = str_replace(',', '.', $amountInteger);

		//важно дернуть страницу с выбором
		$this->request('https://qiwi.com/settings/account/transfer.action?paymentModeType=QIWI&paymentModeValue=qiwi_KZT');

		$this->sender->additionalHeaders = array(
			'X-Requested-With: XMLHttpRequest',
			'Accept: text/html, */*; q=0.01',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
		);

		//1
		$url = 'https://qiwi.com/user/person/account/state.action';
		$postData = 'extra%5B\'account\'%5D='.$this->login.'&source='.$currencyFromQiwi.'&currency=RUB&amountInteger='.$amountInteger.'&amountFraction='.$amountFraction.'&state=CONFIRM&protected=true&destination='.$currencyToQiwi;
		$referer = 'https://qiwi.com/settings/account/transfer.action?paymentModeType=QIWI&paymentModeValue='.$currencyFromQiwi;

		//<input type="hidden" name="token" value="-8766387741497573058"/>
		$content = $this->request($url, $postData, false, $referer);

		//echo $postData."\n\n";
		//echo $content."\n\n";

		//$content1 = $this->request('https://qiwi.com/provider/content/categorypath.action', 'state=CONFIRM&protected=true', false, 'https://qiwi.com/person/account/state.action?state=CONFIRM&protected=true');


		if(preg_match('!<input type="hidden" name="token" value="(.+?)"/>!', $content, $res))
		{
			$token = $res[1];

			//2
			//token=4976240456432199911&amountFraction=00&extra%5B'account'%5D=79636572403&protected=true&amountInteger=2&destination=qiwi_RUB&currency=RUB&source=qiwi_KZT&state=CONFIRM
			$postData = 'token='.$token.'&amountFraction='.$amountFraction.'&extra%5B\'account\'%5D='.$this->login.'&protected=true&amountInteger='.$amountInteger.'&destination='.$currencyToQiwi.'&currency=RUB&source='.$currencyFromQiwi.'&state=CONFIRM';
			$referer = 'https://qiwi.com/person/account/state.action?state=CONFIRM&protected=true';

			$this->request($url, $postData, false, $referer);

			//echo $postData."\n\n";
			//echo $content."\n\n";


			//3
			$url = 'https://qiwi.com/user/person/account/state.action';
			$postData = 'state=PAY';
			$referer = 'https://qiwi.com/person/account/state.action?state=CONFIRM&protected=true';

			$content = $this->request($url, $postData, false, $referer);

			//echo $postData."\n\n";
			//echo $content."\n\n";

			if(preg_match('!<input type="hidden" name="token" value="(.+?)"/>!', $content, $res))
			{
				//4
				$url = 'https://qiwi.com/user/person/account/state.action';
				$token = $res[1];
				$postData = 'token='.$token.'&state=PAY';
				$referer = 'https://qiwi.com/person/account/state.action?state=PAY';

				$content = $this->request($url, $postData, false, $referer);

				//echo $postData."\n\n";
				//echo $content."\n\n";

				if(preg_match('!data-widget="payment-success"!i', $content))
				{
					return $result;
				}
				else
				{
					$this->error = 'ошибка конвертации1: '.$content;

					if(preg_match('!Недостаточно средств!isu', $content))
						$this->errorCode = self::ERROR_NO_MONEY;

					return false;
				}
			}
			else
			{
				$this->error = 'ошибка получения токена2 '.__METHOD__;
				return false;
			}
		}
		else
		{
			$this->error = 'ошибка получения токена1 '.__METHOD__;
			return false;
		}
	}

	/**
	 * возвращает валюту из текста , пр 2.01 руб. или 123 тенге
	 * @param string $amountStr
	 * @return string|false 'RUB|KZT'
	 */
	protected function getCurrency($amountStr)
	{
		if(preg_match('!руб\.!u', $amountStr))
			return self::CURRENCY_RUB;
		elseif(preg_match('!тенге!u', $amountStr))
			return self::CURRENCY_KZT;
		else
		{
			toLogError('неизвестная валюта: '.$amountStr.' на кошельке '.$this->login);
			return false;
		}
	}

}