<?php

class Sender
{
    public $error;	//последняя ошибка
    public $dirHeader;
    public $dirCookie;
    public $fileHeader;
    public $fileBrowser;
    public $browserList;
    public $pause;	//целое число - пауза между группами запросов

    public $useCookie = true;
    public $useHeader = true;
    public $followLocation = true;
    public $maxRedir = 5;	//максимальное количество редиректов при folllowLocation
    public $referer;
    public $timeout = 60;	//макс. время ожидания окончания загрузки всех потоков
    public $info;	//информация о сделанных запросах
    public $inCharset;	//кодировка сайта
    public $outCharset;	//кодировка нужная
    public $additionalHeaders;
    public $cookieFile;	//возможность задать файл для хранения кук
    public $proxyType = 'http'; //тип прокси: http или socks5
	public $browser = '';
	public $sslSert= ''; //адрес файла с сертификатом

    public function __construct()
    {
        $this->dirHeader = dirname(__FILE__).'/'.__CLASS__.'/header/';
        $this->dirCookie = dirname(__FILE__).'/'.__CLASS__.'/cookie/';
        $this->fileHeader = dirname(__FILE__).'/'.__CLASS__.'/header.txt';
        $this->fileBrowser = dirname(__FILE__).'/'.__CLASS__.'/browser.txt';

    }

    /**
     * если передаются массивы, то ключи у них должны быть общими
	 *
	 * @param $extraCurlOpt - когда вместо GET, POST нужны другие методы
     */
    public function send($url, $postData=false, $proxy=false, $referers=false, $extraCurlOpt=false)
    {
        $this->info = array();

        if($this->pause)
            sleep(rand(1,$this->pause));

        $urlArr = $this->makeArray($url);
        $postArr = $this->makeArray($postData);
		
        $proxyArr = $this->makeArray($proxy);
        $refererArr = $this->makeArray($referers);

        if($proxy and count($proxyArr)<count($urlArr))
        {
            $this->error = 'количество прокси меньше числа ссылок';

            return false;
        }

        $threads = array();

        if(!$proxy)
            $myIp = '127.0.0.1';

        foreach($urlArr as $key=>$currentUrl)
        {
            $options = array(
                CURLOPT_URL=>$currentUrl,
                CURLOPT_RETURNTRANSFER=>1,
                CURLOPT_VERBOSE=>0,
                CURLOPT_SSL_VERIFYPEER=>false,
                CURLOPT_SSL_VERIFYHOST=>false,
				//CURLOPT_COOKIESESSION => true,
                CURLOPT_HEADER => true,
                CURLOPT_ENCODING=>'gzip, deflate',
            );

            //$currentPost = $postArr[$key];

            if($proxy)
            {
                $currentProxy = $this->parseProxyStr($proxyArr[$key]);

                if($this->proxyType=='socks5')
                    $options[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
                else
                    $options[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;

                $options[CURLOPT_PROXY] = $currentProxy['ip'].':'.$currentProxy['port'];

                if($currentProxy['login'] and $currentProxy['pass'])
                    $options[CURLOPT_PROXYUSERPWD] = $currentProxy['login'].':'.$currentProxy['pass'];
            }
            else
                $currentProxy = array('ip'=>$myIp, 'port'=>'', 'login'=>'', 'pass'=>'');


            if($postArr[$key])
            {
                $options[CURLOPT_POSTFIELDS] = $postArr[$key];
            }

            if(is_array($postData) or $postArr[$key])
				$options[CURLOPT_POST] = 1;
			elseif($extraCurlOpt == 'put')
				$options[CURLOPT_PUT] = 1;

			if($this->browser)
				$options[CURLOPT_USERAGENT] = $this->browser;

			if($this->additionalHeaders)
				$options[CURLOPT_HTTPHEADER] = $this->additionalHeaders;

            if($this->useCookie)
            {
                $currentCookie = ($this->cookieFile) ? $this->cookieFile : $this->getCookie($currentProxy);

                $options[CURLOPT_COOKIEFILE] = $currentCookie;
                $options[CURLOPT_COOKIEJAR] = $currentCookie;
            }

            if($this->followLocation)
            {
                if(!(ini_get('open_basedir') == '' and (ini_get('safe_mode') == 'Off' or ini_get('safe_mode') == '')))
                {
                    $this->error('проблемы с конфигурацией для FOLLOW_LOCATION: '.$url);
                    return false;
                }

                $options[CURLOPT_FOLLOWLOCATION] = true;
                $options[CURLOPT_MAXREDIRS] = $this->maxRedir;
            }
            else
            {
                $options[CURLOPT_FOLLOWLOCATION] = false;
            }

            if($refererArr[$key])
            {
                $options[CURLOPT_REFERER] = $refererArr[$key];
            }
//            elseif(!$this->referer)
//                $options[CURLOPT_REFERER] = $currentUrl;


			//для установления соединения
//            $options[CURLOPT_CONNECTTIMEOUT] = ($this->timeout < 10) ? 5 : 10;
			//test
            $options[CURLOPT_CONNECTTIMEOUT] = $this->timeout;

			//целиком на срабатывание функции
            $options[CURLOPT_TIMEOUT] = $this->timeout;


			if($this->sslSert)
			{
				$options[CURLOPT_SSL_VERIFYPEER] = true; 	//true
				$options[CURLOPT_SSL_VERIFYHOST] = 2;		//2
				$options[CURLOPT_CAINFO] = $this->sslSert;
			}

            $threads[$key] = curl_init();
            curl_setopt_array($threads[$key], $options);

        }

		$mh = curl_multi_init();

		foreach($threads as $thread)
			curl_multi_add_handle($mh, $thread);

        //чтобы процессора не жрало
        //set_time_limit(0);

		$startTime = time();

        do
        {
            $n=curl_multi_exec($mh, $active);
            usleep(10000);
        }
        while ($active and time() - $startTime < $this->timeout);

        $result = array();

        foreach($urlArr as $key=>$currentUrl)
        {
            $content = curl_multi_getcontent($threads[$key]);

            $header = substr($content, 0, curl_getinfo($threads[$key], CURLINFO_HEADER_SIZE));
            $body = substr($content, curl_getinfo($threads[$key], CURLINFO_HEADER_SIZE));
            $result[$key] = $body;

            $this->info['httpCode'][$key] = curl_getinfo($threads[$key], CURLINFO_HTTP_CODE);
            $this->info['referer'][$key] = curl_getinfo($threads[$key],CURLINFO_EFFECTIVE_URL);
            $this->info['proxy'][$key] = $proxyArr[$key];
            $this->info['header'][$key] = $header;
			$this->info['time'][$key] = str_replace(',', '.', curl_getinfo($threads[$key], CURLINFO_TOTAL_TIME));
			$this->info['error'][$key] = curl_error($threads[$key]);

            curl_multi_remove_handle($mh, $threads[$key]);
            curl_close($threads[$key]);
        }

        curl_multi_close($mh);
        unset($options);

        if($this->inCharset and $this->outCharset)
        {
            foreach($result as $key=>$val)
                $result[$key] = iconv($this->inCharset, $this->outCharset, $val);
        }

        if(!is_array($url))
            $result = current($result);

        return $result;
    }

    private function getHeader($proxy)
    {
        $path = $this->dirHeader.$proxy['ip'].'.txt';

        if(file_exists($path))
        {
            return explode("\r\n", file_get_contents($path));
        }
        elseif($this->setHeader($proxy))
            return $this->getHeader($proxy);
    }

    private function setHeader($proxy)
    {
        $headers = trim(file_get_contents($this->fileHeader));
        $browserList = $this->browserList();
        $headers.= "\r\nUser-Agent: ".$browserList[array_rand($browserList)];

        $path = $this->dirHeader.$proxy['ip'].'.txt';

        if(!file_put_contents($path, trim($headers)))
        {
            $this->error('ошибка записи '.$this->dirHeader.$proxy['ip'].'.txt');
            return false;
        }

        return true;
    }

    private function getCookie($proxy)
    {
        $path = $this->dirCookie.$proxy['ip'].'.txt';

        if(file_exists($path))
        {
            return $path;
        }
        elseif($this->setCookie($proxy))
            return $this->getCookie($proxy);
    }

    private function setCookie($proxy)
    {
        $path = $this->dirCookie.$proxy['ip'].'.txt';

        if(file_put_contents($path, '')===false)
        {
            $this->error('ошибка записи '.$path);
            return false;
        }

        return true;
    }

    private function makeArray($value)
    {
        if(!is_array($value))
            return array($value);
        else
            return $value;
    }

    private function error($text)
    {
        $this->error = $text;

        toLog('Sender::error(): '.$this->error, 1);
    }

    public function browserList()
    {
        if(!$this->browserList)
        {
            $path = $this->fileBrowser;
            $this->browserList = explode("\r\n",trim(file_get_contents($path)));
        }

        return $this->browserList;
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

}

?>