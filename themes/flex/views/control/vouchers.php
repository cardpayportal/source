<?
/**
 * @var ControlController $this
 * @var array $params
 * @var array $interval
 * @var AccountVoucher[] $models
 */
$this->title = 'Ваучеры'

?>

<h1><?=$this->title?></h1>

<div>
	<form method="post">
	<br>
		<select name="params[clientId]">
			<option value="">Все</option>
				<?foreach(Client::getArr() as $id=>$name){?>
					<option value="<?=$id?>"
						<?if($params['clientId']==$id){?>
							selected="selected"
						<?}?>
					><?=$name?></option>
				<?}?>
		</select>

		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		с <input type="text" name="params[dateStart]" value="<?=$interval['dateStart']?>" />
		до <input type="text" name="params[dateEnd]" value="<?=$interval['dateEnd']?>" />
		<input  type="submit" name="stats" value="Показать"/>
	</form>
</div>
<br>

<div>
	<form method="post" style="display: inline">
		<input type="hidden" name="params[dateStart]" value="<?=date('d.m.Y')?>" />
		<input type="hidden" name="params[dateEnd]" value="<?=date('d.m.Y', time()+24*3600)?>" />
		<input type="hidden" name="params[clientId]" value="<?=$params['clientId']?>" />
		<input  type="submit" name="stats" value="За сегодня"/>
	</form>

	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

	<form method="post" style="display: inline">
		<input type="hidden" name="params[dateStart]" value="<?=date('d.m.Y', time()-24*3600)?>" />
		<input type="hidden" name="params[dateEnd]" value="<?=date('d.m.Y')?>" />
		<input type="hidden" name="params[clientId]" value="<?=$params['clientId']?>" />
		<input  type="submit" name="stats" value="За вчера"/>
	</form>
</div>
<br>
<br>

<?if($models){?>
	<table class="std padding">
		<thead>
			<th>Клиент</th>
			<th>Сумма</th>
			<th>Код</th>
			<th>Статус</th>
		</thead>

		<tbody>
			<?foreach($models as $model){?>
				<tr>
					<td><?=$model->client->name?></td>
					<td><?=formatAmount($model->amount, 0)?></td>
					<td><?=$model->code?></td>
					<td>
						<?if($model->date_activate){?>
							<span class="error">
								активирован <?=date('d.m.Y H:i', $model->date_activate)?>
							</span>
						<?}else{?>
							<span class="success">
								создан <?=date('d.m.Y H:i', $model->date_add)?>
							</span>
						<?}?>
					</td>
				</tr>
			<?}?>
		</tbody>
	</table>
<?}else{?>
	ваучеров не найдено
<?}?>


<br><br/>
<h2>Слить все в ваучеры</h2>
<form method="post">

	<?
		$allAmount = 0;
		foreach(Client::getActiveClients() as $client)
		{
			$sum = Client::getSumOutBalance($client->id, true);

			if($sum < 1000)
				continue;

			$allAmount += $sum;
		}
	?>

	<select name="params[clientId]">
		<option value="">Все <?=formatAmount($allAmount, 0)?> руб</option>
		<?foreach(Client::getActiveClients() as $client){?>
			<option value="<?=$client->id?>"><?=$client->name?>
				<?=formatAmount(Client::getSumOutBalance($client->id, true), 0)?> руб</option>
		<?}?>
	</select>

	<p>
		<textarea name="params[wallets]" cols="45" rows="10"><?=$params['wallets']?></textarea>
	</p>

	<p>
		<input type="submit" name="createVouchers" value="Слить с выбранных">
	</p>

</form>