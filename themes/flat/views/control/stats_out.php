<?$this->title = 'Оплаты с панели';?>


<h1><?=$this->title?></h1>


<p>
<form method="post">
	с <input type="text" name="params[date_from]" value="<?=$params['date_from']?>" />
	до <input type="text" name="params[date_to]" value="<?=$params['date_to']?>" />
	<input  type="submit" name="stats" value="Показать"/>
</form>
</p>

<p>
<form method="post" style="display: inline">
	<input type="hidden" name="params[date_from]" value="<?=date('d.m.Y')?>" />
	<input type="hidden" name="params[date_to]" value="<?=date('d.m.Y', time()+24*3600)?>" />
	<input  type="submit" name="stats" value="За сегодня"/>
</form>

&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

<form method="post" style="display: inline">
	<input type="hidden" name="params[date_from]" value="<?=date('d.m.Y', time()-24*3600)?>" />
	<input type="hidden" name="params[date_to]" value="<?=date('d.m.Y')?>" />
	<input  type="submit" name="stats" value="За вчера"/>
</form>

<?
//текущая неделю (неделя начинается с субботы 5 утра)
$curWeekEnd = 0;
$curDayStart = strtotime(date('d.m.Y'));

//если сегодня суббота чтобы считал правильно
if(date('w')==6 and $curDayStart > time())
	$curDayStart += 3600*24;
elseif(date('w')==6 and $curDayStart < time())
	$curDayStart -= 3600*24;

for($i = $curDayStart;$i<=$curDayStart+3600*24*7 ; $i += 3600*24)
{
	if(date('w', $i)==6)
	{
		$curWeekEnd = $i;
		break;
	}
}

$curWeekStart = $curWeekEnd - 3600*24*7;
?>

&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

<form method="post" style="display: inline">
	<input type="hidden" name="params[date_from]" value="<?=date('d.m.Y', $curWeekStart)?>" />
	<input type="hidden" name="params[date_to]" value="<?=date('d.m.Y', $curWeekEnd)?>" />
	<input  type="submit" name="stats" value="За неделю"/>
</form>

&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

<form method="post" style="display: inline">
	<input type="hidden" name="params[date_from]" value="<?=date('d.m.Y', time()-24*30*3600)?>" />
	<input type="hidden" name="params[date_to]" value="<?=date('d.m.Y', time()+24*3600)?>" />
	<input  type="submit" name="stats" value="За месяц"/>
</form>

</p>

<?if($stats['models']){?>

	<p><strong>Всего отправлено: </strong><?=formatAmount($stats['allAmount'], 0)?></p>

	<table class="std padding">

		<tr>
			<td>ID</td>
			<td>Сумма</td>
			<td>Кому</td>
			<td>Статус</td>
			<td>Дата</td>
		</tr>

		<?foreach($stats['models'] as $model){?>
			<tr>
				<td><?=$model->qiwi_id?></td>
				<td><?=$model->amountStr?></td>
				<td><?=$model->wallet?></td>
				<td><?=$model->statusStr?></td>
				<td><?=$model->dateAddStr?></td>
			</tr>
		<?}?>
	</table>

<?}else{?>
	нет данных за выбранный период
<?}?>


