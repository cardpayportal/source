<?php

/**
 * todo: надо гдето хранить подтвержденные адреса
 * @property Sender $_sender
 */
class KrakenBot
{
	const REG_EXP_ADDRESS = '!^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$!';

	private $_login = '';
	private $_pass = '';
	private $_sender = null;	//Sender
	private $_requestInfo = array();	//Sender
	private $_lastContent = '';	//Sender
	private $_dataFile = '';

	public $errorMsg = '';
	public $errorCode = '';

	//если не заданы то подтверждение по мылу будет выдавать false
	public $emailLogin = '';
	public $emailPass = '';

	public $data = [];
	/*
	 [
		'withdrawAddressList' => [
			'btcAddresssfdfsdfadsf11f' => ['name'=>'test1', 'confirmed'=>false],
			'btcAddresssfdfsdfadsffff' => ['name'=>'test2', 'confirmed'=>true],
			...
		]
	 ]
	 */


	public function __construct($login, $pass)
	{
		$this->_login = $login;
		$this->_pass = $pass;

		$this->_sender = new Sender();
		$this->_sender->browser = 'Mozilla/5.0 (Windows NT 6.2; WOW64; rv:55.0) Gecko/20100101 Firefox/55.0';
		$this->_sender->followLocation = false;
		$this->_sender->pause = 1;
		$this->_sender->timeout = 30;

		$this->_dataFile = __DIR__.'/'.__CLASS__.'/data';

		if(!is_writable($this->_dataFile))
		{
			$this->errorMsg = 'data file not writable';
			return false;
		}

		if(!$this->_readData())
		{
			$this->errorMsg = 'error read data file';
			return false;
		}

		return $this->_logIn();
	}

	public function __destruct()
	{
		$this->_writeData();
	}

	private function _request($url, $postData = false, $referer = '')
	{
		$this->_lastContent = $this->_sender->send($url, $postData, false, $referer);

		$this->_requestInfo = $this->_sender->info;

		return $this->_lastContent;
	}

	private function _logIn()
	{
		$authRes = $this->isAuth();

		if($authRes)
			return true;
		elseif($authRes === null)
		{
			print_r($this->_requestInfo);
			die('ff'.$this->_lastContent);
		}

		$this->_request('https://www.kraken.com/en-us/login');

		$url = 'https://www.kraken.com/login';
		$post = 'csr=&username='.$this->_login.'&password='.$this->_pass;
		$referer = 'https://www.kraken.com/en-us/login';

		$this->_sender->additionalHeaders = array(
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Content-Type: application/x-www-form-urlencoded',
			'Upgrade-Insecure-Requests: 1',
		);

		$content = $this->_request($url, $post, $referer);

		if($this->_requestInfo['httpCode'][0] == 302 and preg_match('!Location: /u/trade!', $this->_requestInfo['header'][0]))
			return true;
		else
		{
			$this->errorMsg = 'auth step2, content='.$content;
			return false;
		}
	}

	private function _logOut()
	{

	}

	/**
	 * @return true|false|null (успех|неудача|ошибка запроса-повторить)
	 */
	public function isAuth()
	{
		$url = 'https://www.kraken.com/u/trade';

		$content = $this->_request($url);

		if(preg_match('!<a href="/logout"><i class="icon-arrow-right"></i>!', $content))
		{
			return true;
		}
		elseif(preg_match('!<p>You must Login to perform this action\.</p>!', $content))
		{
			return false;
		}
		else
		{
			$this->errorMsg = 'authError contentLength='.strlen($content);
			return false;
		}
	}

	public function confirmWithdraw($url)
	{
		$this->_sender->additionalHeaders = array(
			'Content-Type: application/x-www-form-urlencoded',
			'Accept: text/html'
		);

		$content = $this->_request($url);

		if(preg_match('!<form action\="(/withdraw/approve/\d+\?.+?)".+?<input name\="authenticity_token" type="hidden" value="(.+?)" /></div>!', $content, $res))
		{
			$url = 'https://www.bitfinex.com'.$res[1];
			$token = $res[2];
			$postData = 'authenticity_token='.$token;
			$referer = 'https://www.bitfinex.com/';

			$content = $this->_request($url, $postData, $referer);

			if(preg_match('!<a href="https://www\.bitfinex\.com/withdraw/\d+">redirected</a>!', $content))
				return true;
			else
			{
				print_r($this->_requestInfo);

				$this->errorMsg = 'confirmWithdraw step2: '.$content;
				return false;
			}
		}
		else
		{
			$this->errorMsg = 'confirmWithdraw step1: '.$content;
			return false;
		}
	}

	/**
	 * список подтвержденных адресов для вывода(btc)
	 * @return array|false
	 */
	public function getWithdrawAddressList()
	{
		$result = [];

		$url = 'https://www.kraken.com/ajax';
		$postData = 'a=funds&p=withdraw&x=address-list';
		$this->_sender->additionalHeaders = [
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
			'Referer: https://www.kraken.com/u/funding/withdraw?asset=XXBT',
			'Accept: text/plain, */*; q=0.01',
		];

		$content = $this->_request($url, $postData);

		if($json = json_decode($content, true))
		{
			foreach($json['data']['addresslist']['XXBT']['Bitcoin'] as $arr)
				$result[$arr['id'].''] = $arr['address'];
		}
		else
		{
			$this->errorMsg = 'error parsing address list: '.$content;
			return false;
		}

		return $result;
	}

	/**
	 * @param string $address
	 * @param string $addressName
	 * @return bool
	 */
	public function setWithdrawAddress($address, $addressName)
	{
		if(!preg_match(self::REG_EXP_ADDRESS, $address))
		{
			$this->errorMsg = 'wrong btc $address';
			return false;
		}

		if(!$addressName)
		{
			$this->errorMsg = 'wrong $addressName';
			return false;
		}

		if(isset($this->data['withdrawAddressList'][$address]))
		{
			if($this->data['withdrawAddressList'][$address]['confirmed'])
				return true;
			else
				return $this->_confirmAddress($address, $addressName);
		}

		$url = 'https://www.kraken.com/ajax';
		$postData = 'description='.$addressName.'&info%5Baddress%5D='.$address.'&asset=XXBT&method=Bitcoin&methodtype=3&a=funds&p=withdraw&x=address-add&csrftoken=';
		$this->_sender->additionalHeaders = [
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
			'Referer: https://www.kraken.com/u/funding/withdraw?asset=XXBT',
			'Accept: text/plain, */*; q=0.01',
		];

		$content = $this->_request($url, $postData);

		if(preg_match('!"csrftoken":"(.+?)"!', $content, $res))
			$token = $res[1];
		else
		{
			$this->errorMsg = 'error getting token: '.$content;
			return false;
		}

		$postData = $postData.$token;

		$content = $this->_request($url, $postData);

		if(preg_match('!"error":\["The withdrawal address needs to be confirmed!', $content))
		{
			$this->data['withdrawAddressList'][$address] = ['name'=>$address, 'confirmed'=>false];

			sleep(30);
			return $this->_confirmAddress($address, $addressName);
		}
		else
		{
			$this->errorMsg = 'error  set address: '.$content;
			return false;
		}
	}

	/**
	 * подтверждает адрес по мылу
	 * @param string $address - адрес
	 * @param string $addressName - название адреса
	 * @return bool
	 */
	private function _confirmAddress($address, $addressName)
	{
		if(!$this->emailLogin or !$this->emailPass)
		{
			$this->errorMsg = 'emailLogin or emailPass is not set';
			return false;
		}

		if(!$addressName)
		{
			$this->errorMsg = '$addressName empty';
			return false;
		}


		//todo: переделать универсально под любые почтовики
		preg_match('!(.+?)\@!', $this->emailLogin, $res);
		$emailLogin = $res[1];
		$mail = new MailBox('{imap.mail.yahoo.com:993/imap/ssl/novalidate-cert}INBOX', $emailLogin, $this->emailPass);

		if($mail->error)
		{
			$this->errorMsg = 'error connect email: '.$mail->error;
			return false;
		}

		if($emails = $mail->getMessages('kraken.com', false, 10))
		{
			foreach($emails as $email)
			{
				//ищем код для нужного $addressName
				if(preg_match('!Address description: '.$addressName.'\s!', $email['text']))
				{
					if(preg_match('!https://www\.kraken\.com/withdrawal-approve\?code\=(.+)!', $email['text'], $res))
					{
						$code = trim($res[1]);

						$url = 'https://www.kraken.com/withdrawal-approve';
						$postData = 'code='.$code;
						$this->_sender->additionalHeaders = [
							'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
							'X-Requested-With: XMLHttpRequest',
							'Referer: https://www.kraken.com/withdrawal-approve?code='.$code,
							'Accept: text/plain, */*; q=0.01',
						];

						$content = $this->_request($url, $postData);

						if(
							$this->_requestInfo['httpCode'][0] == 302
							and preg_match('!Location: u/funding/withdraw!', $this->_requestInfo['header'][0])
						)
						{
							$this->data['withdrawAddressList'][$address]['confirmed'] = true;
							return true;
						}
						else
						{
							$this->errorMsg = 'error last step confirmation: '.$content;
							return false;
						}

					}
					else
					{
						$this->errorMsg = 'code not found in email';
						return false;
					}
				}
			}

			$this->errorMsg = 'email not found for: '.$addressName;
			return false;
		}
		else
		{
			$this->errorMsg = 'error get email messages';
			return false;
		}

	}

	/**
	 * читает сохраненные данные
	 * @return bool
	 */
	private function _readData()
	{
		$content = file_get_contents($this->_dataFile);

		if(!$content)
		{
			$this->data = [];
			return true;
		}

		$this->data = json_decode($content, true);

		return is_array($this->data);
	}

	/**
	 * сохраняет сохраненные данные
	 * @return bool
	 */
	private function _writeData()
	{
		if(!file_put_contents($this->_dataFile, json_encode($this->data)))
		{
			$this->_log('error write file: '.$this->_dataFile);
			return false;
		}
		else
			return true;
	}

	private function _log($msg)
	{
		return toLog($msg);
	}

}