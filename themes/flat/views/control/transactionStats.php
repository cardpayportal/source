<?
/* @var $params array */
/* @var $stats array */
?>

<?$this->title = 'Статистика переводов'?>

<h1><?=$this->title?></h1>

<p>
	<form method="post">

		<select name="params[clientId]">
			<option value=""></option>
				<?foreach(Client::getArr() as $id=>$name){?>
					<option value="<?=$id?>"
						<?if($params['clientId']==$id){?>
							selected="selected"
						<?}?>
					><?=$name?></option>
				<?}?>
			</select>

		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

		с <input type="text" name="params[date_from]" value="<?=$params['date_from']?>" />
		до <input type="text" name="params[date_to]" value="<?=$params['date_to']?>" />
		<input  type="submit" name="stats" value="Показать"/>
	</form>
</p>


<p>
	<form method="post" style="display: inline">
		<input type="hidden" name="params[date_from]" value="<?=date('d.m.Y')?>" />
		<input type="hidden" name="params[date_to]" value="<?=date('d.m.Y', time()+24*3600)?>" />
		<input type="hidden" name="params[clientId]" value="<?=$params['clientId']?>" />
		<input  type="submit" name="stats" value="За сегодня"/>
	</form>

	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

	<form method="post" style="display: inline">
		<input type="hidden" name="params[date_from]" value="<?=date('d.m.Y', time()-24*3600)?>" />
		<input type="hidden" name="params[date_to]" value="<?=date('d.m.Y')?>" />
		<input type="hidden" name="params[clientId]" value="<?=$params['clientId']?>" />
		<input  type="submit" name="stats" value="За вчера"/>
	</form>
</p>

<br>
<br>

<?
if(
	//$stats['amountInIn'] //$stats['amountInIn'] = array('in'=>230, 'out'=>230) если были платежи на входящих
	//or $stats['amountTransit'] //сколько приходило на входящие в этот день
	//or $stats['amountOut'] //сколько приходило на входящие в этот день
	$stats['transactions'] //если в этот день были какие то платежи-активность(неважно успешные или ошибочные)
)
{?>

<h2>Суммы</h2>

<table class="std padding">
	<tr>
		<td>In</td>
		<td>Transit</td>
		<td>Out</td>
	</tr>

	<tr>
		<td style="text-align: left">
			Пришло: <?=formatAmount($stats['in']['amount']['in'], 0)?><br>
			Ушло: <?=formatAmount($stats['in']['amount']['out'], 0)?>
			<?if($stats['in']['amount']['commissionAmount']){?>
				<span title="комиссия" class="error withComment">(-<?=formatAmount($stats['in']['amount']['commissionAmount'])?>)</span>
			<?}?>
		</td>
		<td style="text-align: left">
			Пришло: <?=formatAmount($stats['transit']['amount']['in'], 0)?><br>
			Ушло: <?=formatAmount($stats['transit']['amount']['out'], 0)?>
			<?if($stats['transit']['amount']['commissionAmount']){?>
				<span title="комиссия" class="error withComment">(-<?=formatAmount($stats['transit']['amount']['commissionAmount'])?>)</span>
			<?}?>
		</td>

		<td style="text-align: left">
			Пришло: <?=formatAmount($stats['out']['amount']['in'], 0)?><br>
			Ушло: <?=formatAmount($stats['out']['amount']['out'], 0)?>
			<?if($stats['out']['amount']['commissionAmount']){?>
				<span title="комиссия" class="error withComment">(-<?=formatAmount($stats['out']['amount']['commissionAmount'])?>)</span>
			<?}?>
		</td>
	</tr>

</table>


<?}else{?>
	платежей не найдено
<?}?>