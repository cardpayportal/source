<?php

class Tools
{
	const ANTICAPTCHA_NOT_READY = 'captcha_not_ready';
	const ANTICAPTCHA_GET_LIMIT = 'лимит запросов на получение результата ';

	public static $error;
	private static $microtimeStart;
	private static $memoryStart;

	public static function shortText($str, $len=false, $id=false, $type=false)
	{
		if(!$len)
			$len = 50;

		$str = iconv('utf-8','utf-8',trim(strip_tags($str)));

		if(mb_strlen($str, 'utf-8')>$len)
			$res = mb_substr($str, 0, $len, 'utf-8').'..';
		else
			$res = $str;

		return $res;
	}

	/**
	 * Проверяет удовлетворяет ли значение типу проверяемых данных.
	 * Если передан массив, то вернет false, если 1 элемент не соответствует типу
	 *
	 * Типы:
	 * -ip
	 * -proxy
	 * -domain
	 * -url
	 */
	public static function validate($values, $type)
	{
		if(!is_array($values))
			$values = array($values);

		if(empty($values))
			return false;

		foreach($values as $value)
		{
			switch($type)
			{
				case 'ip':
					if(!preg_match(self::getRegExp('ip'),$value))
						return false;
					break;

				case 'proxy':
					if(!preg_match(self::getRegExp('proxy'),$value))
						return false;
					break;

				case 'domain':
					if(!preg_match(self::getRegExp('linkDomain'),$value))
						return false;
					break;

				case 'domain1':
					if(!preg_match(self::getRegExp('domain'),$value))
						return false;
					break;

				case 'url':
					if(!preg_match(self::getRegExp('url'),$value))
						return false;
					break;

				case 'url1':
					if(!preg_match(self::getRegExp('url1'),$value))
						return false;
					break;

				case 'email':
					if(!preg_match(self::getRegExp('email'),$value))
						return false;
					break;

				case 'phone':
					if(!preg_match(self::getRegExp('phone'),$value))
						return false;
					break;

				case 'icq':
					if(!preg_match(self::getRegExp('icq'),$value))
						return false;
					break;

				default: return false;
			}
		}

		return true;
	}

	/**
	 * Очистка одномерного массива.
	 * @types - может быть массивом типов
	 * @array - если не массив то вернет обратно
	 *
	 * Тип очистки:
	 * empty
	 * unique
	 */
	public static function clearArr($array, $types)
	{
		if(!is_array($types))
			$types = array($types);

		if(!is_array($array))
			return $array;

		foreach($types as $type)
		{
			switch($type)
			{
				case 'empty':
					foreach($array as $key=>$val)
					{
						if(empty($val))
						{
							unset($array[$key]);
						}
					}
					break;

				case 'unique':
					$array = array_unique($array);
					break;
			}
		}

		return $array;
	}


	/**
	 * Возвращает представление массива результатов
	 * $resultArr = array('ошибка'=>'error','успех'=>'succes','предупреждение'=>'warning')
	 */
	public static function resultOut($resultArr)
	{

		$errorBlock = '<p><font color="red">{result}</font></p>';
		$successBlock = '<p><font color="green">{result}</font></p>';
		$warningBlock = '<p><font color="brown">{result}</font></p>';

		$successFlash = '<script>$(document).ready(function(){showMsg(\'{result}\', \'success\')});</script>';
		$errorFlash = '<script>$(document).ready(function(){showMsg(\'{result}\', \'error\')});</script>';

		$content = '';

		foreach($resultArr as $result=>$type)
		{
			if(!empty($result))
			{
				switch($type)
				{
					case 'error':
						$content.=str_replace('{result}',$result,$errorBlock);
						break;

					case 'success':
						$content.=str_replace('{result}',$result,$successBlock);
						break;

					case 'warning':
						$content.=str_replace('{result}',$result,$warningBlock);
						break;
				}
			}
		}

		return $content;
	}

	public static function flashOut()
	{
		$successFlash = '<script>$(document).ready(function(){showMsg(\'{result}\', \'success\')});</script>';
		$errorFlash = '<script>$(document).ready(function(){showMsg(\'{result}\', \'error\')});</script>';

		$content = '';
		//флешка
		if(Yii::app()->user->hasFlash('success'))
			$content.= str_replace('{result}', Yii::app()->user->getFlash('success'), $successFlash);

		if(Yii::app()->user->hasFlash('error'))
			$content.= str_replace('{result}', Yii::app()->user->getFlash('error'), $errorFlash);

		return $content;
	}

	/**
	 * ip
	 * proxy
	 * domain
	 * url
	 * linkDomain
	 *
	 */
	public static function getRegExp($name)
	{
		$arr = array(
			'ip'=>"!\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}!",
			'proxy'=>"!\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d{2,4}!",
			'domain'=>"!^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$!",
			'linkDomain'=>"/(http\:\/\/|)(www\.|)([0-9a-zа-я\._-]+)/i", //из http://google.com/dfsdsf. выбрать домен google.com
			'url'=>"!^(http(s|)?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-?]*)*\/?$!",
			'url1'=>"!(http(s|)://|)(www\.|)([0-9a-zа-я\._-]+)\.([0-9a-zа-я]{2,6})!i",
			'email'=>"/^[-a-z0-9!#$%&'*+=?^_`{|}~]+(?:\.[-a-z0-9!#$%&'*+=?^_`{|}~]+)*@(?:[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])?\.)*(?:aero|arpa|asia|biz|cat|com|coop|edu|gov|info|int|jobs|mil|mobi|museum|name|net|org|pro|tel|travel|[a-z][a-z])$/",
			'phone'=>'/^([87][3489]\d{9}|[87]7[07]\d{8}|[12456]\d{9,13}|376\d{6}|86\d{11}|8[14]\d{10}|90\d{10}|96(0[79]|170|13)\d{6}|96[23]\d{9}|964\d{10}|96(5[69]|89)\d{7}|96(65|77)\d{8}|92[023]\d{9}|91[189]\d{9}|9[34]7\d{8}|959\d{7}|989\d{9}|9[79]\d{8,12}|380[4569]\d{8}|38[15]\d{9}|375[234]\d{8}|372\d{7,8}|37[0-4]\d{8}|37[6-9]\d{7,11}|3[1-69]\d{8,12}|38[12679]\d{8})$/',
			'icq'=>'!.{3,100}!',
		);

		return $arr[$name];
	}

	/**
	 * Вернет домен по ссылке
	 */
	public static function getDomain($href)
	{
		preg_match(self::getRegExp('linkDomain'), $href, $res);
		$result = $res[3];

		return $result;
	}

	public static function dateFormat($timestamp, $format = false)
	{
		if(!$format)
			$format = 'dd.MM.yyyy HH:mm';

		return Yii::app()->dateFormatter->format($format, $timestamp);
	}

	public static function parseCsv($content)
	{
		$result = array();

		$content = str_replace("\r\n", "\n", $content);

		$rows = explode("\n", $content);

		if(!empty($rows))
		{
			foreach($rows as $row)
			{
				$result[] = explode(';', $row);
			}
		}

		return $result;
	}

	public static function createCsv($array)
	{
		$result = array();

		foreach($array as $row)
		{
			$result[] = implode(';', $row);
		}

		$result = implode("\r\n", $result);

		return $result;
	}

	/**
	 * либо $path либо $content
	 */
	public static function returnFile($path=false, $content=false)
	{

		if($path)
		{
			clearstatcache();

			$name = basename($path);
			header ("Content-Type: application/octet-stream");
			header ("Accept-Ranges: bytes");
			header ("Content-Length: ".filesize($path));
			header ("Content-Disposition: attachment; filename=$name");
			readfile($path);
		}
		else
		{
			$name = 'file.txt';
			header ("Content-Type: application/octet-stream");
			header ("Accept-Ranges: bytes");
			header ("Content-Length: ".strlen($content));
			header ("Content-Disposition: attachment; filename=$name");
			die($content);
		}
	}

	/**
	 * Расширение файла
	 */
	public static function ext($fileName, $type=false)
	{
		$explode = explode('.', $fileName);
		$ext = strtolower(end($explode));

		if(strlen($ext)<6)
		{
			if($type)
			{
				if($type=='img')
				{
					if(in_array(strtolower($ext), array('jpg', 'jpeg', 'png', 'tiff', 'tif', 'png', 'bmp', 'gif')))
						return $ext;
				}
			}
			else
				return $ext;
		}

	}

	public static function mimeExt($fileName)
	{
		$inf = getimagesize($fileName);
		$mime = $inf['mime'];

		if($mime)
		{
			$explode = explode('/', $mime);
			return $explode[1];
		}
		else
			return self::ext($fileName);


	}

	public static function move($from, $to)
	{
		return move_uploaded_file($from, $to);
	}

	/*
	 * необходимо чтобы в папке log был хотябы один файл
	 * todo: сделать модулем или расширением
	 * todo: проверка категории на валидность
	 * todo: создать функции типа logError(), logRuntime() для упрощения
	 * многопоточная конкурентная запись в файл учтена
	 * $isFatal - завершить выполнение после записи в лог
	 * $sendSms - послать уведомление админу
	 * $dublicated - false(без повторений), true(допускает повторения ошибок)
	 * сделать многопоточное уведомление
	 */
	public static function log($content, $isFatal=null, $sendSms=null, $category=null, $dublicated=true)
	{
		$defaultCategory = 'log';

		if(!$category)
			$category = $defaultCategory;

		$logFile = Yii::app()->params['logDir'].$category.'.txt';

		if(!file_exists($logFile))
			file_put_contents($logFile, '');

		$logSizeMax = Yii::app()->params['maxLogSize'];

		clearstatcache(true, $logFile);

		if(filesize($logFile) > $logSizeMax)
			$mode = 'w+';
		else
			$mode = "a+";

		$contentWithoutTime = str_replace(array("\r", "\n"),' ', htmlspecialchars($content));
		$content1 = "\n".time().'  '.$contentWithoutTime;

		//разрешение записи строки, нужно для логики исключения дубликатов
		$canWrite = true;

		$searchContent = Tools::logOut($rowNumber = 0, $category);

		if($f = @fopen($logFile, $mode))
		{
			//проверяем можно ли писать дубликаты в логи
			if($dublicated === false)
			{
				if(stripos(str_replace(" ","", $searchContent), str_replace(" ","", $contentWithoutTime)) !== false)
					$canWrite = false;
			}

			flock($f, LOCK_EX);	//ожидание пока поток станет единственным кто пишет в файл

			if($canWrite === true)
			{
				if(fwrite($f, $content1)===false)
					die('error write '.basename($logFile));
			}

			fflush($f);//очищение файлового буфера и записьв файл(надо делать перед закрытием)
			flock($f, LOCK_UN);//разблокировка файла
			fclose($f);
		}
		else
			die('error fopen '.basename($logFile));


		if($isFatal or $sendSms)
		{
			if(cfg('notice_enabled'))
			{
				if(self::threader('noticeAdmin'))
				{
					//чтобы телефон не слать
					$content = preg_replace('!(\+\d{11,12}|\d{11,12})!', '...', $content);
					$content = preg_replace('!"http[^"]+"!', '...', $content);

					Yii::app()->noticeAdmin->send($content);
				}
			}
		}

		if($isFatal)
			die($content);

		return true;
	}

	/**
	 * @param $array
	 * @param $sortIndex
	 *
	 * @return bool
	 *
	 * сортировка выборкой
	 */
	public static function selectionSort($array, $sortIndex)
	{
		if($arrayCount = count($array))
		{
			for($i=0; $i < $arrayCount-1; $i++)
			{
				$small = $i;
				for($k=$i+1; $k < $arrayCount; $k++)
				{
					if($array[$k][$sortIndex] > $array[$small][$sortIndex])
						$small = $k;
				}

				$buf = $array[$i];
				$array[$i] = $array[$small];
				$array[$small] = $buf;
			}

			return $array;
		}
		else
			return false;
	}

	/**
	 * @param int $rowNumber
	 * @param null $category
	 * @param bool $replacePhones
	 *
	 * @return array|bool
	 *
	 * вывод логов отсортированный, сгруппированный
	 */
	public static function logOutSorted($rowNumber = 0, $category=null, $replacePhones = true)
	{
		$categories = self::logCategories();

		if(!$category)
			$category = current($categories);

		$logFile = Yii::app()->params['logDir'].$category.'.txt';

		$explode = explode("\n", file_get_contents($logFile));

		$tempArrLog = [];
		foreach($explode as $key=>$line)
		{
			if(preg_match('!(\d+)\s+(.+?:|.+)!iu', $line, $matches))
			{
				$str = $matches[2];
			}
			else
				$str = $line;

			$tempArrLog[$key]['time'] = $matches[1];
			$tempArrLog[$key]['shortStr'] = preg_replace('!^\d+!','',$str);
			$tempArrLog[$key]['fullStr'] = preg_replace('!^\d+!','',$line);
			$tempArrLog[$key]['index'] = $key;
		}

		$result = [];
		$result = self::selectionSort($tempArrLog, 'shortStr');

		$categoryIndex = 0;
		$resultArr = [];

		$resultCount = count($result);


		foreach($result as $key=>$element)
		{

			if($resultCount > $key+1)
			{
				if($element['shortStr'] !== $result[$key+1]['shortStr'])
					$resultArr[$categoryIndex++][] = $element;
				else
					$resultArr[$categoryIndex][] = $element;
			}
			else
				continue;
		}

		foreach($resultArr as $key=>$arr)
		{
			$resultArr[$key] = self::selectionSort($arr, 'time');
			$resultArr[$key]['lastTime'] = $resultArr[$key][0]['time'];
			if($resultArr[$key]['lastTime'] == '')
			{
				unset($resultArr[$key]);
				continue;
			}
		}
		$resultArr = self::selectionSort($resultArr, 'lastTime');

		return $resultArr;
	}

	/**
	 * вывод логов
	 *
	 */
	public static function logOut($rowNumber = 0, $category=null, $replacePhones = true)
	{
		$categories = self::logCategories();

		if(!$category)
			$category = current($categories);

		$logFile = Yii::app()->params['logDir'].$category.'.txt';

		$explode = explode("\n", file_get_contents($logFile));

		$arr = array();

		foreach($explode as $key=>$row)
		{
			if($key < $rowNumber)
				continue;

			preg_match('!^(\d+  )!', $row, $res);

			$str = trim(preg_replace("!$res[1]!", '<strong>'.date('d.m.Y H:i:s', $res[1]).'</strong> ', $row, 1));

			if($replacePhones)
			{
				preg_match_all('!(\+([37]\d{10,11}))!', $str, $res);

				foreach($res[1] as $resKey=>$login)
					$str = str_replace($login, '<a target="_blank" title="показать в списке" href="'.url('account/list', array('login'=>trim($res[2][$resKey], '+'))).'">'.$login.'</a>', $str);
			}


			$arr[] = $str;
		}


		$implode = implode('<br/>', array_reverse($arr));

		return $implode;
	}

	/*
	 * имена файлов в папке log/
	 */
	public static function logCategories()
	{
		$logDir = Yii::app()->params['logDir'];
		//clearstatcache(true, $logDir);

		$result = array();

		foreach(Tools::dirFiles($logDir) as $file)
		{
			$baseName = basename($file, '.txt');

			$result[] = $baseName;
		}

		sort($result);

		return $result;
	}

	public static function url($path, $params=array())
	{
		$url = Yii::app()->createUrl($path, $params);

		$url = str_replace('%23', '#', $url);

		return $url;
	}

	public static function absUrl($path, $params=array())
	{
		return Yii::app()->createAbsoluteUrl($path, $params);
	}

	public static function timeOut($timePass=false)
	{
		clearstatcache();

		if(file_exists(self::cfg('tmpDir').'stop.txt'))
			self::log('stopped: '.file_get_contents(self::cfg('tmpDir').'stop.txt'), 1);

		if(!defined('MAX_E_TIME') or !defined('START_TIME')) toLog('константы MAX_E_TIME и START_TIME не определены', true);

		$time = time()-START_TIME;

		if($timePass)
			return $time;

		if($time>=MAX_E_TIME)
			return true;
	}

	public static function selectGenerator($paramsArr, $selected=false, $selectedBy='value')
	{
		$result = '';

		if(!$paramsArr)
			return $result;

		foreach($paramsArr as $key=>$val)
		{
			$selectedStr = '';

			if($selected)
			{
				if(!is_array($selected))
					$selected = array($selected);

				if(($selectedBy=='value' and in_array($val, $selected)) or ($selectedBy=='key' and in_array($key, $selected)))
					$selectedStr = ' selected="selected"';
			}

			if($selectedBy=='value')
				$keyStr = str_replace('"', '\"', $val);
			else
				$keyStr = $key;

			$result.= '<option value="'.$keyStr.'"'.$selectedStr.'>'.$val.'</option>';
		}

		return $result;
	}

	public static function trans($text)
	{
		$replaceArr = array('а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'j','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sh','ъ'=>'','ы'=>'i','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya','А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E','Ё'=>'E','Ж'=>'J','З'=>'Z','И'=>'I','Й'=>'Y','К'=>'K','Л'=>'L','М'=>'M','Н'=>'N','О'=>'O','П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F','Х'=>'H','Ц'=>'C','Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Sh','Ъ'=>'','Ы'=>'I','Ь'=>'','Э'=>'E','Ю'=>'Yu','Я'=>'Ya',' '=>'-','/'=>'-',);

		$result=strtr($text,$replaceArr);
		$result = preg_replace('![^0-9a-zA-Z_-]!', '', $result);

		return $result;
	}

	/**
	 * Для имен файлов ...
	 */
	public static function trans1($text)
	{
		$replaceArr = array('а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'j','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sh','ъ'=>'','ы'=>'i','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya','А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E','Ё'=>'E','Ж'=>'J','З'=>'Z','И'=>'I','Й'=>'Y','К'=>'K','Л'=>'L','М'=>'M','Н'=>'N','О'=>'O','П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F','Х'=>'H','Ц'=>'C','Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Sh','Ъ'=>'','Ы'=>'I','Ь'=>'','Э'=>'E','Ю'=>'Yu','Я'=>'Ya',' '=>'_','-'=>'_','/'=>'-',);

		$result=strtr($text,$replaceArr);
		$result = preg_replace('![^0-9a-zA-Z_]!', '', $result);

		return strtolower($result);
	}

	public static function getSep($content)
	{
		if(strpos($content, "\r\n")!==false)
			return "\r\n";
		else
			return "\n";
	}

	public static function checkImg($content)
	{
		$img = @imagecreatefromstring($content);

		if($img)
		{
			imagedestroy($img);
			return true;
		}
	}

	public static function checkImgExeptions()
	{
		return array('tiff', 'tif', 'bmp', 'psd');
	}

	public static function getMyIp()
	{
		return self::cfg('ip');
	}

	public static function cfg($name)
	{
		return Yii::app()->params[$name];
	}

	public static function ucfirst($str, $enc = 'utf-8')
	{
		return mb_strtoupper(mb_substr($str, 0, 1, $enc), $enc).mb_substr($str, 1, mb_strlen($str, $enc), $enc);
	}

	/**
	 *
	 * для замера времени выполнения участка кода в секундах(до 3х знаков) в секундах
	 * +разницу в потребляемой памяти в мегабайтах
	 *
	 * вызов:
	 * 	Tools::runtimeLog(__METHOD__.' (line '.__LINE__.')');
	 * 	 участок кода
	 * 	Tools::runtimeLog(__METHOD__.' (line '.__LINE__.')');
	 * @param string|bool $label
	 * @return string|null
	 */
	public static function runtimeLog($label = false)
	{
		if(self::$microtimeStart)
		{
			$time = (microtime(true) - self::$microtimeStart);
			$memory = (memory_get_usage() - self::$memoryStart) / 1000000;

			$msg = "$label: ".formatAmount($time, 3).' sec ('.formatAmount($memory, 3).' mb)';

			self::log($msg);

			self::$microtimeStart = null;

			return $msg;
		}
		else
		{
			self::$microtimeStart = microtime(true);
			self::$memoryStart = memory_get_usage();
		}
	}

	public static function arr2Str($arr)
	{
		ob_start();

		print_r($arr);

		$content = ob_get_contents();
		ob_end_clean();

		return $content;
	}

	public static function trim($str, $chars=false)
	{
		return trim(str_replace(chr(194).chr(160), ' ', $str), $chars);
	}

	/**
	 * добавляет параметр в урл если его нет
	 */
	public static function urlAddParam($url, $params)
	{
		$paramStr = '';
		$result = rtrim($url, '?&');

		foreach($params as $key=>$param)
			$paramStr.='&'.$key.'='.$param;

		if(strpos($url, '?')!==false)
			$result.= $paramStr;
		else
			$result.= '?'.ltrim($paramStr, '&');

		return $result;
	}

	/**
	 * имя папки в зависимости от $id и макс количества файлов в каждой папке(конфиг)
	 */
	public static function dirName($id)
	{
		$maxFiles = self::cfg('maxFilesPerFolder');

		return ceil($id/$maxFiles);
	}

	public static function recursiveReplace($what, $byWhat, $where)
	{
		$result = str_replace($what, $byWhat, $where);

		if(strpos($result, $what)!==false)
			$result = self::recursiveReplace($what, $byWhat, $result);

		return $result;
	}

	/**
	 * делает из калеки нормальный урл
	 */
	public static function urlValid($url)
	{
		if(is_array($url))
		{
			foreach($url as $key=>$val)
				$url[$key] = self::urlValid($val);
		}
		else
		{
			$urlParse = parse_url($url);

			$url = $urlParse['scheme'].'://'.$urlParse['host'];

			if($path = rawurldecode($urlParse['path']))
			{
				$newPath = '';

				for($i=0; $i<=mb_strlen($path); $i++)
				{
					$val = mb_substr($path, $i, 1, 'utf-8');

					if(preg_match('![^/&=a-zA-Z0-9_-]!iu', $val))
					{
						$newPath.= rawurlencode($val);
					}
					else
						$newPath.= $val;

				}

				$url.=$newPath;
			}

			if($query = rawurldecode($urlParse['query']))
			{
				$newQuery = '';

				for($i=0; $i<=mb_strlen($query); $i++)
				{
					$val = mb_substr($query, $i, 1, 'utf-8');

					if(preg_match('![^/&=a-zA-Z0-9_-]!iu', $val))
					{
						$newQuery.= rawurlencode($val);
					}
					else
						$newQuery.= $val;

				}

				$url.='?'.$newQuery;
			}
		}

		return $url;
	}

	public static function utf8($text)
	{
		return str_replace(chr(194).chr(160), ' ', $text);
	}

	/**
	 * решает проблему при декодировании json взятого из другой кодировки
	 * @param string $json
	 * @return string
	 */
	public static function utf8Json($json)
	{
		$encoding = mb_detect_encoding($json);

		if($encoding == 'UTF-8')
			$json = preg_replace('/[^(\x20-\x7F)]*/', '', $json);

		return $json;
	}


	public static function clearDir($path, $deleteSelf=false)
	{
		clearstatcache();

		$path = rtrim($path, '/');

		if(file_exists($path) and self::isDir($path))
		{
			if($files = self::dirFiles($path))
			{
				foreach($files as $file)
				{
					if(self::isDir($file))
					{
						if(!self::clearDir($file, true))
							return false;
					}
					else
					{
						if(!unlink($file))
							return false;
					}
				}
			}

			if($deleteSelf)
				rmdir($path);

			return true;
		}
		else
			return false;
	}

	/*
	 * полные пути к файлам в этой папке
	 */
	public static function dirFiles($path)
	{
		clearstatcache();

		$result = array();

		$path = rtrim($path, '/');

		if(file_exists($path) and self::isDir($path))
		{
			foreach(scandir($path) as $file)
			{
				if($file=='.' or $file=='..')
					continue;

				$result[] = $path.'/'.$file;
			}
		}

		return $result;
	}

	public static function isDir($path)
	{
		if(!file_exists($path))
			return false;

		if(@scandir($path))
			return true;
		else
			return false;
	}

	public static function isFile($path)
	{
		if(!file_exists($path))
			return false;

		if(@scandir($path))
			return false;
		else
			return true;
	}

	public static function htmlDecode($str)
	{
		if(preg_match_all('!#(\d+);!', $str, $res))
		{
			foreach($res[1] as $key=>$val)
				$str = str_replace($res[0][$key], chr($val), $str);
		}

		return $str;
	}

	public static function isWeekEnd($timestamp)
	{
		if(date('N', $timestamp)>5)
			return true;
	}

	/**
	 * @param $content
	 * @return bool|simple_html_dom
	 */
	public static function dom($content)
	{
		require_once(dirname(__FILE__).'/simple_html_dom.php');
		$dom = str_get_html($content);
		return $dom;
	}

	public static function generateCode($symbols=false, $len=false)
	{
		if(!$symbols)
			$symbols = '0123456789';

		if(!$len)
			$len = 4;

		$result = '';

		for($i=1;$i<=$len;$i++)
			$result .= $symbols{rand(0, strlen($symbols)-1)};

		return $result;
	}



	/**
	 * проверяет файл по mime на соответствие условиям
	 * $formats - строка с форматами, через запятую
	 */
	public static function checkFileType($file, $formats)
	{
		$exts = explode(',', $formats);

		if(file_exists($file))
		{
			$inf = getimagesize($file);

			$mime = $inf['mime'];

			foreach($exts as $ext)
			{
				if(strpos($mime, '/'.$ext))
					return true;
			}
		}
	}

	public static function notice($text, $to=false)
	{
		$emailTitle = 'fff: '.mb_substr($text, 0, 60, 'utf-8').'..';
		$emailFrom = 'fff;email@fff.com';

		if($to=='manager')
		{
			$phone = Config::Val('manager_phone');
			$email = Config::Val('manager_email');

			if(!$phone and !$email)
				Tools::log('не указан телефон и email менеджера');
			else
			{
				if($phone)
					User::sms($phone, strip_tags($text));

				if($email)
					Tools::email($email, $text, $emailTitle, $emailFrom);
			}
		}
		else
		{
			$config = Tools::cfg('notice');

			if($config['enabled'] and (time() - intval(Config::Val('last_notice')) >= $config['interval']))
			{
				Config::Val('last_notice', time());

				$content = @file_get_contents($config['url'].urlencode($text));

				self::log('уведомление админу: '.$text);

				if($content=='success')
					return true;
				else
					self::log('ошибка уведомления: '.$content);
			}
		}
	}

	public static function email($to, $text, $title, $from)
	{
		global $config;

		if(mb_strlen($title, 'utf-8') > 60)
			$title = mb_substr($title, 0, 58, 'utf-8').'..';

		$m = new Email;
		$m->From($from);
		$m->To($to);
		$m->Subject($title);
		$m->Body($text, "html");

		$result = $m->Send();

		if($result)
		{
			Tools::log('email на '.$to.': '.$text);
			return true;
		}
		else
		{
			$error = 'ошибка отправки email: '.$to.' от '.$from.' '.$title.' : '.$text;
			Tools::log($error);
			Tools::notice($error);
			return $error;
		}

	}

	public static function cleanTags($content)
	{
		return preg_replace('!<(\w+) [^>]+?>!', '<$1>', $content);
	}

	public static function languageHeader($ip)
	{
		$langs = array(
			'ru' => array('ru', 'be', 'uk', 'ky', 'ab', 'mo', 'et', 'lv'),
		);

		$class = new LangDetect;

		$lang = $class->getBestMatch('en', $langs);

		return $lang;
	}

	/**
	 * задаем шанс, генерируем целое число от 1 до $chanceCount, если оно <=$chance то return true
	 * $chance - целое число, шанс на успех
	 * $chanceCount - целое число, кол-во шансов
	 */
	public static function trigger($chance, $chanceCount=100)
	{
		if($chanceCount >= $chance)
		{
			$rand = rand(1, $chanceCount);

			if($rand <= $chance)
				return true;
		}
	}

	public static function threader($thread)
	{
		//время через которое принудительно нужно удалять файл
		//если сервер перезагрузится и файл случайно не удалится
		$lifeTime = 600;

		$dir = cfg('threader_dir');
		$file = $dir.$thread.'.txt';

		if(!file_exists($dir))
			Tools::log('not found '.$dir, 1);

		clearstatcache(true, $file);

		if(!file_exists($file))
		{
			if(file_put_contents($file, time())!==false)
				register_shutdown_function(array('Tools', 'threaderClear'), $thread);
			else
				self::log('create fail '.$file, 1);

			return true;
		}
		else
		{
			$timestamp = file_get_contents($file);

			if(time() - $timestamp > $lifeTime)
			{
				if(unlink($file))
					return true;
			}

			self::$error = 'thread '.$thread.' already run';
			return false;
		}
	}

	public static function threaderClear($thread)
	{
		clearstatcache();

		$dir = cfg('threader_dir');

		$file = $dir.$thread.'.txt';

		if(!file_exists($file))
			return true;
		elseif(!unlink($file))
		{
			clearstatcache();

			if(file_exists($file))
				Tools::log('fail delete: '.$file);
		}


		return true;
	}

	/*
	 * есть ли в системе поток с именем $threadName
	 * $isRegExp - является ли $threadName регулярным выражением(для поиска потока по маске)
	 * todo: сделать $lifrTime статик-свойством класса
	 */
	public static function threadExist($threadName, $isRegExp=false)
	{
		clearstatcache();

		$lifeTime = 3600;

		$exist = false;

		if($isRegExp)
		{
			$files = self::dirFiles(cfg('threader_dir'));

			foreach($files as $file)
			{
				if(preg_match($threadName, basename($file)))
				{
					$exist = true;
					break;
				}
			}
		}
		else
		{
			$file = cfg('threader_dir').$threadName.'.txt';
			$exist = file_exists($file);
		}

		if($exist)
		{
			$timestamp = file_get_contents($file);

			if(time() - $timestamp > $lifeTime)
			{
				if(@unlink($file))
					return false;
				else
					toLog('ошибка удаления файла '.$file, 1);
			}
			else
				return true;
		}
		else
			return false;

	}


	public static function queryStr(array $params)
	{
		$result = '';

		foreach($params as $key=>$val)
		{
			$result .= $key.'='.$val.'&';
		}

		return trim($result, '&');
	}

	/*
	 * проверяет не прошло ли с начала выполнения запроса $seconds секунд
	 */
	public static function timeIsOut($seconds)
	{
		if(!defined('START_TIME')) die('константа START_TIME не определена');

		return time() - START_TIME > $seconds;
	}

	/*
	 * запаковать файл или папку
	 * $path - путь к файлу или папке
	 * $zipFile - путь к архиву
	 */
	public static function archive($path, $zipFile)
	{
		$zip = new Zipper;

		if($zip->open($zipFile, ZIPARCHIVE::CREATE)===true)
		{
			return $zip->addDir($path);
		}
		else
		{
			self::$error = 'проверьте права на запись';
			return false;
		}
	}

	/*
	 * распаковать $zipFile в папку $to
	 */
	public static function extract($zipFile, $to)
	{
		$zip = new Zipper;

		if($zip->open($zipFile)===true)
		{
			return $zip->extractTo($to);
		}
		else
		{
			self::$error = 'проверьте права на запись';
			return false;
		}
	}

	/*
	 * сортировка многомерного массива по имени поля
	 * массив передается по ссылке
	 */
	public static function multisort(&$array, $fieldName, $mode = SORT_ASC)
	{
		if($fieldName)
		{
			$column = array();

			foreach($array as $key=>$row)
				$column[$key] = $row[$fieldName];

			array_multisort($column, $mode, $array);

			return true;
		}
		else
			return false;
	}

	/*
	 * возвращает строку с условием для текущего потока
	 * $threadNumber - номер текущего потока, начинается с 0
	 * $threadCountMax - сколько максимум потоков в системе
	 * todo: сделать максимум потоков чтобы было неограничено цифрой 10
	 */
	public static function threadCondition($threadNumber, $threadCountMax)
	{
		$threadMax = 10;

		$fieldName = 'id';

		$threadNumber *= 1;
		$threadNumber = round($threadNumber);

		if($threadNumber > $threadMax -1 or $threadNumber < 0)
		{
			self::$error = 'неверный номер потока';
			return false;
		}

		if($threadNumber > $threadCountMax - 1)
			die('неверный номер потока');

		$numbers = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');

		$chunk = array_chunk($numbers, 10 / $threadCountMax);

		$arr = $chunk[$threadNumber];

		$halfCond = '';

		foreach ($arr as $key => $val)
		{
			$halfCond .= "`{$fieldName}` LIKE '%{$val}'";

			if ($key < count($arr) - 1)
				$halfCond .= " OR ";
		}

		$threadCond = "($halfCond)";

		return $threadCond;
	}

	/*
	 * скачивает файл в $path
	 */
	public static function downloadFile($url, $path)
	{

		if(!$fp = fopen($path, "w"))
			return false;

		$ch = curl_init();

		$options = array(
			CURLOPT_URL=>$url,
			CURLOPT_RETURNTRANSFER=>1,
			CURLOPT_VERBOSE=>0,
			CURLOPT_SSL_VERIFYPEER=>false,
			CURLOPT_SSL_VERIFYHOST=>false,
			CURLOPT_HEADER => false,
			CURLOPT_ENCODING=>'gzip,deflate',
			CURLOPT_FILE => $fp,
			CURLOPT_TIMEOUT => 300,
		);

		curl_setopt_array($ch, $options);

		curl_exec($ch);


		fclose($fp);

		return filesize($path);
	}

	public static function timeSpend()
	{
		return time() - START_TIME;
	}

	/*
	 * распознает рекапчу
	 * todo: сделать универсальную функцию для распознавания любой капчи
	 *
	 * $type = recaptcha
	 * $params = array(
	 * 	'step'=> 'send|get', - послать на распознавание либо получить уже распознанную
	 *
	 * 	'googleApiKey'=>'', - (step=send) постоянный гуглАпи ключ сайта, с которого получаем капчу
	 * 	'pageUrl'=>'qiwi.com', (step=send) - адрес страницы, с которой получена капча, или домен
	 * 	'captchaId'=>'315231423', (step=get) - id капчи в сервисе  антикапчи
	 *
	 * )
	 * todo: проверка баланса, предупреждение о балансе
	 */
	public static function anticaptcha($type=false, $params=array())
	{
		$cfg = cfg('recaptcha');
		/*
					array(
					'urlIn'=>'http://rucaptcha.com/in.php',
					'urlOut'=>'http://rucaptcha.com/res.php?key={recaptchaKey}&action=get&id={recaptchaId}', //recaptchaId - id конкретной капчи в рекапче
					'maxTimeDefault' => 120,
					'sleepTime' => 5,
				);
		*/
		$startTime = time();

		$sender = new Sender;
		$sender->followLocation = false;
		$sender->timeout = 20;
		$sender->pause = 0;

		if($type == 'recaptcha')
		{

			if($params['step'] == 'send')
			{
				if(!$params['googleApiKey'])
					self::$error = 'не указан $params[\'googleApiKey\']';

				if(!$params['pageUrl'])
					self::$error = 'не указан $params[\'pageUrl\']';

				if(!self::$error)
				{
					$url = $cfg['urlIn'];

					$postData = 'key='.$cfg['key'].'&method=userrecaptcha'
						.'&googlekey='.$params['googleApiKey'].'&pageurl='.$params['pageUrl'];

					$content = $sender->send($url, $postData);

					if(preg_match('!OK\|(\d+)!', $content, $res))
					{
						return $res[1];
					}
					else
						self::$error = 'не получен id капчи: '.$content.' (httpCode = '.$sender->info['httpCode'][0].')';

				}
			}
			elseif($params['step'] == 'get')
			{
				if(!$params['captchaId'])
					self::$error = 'не указан $params[\'captchaId\']';

				$url = str_replace(array('{key}', '{captchaId}'), array($cfg['key'], $params['captchaId']), $cfg['urlOut']);

				$content = $sender->send($url);

				if(preg_match('!OK\|(.+)!', $content, $res))
				{
					return $res[1];
				}
				elseif(strpos($content, 'CAPCHA_NOT_READY')!==false)
					self::$error = self::ANTICAPTCHA_NOT_READY;
				else
					self::$error = 'ошибка получения капчи: '.$content;
			}
		}
		elseif($type == 'image')
		{
			if($params['step'] == 'send')
			{
				if(!$params['imageContent'])
					self::$error = 'не указан $params[\'imageContent\']';

				if(!self::$error)
				{
					$url = $cfg['urlIn'];

					$postData = 'key='.$cfg['key'].'&method=base64'
						.'&body='.urlencode(base64_encode($params['imageContent']));

					$content = $sender->send($url, $postData);

					if(preg_match('!OK\|(\d+)!', $content, $res))
					{
						return $res[1];
					}
					else
						self::$error = 'не получен id капчи: '.$content.' (httpCode = '.$sender->info['httpCode'][0].')';

				}
			}
			elseif($params['step'] == 'get')
			{
				if(!$params['captchaId'])
					self::$error = 'не указан $params[\'captchaId\']';

				$url = str_replace(array('{key}', '{captchaId}'), array($cfg['key'], $params['captchaId']), $cfg['urlOut']);

				$content = $sender->send($url);

				if(preg_match('!OK\|(.+)!', $content, $res))
				{
					return $res[1];
				}
				elseif(strpos($content, 'CAPCHA_NOT_READY')!==false)
					self::$error = self::ANTICAPTCHA_NOT_READY;
				else
					self::$error = 'ошибка получения капчи: '.$content;
			}
		}
		elseif($type == 'url')
		{
			if($params['step'] == 'send')
			{
				if(!$params['imageUrl'])
					self::$error = 'не указан $params[\'imageUrl\']';

				if(!self::$error)
				{
					$url = $cfg['urlIn'];

					$postData = 'key='.$cfg['key'].'&method=base64'
						.'&body='.urlencode(base64_encode(file_get_contents($params['imageUrl'])));

					$content = $sender->send($url, $postData);

					if(preg_match('!OK\|(\d+)!', $content, $res))
					{
						return $res[1];
					}
					else
						self::$error = 'не получен id капчи: '.$content.' (httpCode = '.$sender->info['httpCode'][0].')';

				}
			}
			elseif($params['step'] == 'get')
			{
				if(!$params['captchaId'])
					self::$error = 'не указан $params[\'captchaId\']';

				$url = str_replace(array('{key}', '{captchaId}'), array($cfg['key'], $params['captchaId']), $cfg['urlOut']);

				$content = $sender->send($url);

				if(preg_match('!OK\|(.+)!', $content, $res))
				{
					return $res[1];
				}
				elseif(strpos($content, 'CAPCHA_NOT_READY')!==false)
					self::$error = self::ANTICAPTCHA_NOT_READY;
				else
					self::$error = 'ошибка получения капчи: '.$content;
			}
		}
		else
			self::$error = 'неверный $type';

		return false;
	}

	/**
	 * todo: добавить выбор метода шифрования
	 * сделать $iv  - 32 байта(можно брать md5 от ключа)
	 */
	public static function cryptText($text, $key)
	{
		//Создание вектора инициализации для дополнительной безопасности
		$encrypted = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $text, MCRYPT_MODE_ECB);
		return base64_encode($encrypted);

	}

	/**
	 * $text - base64
	 */
	public static function decryptText($text, $key)
	{
		$data = base64_decode($text);
		$result = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $data, MCRYPT_MODE_ECB);

		return rtrim($result, "\0");	//удаление пустых символов справа(заполняет до длины блока - 32)
	}

	/**
	 * возвращает время с точностью до 10000 доли секунды
	 * @return int
	 */
	public static function microtime()
	{
		return intval(microtime(true)*10000);
	}

	/**
	 * возвращает дату из микротайм
	 * @return string
	 */
	public static function microtimeDate($microtime, $format = 'd.m.Y H:i:s')
	{
		$time = floor($microtime/10000);
		return date($format, $time);
	}

	public static function cryptTextSsl($text, $key)
	{
		$result = '';

		$pk  = openssl_get_publickey($key);
		openssl_public_encrypt($text, $result, $pk );

		return base64_encode($result); //преобразует бинарные данные в текст
	}

	/**
	 * $text - base64
	 */
	public static function decryptTextSsl($text, $key)
	{
		$result = '';

		$pk  = openssl_get_privatekey($key);
		openssl_private_decrypt(base64_decode($text), $result, $pk );

		return rtrim($result, "\0");	//удаление пустых символов справа(заполняет до длины блока - 32)
	}

	/**
	 * нагрузка на систему текущая
	 * @return string
	 */
	public static function getSysLoad()
	{
		$process = sys_getloadavg();
		return formatAmount($process[2], 2);
	}

	/**
	 * процент загруженности inode
	 * @return int процент 0 - 100
	 */
	public static function getSysInode()
	{
		$response = `df -i`;

		if(preg_match("!(\d+)\% /!", $response, $res))
			return $res[1];
	}

	/**
	 * @param int $timestamp если 0 то текущий момент
	 * @return int timestamp начала дня $timestamp
	 */
	public static function startOfDay($timestamp = 0)
	{
		if(!$timestamp)
			$timestamp = time();

		return strtotime(date('d.m.Y', $timestamp));
	}

	public static function getClientIp()
	{
		if($_SERVER['HTTP_CF_CONNECTING_IP'])	//cloudflare
			return $_SERVER['HTTP_CF_CONNECTING_IP'];
		elseif($_SERVER['HTTP_X_FORWARDED_FOR'])	//nginx
		{
			//multi ip
			$ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
			return trim($ips[0]);
		}
		else
			return $_SERVER['REMOTE_ADDR'];
	}

	/**
	 * кол-во файлов и папок в $path (не рекурсивно)
	 * @param string $path
	 * @return int|false
	 */
	public static function filesCount($path)
	{
		$dir = opendir($path);

		if(!$dir)
			return false;

		$count = 0;

		while($file = readdir($dir))
		{
			if($file == '.' or $file == '..')
				continue;

			$count++;
		}

		closedir($dir);

		return $count;
	}

	//detect if client IP is admin IP
	/**
	 * @return bool
	 */
	public static function isAdminIp()
	{
		$ip = self::getClientIp();

		if(in_array($ip, cfg('admin_addr_arr')) !== false)
			return true;
		else
			return false;
	}

	/**
	 * определяет, запущен ли проект на локалхосте
	 * если true то эмуляция внешних запросов вместо отправки
	 * @return bool
	 */
	public static function isLocalhost()
	{
		return ($_SERVER['SERVER_ADDR'] == '::1');
	}

	/**
	 * распознать капчу
	 * похож на self::anticaptcha но проще
	 * todo: доделать и протестить с $type == 'recaptcha'
	 * @param array $params возможные
	 * 		параметры['imageContent'=>'', 'googleApiKey'=>'', 'pageUrl'=>'',... ]
	 * @return string|bool
	 */
	public static function recognize($params = [])
	{
		$cfg = cfg('recaptcha');
		/*
			[
				'urlIn'=>'http://rucaptcha.com/in.php',
				'urlOut'=>'http://rucaptcha.com/res.php?key={recaptchaKey}&action=get&id={recaptchaId}', //recaptchaId - id конкретной капчи в рекапче
				'maxTimeDefault' => 120,
				'sleepTime' => 5,
			]
		*/

		$startTime = time();

		$sender = new Sender;
		$sender->followLocation = false;
		$sender->timeout = 20;
		$sender->pause = 0;

		if($params['googleApiKey'])
		{
//			if($params['step'] == 'send')
//			{
//				if(!$params['googleApiKey'])
//					self::$error = 'не указан $params[\'googleApiKey\']';
//
//				if(!$params['pageUrl'])
//					self::$error = 'не указан $params[\'pageUrl\']';
//
//				if(!self::$error)
//				{
//					$url = $cfg['urlIn'];
//
//					$postData = 'key='.$cfg['key'].'&method=userrecaptcha'
//						.'&googlekey='.$params['googleApiKey'].'&pageurl='.$params['pageUrl'];
//
//					$content = $sender->send($url, $postData);
//
//					if(preg_match('!OK\|(\d+)!', $content, $res))
//					{
//						return $res[1];
//					}
//					else
//						self::$error = 'не получен id капчи: '.$content.' (httpCode = '.$sender->info['httpCode'][0].')';
//
//				}
//			}
//			elseif($params['step'] == 'get')
//			{
//				if(!$params['captchaId'])
//					self::$error = 'не указан $params[\'captchaId\']';
//
//				$url = str_replace(array('{key}', '{captchaId}'), array($cfg['key'], $params['captchaId']), $cfg['urlOut']);
//
//				$content = $sender->send($url);
//
//				if(preg_match('!OK\|(.+)!', $content, $res))
//				{
//					return $res[1];
//				}
//				elseif(strpos($content, 'CAPCHA_NOT_READY')!==false)
//					self::$error = self::ANTICAPTCHA_NOT_READY;
//				else
//					self::$error = 'ошибка получения капчи: '.$content;
//			}
		}
		elseif($params['imageContent'])
		{
			$url = $cfg['urlIn'];

			$postData = 'key='.$cfg['key'].'&method=base64'
				.'&body='.urlencode(base64_encode($params['imageContent']));

			$content = $sender->send($url, $postData);

			if(preg_match('!OK\|(\d+)!', $content, $res))
			{
				$captchaId = $res[1];

				//первоначальный сон
				sleep(20);

				$url = str_replace(['{key}', '{captchaId}'], [$cfg['key'], $captchaId], $cfg['urlOut']);

				self::$error = 'captcha not ready';

				while(time() - $startTime < $cfg['maxTimeDefault'])
				{
					$content = $sender->send($url);

					if(preg_match('!OK\|(.+)!', $content, $res))
					{
						self::$error = '';
						return $res[1];
					}
					//тут не ошибка, действительно пропущена T
					elseif(strpos($content, 'CAPCHA_NOT_READY')===false)
					{
						//если не ожидание капчи то ошибка
						self::$error = 'error captcha content:'.$content.'|'.__LINE__;
						return false;
					}
					//во всех остальных случаях будет ожидание, ничего не делаем

					sleep($cfg['sleepTime']);
				}
			}
			else
			{
				self::$error = 'не получен captchaId: '.$content.' (httpCode = '.$sender->info['httpCode'][0].')';
				return false;
			}
		}
		else
			self::$error = 'неверный тип капчи';

		return false;
	}

	/**
	 *
	 * @param int $timestamp если 0 то текущий момент
	 * @return int timestamp начала месяца $timestamp
	 */
	public static function startOfMonth($timestamp = 0)
	{
		if(!$timestamp)
			$timestamp = time();

		return strtotime(date('01.m.Y', $timestamp));
	}

	/**
	 * список активных потоков по регулярке
	 * @param string $regExp
	 * @return array
	 */
	public static function getThreads($regExp)
	{
		$result = [];

		clearstatcache();

		$files = self::dirFiles(cfg('threader_dir'));

		foreach($files as $file)
		{
			$fileName = basename($file);

			if(preg_match($regExp, $fileName))
				$result[] = str_replace('.txt', '', $fileName);
		}

		return $result;

	}

	public static function execJs($jsContent)
	{
		set_time_limit(30);

		$tmpDir = DIR_ROOT.'protected/runtime/jsExec';
		$tmpFile = $tmpDir.'/'.md5($jsContent).rand(1, 1000).'.js';

		if(file_put_contents($tmpFile, $jsContent))
		{
			register_shutdown_function(function($tmpFile){
				unlink($tmpFile);
			}, $tmpFile);

			return exec('phantomjs '.$tmpFile);
		}
		else
			return false;
	}
}
