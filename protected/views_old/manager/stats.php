<?
/**
 * @var ManagerController $this
 * @var array $params
 * @var float $allAmount
 * @var array $stats
 */

$this->title = 'Статистика';

?>


<h1><?=$this->title?></h1>



<form method="post">
	<p>
		с <input type="text" name="params[date_from]" value="<?=$params['date_from']?>" />
		до <input type="text" name="params[date_to]" value="<?=$params['date_to']?>" />
		<input  type="submit" name="stats" value="Показать"/>
	</p>
</form>

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

&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

<?
//текущая неделю (неделя начинается с субботы)
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

<form method="post" style="display: inline">
	<input type="hidden" name="params[date_from]" value="<?=date('d.m.Y', $curWeekStart)?>" />
	<input type="hidden" name="params[date_to]" value="<?=date('d.m.Y', $curWeekEnd)?>" />
	<input  type="submit" name="stats" value="За неделю"/>
</form>

<?if($this->isControl() or $this->isAdmin() or $this->isFinansist()){?>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<form method="post" style="display: inline">
		<input type="hidden" name="params[date_from]" value="<?=date('d.m.Y', time()-24*30*3600)?>" />
		<input type="hidden" name="params[date_to]" value="<?=date('d.m.Y', time()+24*3600)?>" />
		<input  type="submit" name="stats" value="За месяц"/>
	</form>
<?}?>


<p>
	<strong>Всего принято: </strong>
	<?=formatAmount($allAmount, 0)?>
</p>

<?if($stats){?>
	<table class="std padding">
		<?foreach($stats as $wallet=>$amount){?>
			<tr>
				<td><b><?=$wallet?></b></td>
				<td><?=formatAmount($amount, 2)?> руб</td>
			</tr>
		<?}?>
	</table>
<?}?>