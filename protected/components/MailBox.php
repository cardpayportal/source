<?
class MailBox
{
	public $error;
	private $box;

	public function __construct($server, $login, $pass)
	{
		imap_timeout(IMAP_OPENTIMEOUT, 30);
		imap_timeout(IMAP_READTIMEOUT, 30);
		imap_timeout(IMAP_WRITETIMEOUT, 30);
		imap_timeout(IMAP_CLOSETIMEOUT, 30);
		//die('ff'.$server.' '.$login.' '.$pass);

		if(!$this->box = @imap_open($server, $login, $pass))
			$this->error = 'error auth: '.$login.' : '. imap_last_error();
	}

	public function __destruct()
	{
		if($this->box and is_object($this->box))
		{
			imap_close($this->box);
		}
	}

	/**
	 * @param string|bool $from
	 * @param bool $onlyUnread
	 * @param int $limit
	 * @return array в порядке убывания даты
	 */
	public function getMessages($from = false, $onlyUnread=false, $limit=100)
	{
		$filter = '';

		if($onlyUnread)
			$filter = 'UNSEEN';


		$mails = imap_search($this->box, $filter);
		$mails = array_reverse($mails);


		$result = array();

		foreach($mails as $number)
		{
			if(count($result) >= $limit)
				break;

			$header = imap_header($this->box, $number);

			if($from !== false)
			{
				if(mb_strpos($header->fromaddress, $from, null, 'utf-8') === false)
					continue;
			}
			$body = imap_fetchbody($this->box, $number, 1);
			$body = $this->decode(imap_qprint($body), 'cp1251');

			/*
			 imap_base64($text);
			else if($structure->encoding == 4) {
    		$text = imap_qprint($text));
			 */

			$result[] = array(
				'header'=>$header,
				'text'=>$body,
			);

		}

		return $result;
	}

	/*
	 * decode utf7=>utf8
	 */
	public function decode($text, $encodingFrom = 'UTF7-IMAP')
	{
		return mb_convert_encoding($text, 'UTF8', $encodingFrom);
	}
}