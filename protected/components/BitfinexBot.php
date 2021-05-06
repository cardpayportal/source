<?php

/**
 * @property Sender $_sender
 */
class BitfinexBot
{
	private $_login = '';
	private $_pass = '';
	private $_sender = null;	//Sender
	private $_requestInfo = array();	//Sender
	private $_lastContent = '';	//Sender

	public $errorMsg = '';
	public $errorCode = '';

	public function __construct($login, $pass)
	{
		$this->_login = $login;
		$this->_pass = $pass;

		$this->_sender = new Sender();
		$this->_sender->browser = 'Mozilla/5.0 (Macintosh; Intel Mac OS X; rv:53.0.1) Gecko/20500102 Firefox/53.0.1';
		$this->_sender->followLocation = false;
		$this->_sender->pause = 1;
		$this->_sender->timeout = 30;

		return $this->_logIn();
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

		$content = $this->_request('https://www.bitfinex.com/');

		if(!preg_match('!<input name="authenticity_token" type="hidden" value="(.+?)"!', $content, $res))
		{
			die($content);
			$this->errorMsg = 'auth step1';
			return false;
		}

		$token = $res[1];

		$url = 'https://www.bitfinex.com/sessions';
		$post = 'utf8=%E2%9C%93&authenticity_token='.$token.'&login='.$this->_login.'&password='.$this->_pass.'&action=';
		$referer = 'https://www.bitfinex.com/';

		$this->_sender->additionalHeaders = array(
			'X-CSRF-Token: '.$token,
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
			'Accept: */*;q=0.5, text/javascript, application/javascript, application/ecmascript, application/x-ecmascript',
		);

		$content = $this->_request($url, $post, $referer);

		if(preg_match('!Welcome back!', $content))
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
		$url = 'https://www.bitfinex.com/trading';

		$content = $this->_request($url);

		if(preg_match('!<a href="/logout"!', $content))
		{
			return true;
		}
		elseif($this->_requestInfo['httpCode'][0] == 302)
		{
			return false;
		}
		elseif($this->_requestInfo['httpCode'][0] == 200)
		{
			$this->errorMsg = 'authError contentLength='.strlen($content);
			return false;
		}
		else
		{
			$this->errorMsg = 'authError code='.$this->_requestInfo['httpCode'][0];
			return null;
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
}