<?php
class PayeerApi
{
	private $url = 'https://payeer.com/ajax/api/api.php';
	private $agent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.84 Safari/537.36';

	private $auth = array();

	private $output;
	private $errors;
	private $language = 'ru';
	private $proxy;
	private $proxyType = 'http';

	public function __construct($account, $apiId, $apiPass, $proxy)
	{
		$arr = array(
			'account' => $account,
			'apiId' => $apiId,
			'apiPass' => $apiPass,
		);

		$this->proxy = $proxy;

		$response = $this->getResponse($arr);

		if ($response['auth_error'] == '0')
		{
			$this->auth = $arr;
		}
	}

	public function isAuth()
	{
		if (!empty($this->auth)) return true;
		return false;
	}

	private function getResponse($arPost)
	{
		if (!function_exists('curl_init'))
		{
			die('curl library not installed');
			return false;
		}

		if ($this->isAuth())
		{
			$arPost = array_merge($arPost, $this->auth);
		}

		$data = array();
		foreach ($arPost as $k => $v)
		{
			$data[] = urlencode($k) . '=' . urlencode($v);
		}
		$data[] = 'language=' . $this->language;
		$data = implode('&', $data);

		$handler  = curl_init();
		curl_setopt($handler, CURLOPT_URL, $this->url);
		curl_setopt($handler, CURLOPT_HEADER, 0);
		curl_setopt($handler, CURLOPT_POST, true);
		curl_setopt($handler, CURLOPT_POSTFIELDS, $data);
		curl_setopt($handler, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($handler, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($handler, CURLOPT_USERAGENT, $this->agent);
		curl_setopt($handler, CURLOPT_RETURNTRANSFER, 1);

		if($this->proxy)
		{
			$currentProxy = $this->parseProxyStr($this->proxy);

			if($this->proxyType=='socks5')
				curl_setopt($handler, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
			else
				curl_setopt($handler, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);

			curl_setopt($handler, CURLOPT_PROXY, $currentProxy['ip'].':'.$currentProxy['port']);

			if($currentProxy['login'] and $currentProxy['pass'])
				curl_setopt($handler, CURLOPT_PROXYUSERPWD, $currentProxy['login'].':'.$currentProxy['pass']);
		}
		else
		{
			$myIp = '127.0.0.1';
			$currentProxy = array('ip'=>$myIp, 'port'=>'', 'login'=>'', 'pass'=>'');
		}

		$content = curl_exec($handler);
		$arRequest = curl_getinfo($handler);
		curl_close($handler);

		$content = json_decode($content, true);

		if (isset($content['errors']) && !empty($content['errors']))
		{
			$this->errors = $content['errors'];
		}

		return $content;
	}

	private function parseProxyStr($str)
	{
		if(!preg_match('!(([^:]+?):([^@]+?)@|)(.+?):(\d{2,7})!', $str, $res))
		{
			$this->error('неверный формат прокси: '.$str);
			return false;
		}

		return array(
			'login'=>$res[2],
			'pass'=>$res[3],
			'ip'=>$res[4],
			'port'=>$res[5],
		);
	}

	public function getPaySystems()
	{
		$arPost = array(
			'action' => 'getPaySystems',
		);

		$response = $this->getResponse($arPost);

		return $response;
	}

	public function initOutput($arr)
	{
		$arPost = $arr;
		$arPost['action'] = 'initOutput';

		$response = $this->getResponse($arPost);

		if (empty($response['errors']))
		{
			$this->output = $arr;
			return true;
		}

		return false;
	}

	public function output()
	{
		$arPost = $this->output;
		$arPost['action'] = 'output';

		$response = $this->getResponse($arPost);

		if (empty($response['errors']))
		{
			return $response['historyId'];
		}

		return false;
	}

	public function getHistoryInfo($historyId)
	{
		$arPost = array(
			'action' => 'historyInfo',
			'historyId' => $historyId
		);

		$response = $this->getResponse($arPost);

		return $response;
	}



	public function getBalance()
	{
		$arPost = array(
			'action' => 'balance',
		);

		$response = $this->getResponse($arPost);

		return $response;
	}

	public function getErrors()
	{
		return $this->errors;
	}

	public function transfer($arPost)
	{
		$arPost['action'] = 'transfer';

		$response = $this->getResponse($arPost);

		return $response;
	}

	public function SetLang($language)
	{
		$this->language = $language;
		return $this;
	}

	public function getShopOrderInfo($arPost)
	{
		$arPost['action'] = 'shopOrderInfo';

		$response = $this->getResponse($arPost);

		return $response;
	}

	public function checkUser($arPost)
	{
		$arPost['action'] = 'checkUser';

		$response = $this->getResponse($arPost);

		if (empty($response['errors']))
		{
			return true;
		}

		return false;
	}

	public function getExchangeRate($arPost)
	{
		$arPost['action'] = 'getExchangeRate';

		$response = $this->getResponse($arPost);

		return $response;
	}

	public function merchant($arPost)
	{
		$arPost['action'] = 'merchant';

		$arPost['shop'] = json_encode($arPost['shop']);
		$arPost['form'] = json_encode($arPost['form']);
		$arPost['ps'] = json_encode($arPost['ps']);

		if (empty($arPost['ip'])) $arPost['ip'] = $_SERVER['REMOTE_ADDR'];

		$response = $this->getResponse($arPost);

		if (empty($response['errors']))
		{
			return $response;
		}

		return false;
	}

	public function getHistory($count ,$from = '', $to = '')
	{
		$arPost = array(
			'action' => 'history',
			'count' => $count
		);

		$response = $this->getResponse($arPost);

		return $response;
	}


}
?>
