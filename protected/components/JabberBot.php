<?php
/*
 * singleton - чтобы избежать повторной авторизации в цикле
 */
class JabberBot
{
	const THREAD_NAME = 'jabberBot';
	const SESSION_IDENTIFIER = 'notice_bot';//идентификатор сессии при авторизации

	const LOGIN_REG_EXP = '!^(.+?)@(.+)$!';

	//const ERROR_CONNECT = 'error_connect';
	//const ERROR_LOGIN = 'error_login';
	//const ERROR_AUTH = 'error_auth';

	private static $_instance = null;

	private $_login = ''; //то что до @
	private $_loginStr = ''; //весь логин с адресом сервера
	private $_pass = '';
	private $_host = '';
	private $_port = '';

	public $error = '';
	public $errorCode = '';

	public $tlsEnabled = false; //включен ли защищенный режим

	private $_lastRequestId = 0;
	private $_sessionIdentifier = '';

	public $contacts = array();	//список контактов	array( 0 => array('name'=>'', 'id'=>'sdf@xmpp.org', 'group'=>''), ...)
	public $offlineMessages = array();

	public $status = '';

	private $_stream;

	public $pause = 400000;

	private function __construct($login, $pass, $status = 'online', $port = '5222')
	{
		if(!preg_match(self::LOGIN_REG_EXP, $login, $res))
		{
			$this->error = 'неверный логин';
			return false;
		}

		if(!$pass)
		{
			$this->error = 'неверный пароль';
			return false;
		}

		$this->_loginStr = $login;

		$this->_login = $res[1];	//то что до собачки
		$this->_pass = $pass;
		$this->_host = $res[2];
		$this->_port = $port;

		$this->status = $status;

		return $this->init();
	}

	public static function getInstance($login, $pass, $status = 'online', $port = '5222')
	{
		if(is_null(self::$_instance))
			self::$_instance = new self($login, $pass, $status = 'online', $port = '5222');

		return self::$_instance;
	}

	protected function __clone()
	{
		// ограничивает клонирование объекта
	}

	public function __destruct()
	{
		/*
		//разрыв соединения
		for($i=1; $i<=5; $i++)
		{
			sleep(2); // ставим паузу в 3 секунды, чтобы не создавать большую нагрузку на php
			$this->_getXml($this->_stream);
		}
		*/
	}

	private function init()
	{
		if(Tools::threader(self::THREAD_NAME))
		{
			if($this->_stream = fsockopen($this->_host,$this->_port,$errorno,$errorstr,10))
			{
				stream_set_blocking($this->_stream,0);
				stream_set_timeout($this->_stream,3600*24);

				//приветствие
				$xml = '<?xml version="1.0"?><stream:stream xmlns:stream="http://etherx.jabber.org/streams" version="1.0" xmlns="jabber:client" to="'.$this->_host.'" xml:lang="en" xmlns:xml="http://www.w3.org/XML/1998/namespace">';
				fwrite($this->_stream,$xml."\n");

				$content = $this->_getXml($this->_stream);

				if(preg_match('!<stream!', $content))
				{
					if(preg_match('!<starttls!', $content))
					{
						//если поддерживает защищенный режим то включаем
						$xml = '<starttls xmlns="urn:ietf:params:xml:ns:xmpp-tls"/>';
						fwrite($this->_stream,$xml."\n");
						$content = $this->_getXml($this->_stream);

						if(preg_match('!<proceed!', $content))
						{
							//сервер подтвердил переход в защищенный режим

							stream_set_blocking($this->_stream, 1);
							stream_socket_enable_crypto($this->_stream, TRUE, STREAM_CRYPTO_METHOD_TLS_CLIENT);
							stream_set_blocking($this->_stream, 0);

							//снова посылаем приветствие
							$xml = '<?xml version="1.0"?><stream:stream xmlns:stream="http://etherx.jabber.org/streams" version="1.0" xmlns="jabber:client" to="'.$this->_host.'" xml:lang="en" xmlns:xml="http://www.w3.org/XML/1998/namespace">';
							fwrite($this->_stream, $xml."\n");
							$content = $this->_getXml($this->_stream);

							if(preg_match('!hash=\'sha-1\'!', $content))
								$this->tlsEnabled = true;
						}
						else
						{
							$this->error = 'error tls enable';
							return false;
						}

					}

					//авторизация
					$xml = '<auth xmlns="urn:ietf:params:xml:ns:xmpp-sasl" mechanism="PLAIN">'.base64_encode("\x00".$this->_login."\x00".$this->_pass).'</auth>';
					fwrite($this->_stream, $xml."\n");
					$content = $this->_getXml($this->_stream);

					if(preg_match('!<success!', $content))
					{
						//снова посылаем приветствие
						//todo: проверку ответа
						$xml = '<?xml version="1.0"?><stream:stream xmlns:stream="http://etherx.jabber.org/streams" version="1.0" xmlns="jabber:client" to="'.$this->_host.'" xml:lang="en" xmlns:xml="http://www.w3.org/XML/1998/namespace">';
						fwrite($this->_stream, $xml."\n");
						$content = $this->_getXml($this->_stream);

						$xml = '<iq type="set" id="'.$this->_getNewRequestId().'"><bind xmlns="urn:ietf:params:xml:ns:xmpp-bind"><resource>'.self::SESSION_IDENTIFIER.'</resource></bind></iq>';
						fwrite($this->_stream, $xml."\n");
						$content = $this->_getXml($this->_stream);

						if(preg_match('!<iq id=\''.$this->_lastRequestId.'\' type=\'result\'>.+?<jid>(.+?)</jid></bind></iq>!', $content, $res))
						{
							$this->_sessionIdentifier = $res[1];

							//запуск сессии
							$xml = '<iq type="set" id="'.$this->_getNewRequestId().'" to="'.$this->_host.'"><session xmlns="urn:ietf:params:xml:ns:xmpp-session"/></iq>';
							fwrite($this->_stream, $xml."\n");
							$content = $this->_getXml($this->_stream);

							if(preg_match('!<iq type=\'result\' .+? id=\''.$this->_lastRequestId.'\'/>!', $content, $res))
							{
								//список контактов
								$xml = '<iq type="get" id="'.$this->_getNewRequestId().'"><query xmlns="jabber:iq:roster"/></iq>';
								fwrite($this->_stream, $xml."\n");
								$content = $this->_getXml($this->_stream);

								if($this->_setContacts($content))
								{
									//выходим онлайн, устанавливаем статус
									$xml = '<presence><show>'.$this->status.'</show><status>online</status><priority>10</priority></presence>';
									fwrite($this->_stream, $xml."\n");
									$content = $this->_getXml($this->_stream);

									if(preg_match('!<presence!', $content))
									{
										//echo 'presenceContent: '.$content."\n\n";

										if($this->_setOfflineMessages($content))
										{
											//отправить свой статус всем кто запросил
											if(preg_match_all('!<presence from=\'(.+?)\' to=\''.$this->_loginStr.'\' type=\'subscribe\'><status></status></presence>!s', $content, $res))
											{
												foreach($res[1] as $key=>$from)
												{
													if(!$this->sendPresence($from, 'subscribed'))
														return false;
												}
											}

											return true;
										}
										else
											return false;
									}
									else
									{
										$this->error = 'error presence';
										return false;
									}


								}
								else
								{
									return false;
								}

							}
							else
							{
								$this->error = 'error session link';
								return false;
							}

						}
						else
						{
							$this->error = 'bind error';
							return false;
						}

					}
					else
					{
						$this->error = 'ошибка авторизации';
						return false;
					}

				}
				else
				{
					$this->error = 'error connect: '.$content;
					return false;
				}
			}
			else
				die('no stream');
		}
		else
		{
			$this->errro = 'alread run';
			return false;
		}
	}

	/*
	 * отправить свой статус собеседникам
	 */
	public function sendPresence($to, $type)
	{
		$xml = '<presence from="'.$this->_sessionIdentifier.'" to="'.$to.'" type="'.$type.'"/>';

		fwrite($this->_stream, $xml."\n");

		$content = $this->_getXml($this->_stream);

		if(preg_match('!type=\'set\'><query!', $content))
			return true;
		else
		{
			$this->error  = 'ошибка sendPresence: '.$content;
			return false;
		}
	}

	private function _getXml($stream)
	{
		usleep($this->pause); // перед получением информации дадим паузу, чтобы сервер успел отдать информацию
		$xml='';

		// запрашивать данные 1600 раз, но не более 15 пустых строк
		$emptyLine = 0;

		for($i=0; $i<1600; $i++)
		{
			$line = fread($stream,2048);
			if(strlen($line) == 0)
			{
				$emptyLine++;
				if($emptyLine > 15) break;
			}
			else
			{
				$xml .= $line;
			}
		}

		if(!$xml)
			return false;

		return $xml;
	}

	/*
	 * уникальный id для запроса
	 */
	private function _getNewRequestId()
	{
		$this->_lastRequestId = round(microtime(true)*1000);
		return $this->_lastRequestId;
	}

	/*
	 * парсит ответ, устанавливает список контактов
	 */
	private function _setContacts($content)
	{
		if(preg_match('!query xmlns\=\'jabber\:iq\:roster\'!', $content))
		{
			if(preg_match_all('!<item.+?name=\'(.+?)\' jid=\'(.+?)\'><group>(.+?)</group></item>!s', $content, $res))
			{
				foreach($res[1] as $key=>$name)
				{
					$this->contacts[$key]['name'] = $name;
					$this->contacts[$key]['id'] = $res[2][$key];
					$this->contacts[$key]['group'] = $res[3][$key];
				}
			}

			return true;
		}
		else
		{
			$this->error = 'error parse contacts: '.$content;
			return false;
		}
	}

	/*
	 * после авторизации заполняет массив $this->offlineMessages сообщениями
	 */
	private function _setOfflineMessages($content)
	{
		if(preg_match('!<presence!', $content))
		{
			if(preg_match_all('!<message from=\'(.+?)\' to=\''.$this->_sessionIdentifier.'\' type=\'chat\' id=\'(.+?)\'>.+?<body>(.+?)</body>.+?stamp=\'(.+?)\'>!s', $content, $res))
			{
				foreach($res[1] as $key=>$from)
				{
					$this->offlineMessages[$key]['from'] = $from;
					$this->offlineMessages[$key]['id'] = $res[2][$key];
					$this->offlineMessages[$key]['text'] = $res[3][$key];
					$this->offlineMessages[$key]['timestamp'] = strtotime($res[4][$key]);
					$this->offlineMessages[$key]['date'] = date('d.m.Y H:i:s', $this->offlineMessages[$key]['timestamp']);
				}
			}

			return true;
		}
		else
		{
			$this->error = 'error parse offlineMessages: '.$content;
			return false;
		}
	}

	/*
	 * отсылает сообщение на xmpp
	 */
	public function sendMessage($to, $text)
	{
		toLogRuntime('jabber msg: '.Tools::shortText($text, 50).' => '.$to);

		$xml = '<message type="chat" from="'.$this->_sessionIdentifier.'" to="'.$to.'" id="'.$this->_getNewRequestId().'"><body>'.$text.'</body></message>';
		fwrite($this->_stream, $xml."\n");
		$content = $this->_getXml($this->_stream);

		return true;
	}



}