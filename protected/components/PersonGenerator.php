<?php

class PersonGenerator
{

	/**
	 * $result = array(
	 * 	'second_name'=>'Фамилия',
	 * 	'first_name'=>'Имя',
	 * 	'third_name'=>'Отчество',
	 * 	'birth'=>'01.12.1960',
	 * 	'passport'=>'1234567899',	//серияНомер
	 * 	'inn'=>'234567891234',	//12 цифр
	 *  'issue'=>'01.12.1975', //дата выдачи паспорта (через 15 лет после рождения)
	 * )
	 */
	public static function newPerson()
	{
		$result = array();

		$dir = dirname(__FILE__).'/'.__CLASS__.'/';

		$firstFile = $dir.'first_names.txt';
		$secondFile = $dir.'second_names.txt';
		$thirdFile = $dir.'third_names.txt';

		$secondNames = self::nameArr($secondFile);
		$firstNames = self::nameArr($firstFile);
		$thirdNames = self::nameArr($thirdFile);

		$result['second_name'] =  $secondNames[array_rand($secondNames)];
		$result['first_name'] =  $firstNames[array_rand($firstNames)];
		$result['third_name'] =  $thirdNames[array_rand($thirdNames)];

		//дата рождения
		$day = rand(1,28);

		if($day < 10)
			$day = '0'.$day;

		$month = rand(1,12);

		if($month < 10)
			$month = '0'.$month;

		$year = rand(date('Y')-44, date('Y')-21);	//только совершеннолетние

		$result['birth'] = $day.'.'.$month.'.'.$year;

		//дата выдачи
		$day = rand(1,28);

		if($day < 10)
			$day = '0'.$day;

		$month = rand(1,12);

		if($month < 10)
			$month = '0'.$month;

		if(date('Y') - $year > 45) //дата выдачи должна быть спустя 20 или 45 лет с даты рождения
			$year = $year+46;
		else
			$year = $year+21;

		//яндекс не любит даты выдачи до 2000
		if($year < 2000)
			$year = rand(2000, date('Y')-2);

		$result['issue'] = $day.'.'.$month.'.'.$year;

		// XXYY ZZZZZZ (XX - код региона ОКАТО, YY - год выдачи, ZZZZ - порядковый номер)
		$result['passport'] = rand(11, 99).substr($year, 2, 2).rand(111111, 999999);
		$result['inn'] = self::newInn();



		return $result;
	}

	public static function newInn()
	{
		$n = self::randomNumber(10);
		$n[] = (($n[0]*7+$n[1]*2+$n[2]*4+$n[3]*10+$n[4]*3 +$n[5]*5+$n[6]*9+$n[7]*4+$n[8]*6+$n[9]*8)%11)%10;
		$n[] = (($n[0]*3+$n[1]*7+$n[2]*2+$n[3]*4 +$n[4]*10+$n[5]*3+$n[6]*5+$n[7]*9+$n[8]*4+$n[9]*6+$n[10]*8)%11)%10;
		return implode($n);
	}




	private static function randomNumber($length=1)
	{
		$number = array();

		for($i=0; $i < $length; $i++)
			$number[] = mt_rand(0,9);

		return $number;
	}

	/**
	 * преобразует список в файле в массив построчно
	 */
	public static function nameArr($file)
	{
		$result = array();

		$content = file_get_contents($file);

		$sep = Tools::getSep($content);

		$rows = explode($sep, $content);

		foreach($rows as $row)
		{
			$row = trim($row);

			if(empty($row))
				continue;

			$result[] = Tools::ucfirst($row);
		}

		return $result;
	}


	public static function newSnils()
	{
		$result = array();

		for($i=1; $i<=3; $i++)
		{
			$arr = array();

			for($j=1; $j<=3; $j++)
				$arr[] = rand(0,9);

			if($arr[0] == $arr[1] and $arr[0] == $arr[2])
				$arr = array($i+1, $i+2, $i+3);

			$result = array_merge($result, $arr);
		}

		$controlSum = 0;

		foreach($result as $num=>$val)
			$controlSum += $val*(count($result) - $num);


		if($controlSum < 100)
		{
			$controlSum = "$controlSum";
		}
		elseif($controlSum > 99 and $controlSum < 102)
		{
			$controlSum = "00";
		}
		elseif($controlSum >= 102)
		{
			$controlSum = $controlSum%101;

			if($controlSum == 100)
				$controlSum = '00';
			else
				$controlSum = "$controlSum";

			if(strlen($controlSum)<2)
				$controlSum = '0'.$controlSum;

		}

		$result[] = $controlSum[0];
		$result[] = $controlSum[1];

		return implode('', $result);
	}
}