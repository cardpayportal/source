<?php
class YandexBot extends Bot
{
	public $ncrnd 	= '';
	protected $_inboxMailAddr = '';

	function __construct($login, $pass, $debug)
	{
		parent::__construct($login, $pass, $debug);
		$this->_inboxMailAddr = 'https://mail.yandex.ru/lite/inbox?ncrnd='.$this->ncrnd;
	}

	protected function _auth()
	{
		$url = 'https://passport.yandex.ru/passport?mode=auth&retpath=https%3A%2F%2Fmail.yandex.ru%2Flite%2Finbox%3Fncrnd%3D2027';
		$postData = 'login='.$this->_login.'&passwd='.$this->_pass.'&retpath=https%3A%2F%2Fmail.yandex.ru%2Flite%2Finbox%3Fncrnd%3D2027';
		$headers = array(
			'Referer: https://passport.yandex.ru/passport?mode=auth&retpath=https%3A%2F%2Fmail.yandex.ru%2Flite%2Finbox%3Fncrnd%3D2027',
			'Upgrade-Insecure-Requests: 1',
			'Content-Type: application/x-www-form-urlencoded',
		);

		//step1
		$content = $this->_request($url, $postData, $headers);


		if(preg_match('!<a href="https://passport\.yandex\.ru/auth/finish/\?track_id=(\w+)">!iu', $content, $matches))
		{
			$trackId = $matches[1];
			$url = 'https://passport.yandex.ru/auth/finish/?track_id='.$trackId;
			//step2
			$content = $this->_request($url, null, $headers);

			if(preg_match('!amp;ncrnd=(\w+)"!iu', $content, $matches))
			{
				$this->ncrnd = $matches[1];
				$url = '<a href="https://pass.yandex.ru/login?retpath=https%3A%2F%2Fmail.yandex.ru%2Flite%2Finbox%3Fncrnd%3D2027&amp;ncrnd='.$this->ncrnd.'">';
				$headers = array();
				//step3
				$content = $this->_request($url, null, $headers);

				if($this->requestInfo['httpCode'] == 0)
					return true;
				else
				{
					$this->error = 'auth error';
					return false;
				}
			}
			else
				die('step2 failed');
		}
		else
			die('step1 failed');
	}

	protected function _isAuth()
	{
		//ищем "class="b-header__link b-header__link_exit">Выход</a>"
		//на странице https://mail.yandex.ru/lite/inbox?ncrnd=8404 чтобы убедиться что успешно вошли
		$content = $this->_request($this->_inboxMailAddr);

		if(preg_match('!class="b-header__link b-header__link_exit">Выход!', $content))
		{
			//die($content);
			return true;
		}
		else
			return false;
	}

	public function logOut()
	{
		$content = $this->_request($this->_inboxMailAddr);

		if(preg_match('!<a href="https://passport\.yandex\.ru/passport\?mode=logout&amp;yu=(\d+?)&amp;!ui', $content, $matches))
		{
			$yu = $matches[1];
			$url = 'https://passport.yandex.ru/passport?mode=logout&yu='.$yu.'&retpath=http%3A%2F%2Fwww.yandex.ru%2F';
			$headers = array();
			$content = $this->_request($url, null, $headers);
			if($this->requestInfo['httpCode'] == 302)
				return true;
			else
				return false;
		}
		else
			prrd('not logged in');
	}

	public function getMailContent()
	{
		$result = array();

		$content = $this->_request($this->_inboxMailAddr);

		//prrd($this->requestInfo['requestSize']);
		//<span class="b-messages__from__text" aria-hidden="true" role="presentation"><span class="b-messages__from__text">Facebook</span></span></a>
		//$content = '<span class="b-messages__from__text" aria-hidden="true" role="presentation"><span class="b-messages__from__text">Facebook</span></span></a>';
		if(preg_match('!<span\s+class="b-messages__from__text"\s+aria-hidden="true"\s+role="presentation"><span\s+class="b-messages__from__text">([А-ЯЁа-яёA-Za-z0-9-_\.]+)</span></span></a>!ui', $content, $matches))
		{
			//foreach($matches[1] as $key=>$value)
			//{
			//$result = trim($value);
			//}
			print_r($matches);
		}
		else
			$this->error = 'parsing fail';//.$content;
		return $result;
	}
}
