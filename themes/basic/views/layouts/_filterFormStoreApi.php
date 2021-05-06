<?
/**
 * @var array $filter
 */

$day = 3600*24;

$today = array(
	'timestampStart'=>strtotime(date('d.m.Y')),
	'timestampEnd'=>strtotime(date('d.m.Y')) + $day,
);

$yesterday = array(
	'timestampStart'=>$today['timestampStart'] - $day,
	'timestampEnd'=>$today['timestampStart'],
);

$week = array(
	'timestampStart'=>$today['timestampStart'] - 7 * $day,
	'timestampEnd'=>$today['timestampStart'],
);

$month = array(
	'timestampStart'=>$today['timestampStart'] - 30 * $day,
	'timestampEnd'=>$today['timestampStart'],
);

?>
<form method="post" class="inline">
	<p>
		<b>С:</b> <input type="text" name="filter[dateStart]" value="<?=$filter['dateStart']?>" />
		<b>По:</b> <input type="text" name="filter[dateEnd]" value="<?=$filter['dateEnd']?>" />
		<input  type="submit" name="submit" value="Фильтровать"/>
	</p>
</form>

<form method="post" class="inline">
	<input type="hidden" name="filter[dateStart]" value="<?=date(cfg('dateFormatExt1'), $today['timestampStart'])?>" />
	<input type="hidden" name="filter[dateEnd]" value="<?=date(cfg('dateFormatExt1'), $today['timestampEnd'])?>" />
	<input type="hidden" name="filter[storeId]" value="<?=$filter['storeId']?>" />
	<input  type="submit" name="submit" value="Сегодня"/>
</form>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

<form method="post" class="inline">
	<input type="hidden" name="filter[dateStart]" value="<?=date(cfg('dateFormatExt1'), $yesterday['timestampStart'])?>" />
	<input type="hidden" name="filter[dateEnd]" value="<?=date(cfg('dateFormatExt1'), $yesterday['timestampEnd'])?>" />
	<input type="hidden" name="filter[storeId]" value="<?=$filter['storeId']?>" />
	<input  type="submit" name="submit" value="Вчера"/>
</form>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

<form method="post" class="inline">
	<input type="hidden" name="filter[dateStart]" value="<?=date(cfg('dateFormatExt1'), $week['timestampStart'])?>" />
	<input type="hidden" name="filter[dateEnd]" value="<?=date(cfg('dateFormatExt1'), $week['timestampEnd'])?>" />
	<input type="hidden" name="filter[storeId]" value="<?=$filter['storeId']?>" />
	<input  type="submit" name="submit" value="Неделя"/>
</form>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

<form method="post" class="inline">
	<input type="hidden" name="filter[dateStart]" value="<?=date(cfg('dateFormatExt1'), $month['timestampStart'])?>" />
	<input type="hidden" name="filter[dateEnd]" value="<?=date(cfg('dateFormatExt1'), $month['timestampEnd'])?>" />
	<input type="hidden" name="filter[storeId]" value="<?=$filter['storeId']?>" />
	<input  type="submit" name="submit" value="Месяц"/>
</form>
