<?
/**
 * @var ControlController $this
 * @var ClientCalc[] $models
 */
?>

<table class="table table-bordered table-colored-header">
	<thead>
		<th>ID</th>
		<th>Клиент</th>
		<th>Сумма</th>
		<th>Примечание</th>
		<th>Добавлен</th>
		<th>Автор</th>
		<th>Долг</th>
		<th>Приход</th>
		<th>Статус</th>
		<th>Действие</th>
	</thead>

	<?
	//для вывода кнопки удаления у последнего расчета клиента
	$uniqueClients = array();
	?>

	<tbody>
		<?foreach($models as $model){?>
			<tr
				<?if($model->status == ClientCalc::STATUS_NEW){?>
					class="wait"
					title="новый расчет"
				<?}elseif($model->status == ClientCalc::STATUS_WAIT){?>
					class="wait"
					title="в процессе"
				<?}elseif($model->status == ClientCalc::STATUS_DONE){?>
					class="success"
					title="оплачен"
				<?}elseif($model->status == ClientCalc::STATUS_CANCEL){?>
					class="error"
					title="отменен"
				<?}elseif($model->is_control){?>
					class="selected"
					title="контрольный"
				<?}?>
			>
				<td><?=$model->id?></td>
				<td><?=$model->client->name?></td>
				<td>
					<nobr><b><?=formatAmount($model->amount_rub, 0)?> RUB</b></nobr><br>
					<nobr><?=formatAmount($model->amount_usd, 2)?> USD</nobr><br>

					<?if($model->amount_btc > 0){?>
						<nobr><?=formatAmount($model->amount_btc, 8)?> BTC</nobr><br>
						(курс: <?=formatAmount($model->btc_rate)?>)
					<?}?>
				</td>
				<td <?if($model->client_comment){?>style="text-align: left"<?}?>>
					<?if($model->comment){?>
						<b>Оператор:</b><br>
					<?}?>
					<?=$model->comment?>
					<?if($model->client_comment){?>
						<br>
						<b>Клиент:</b><br>
						<?=htmlspecialchars($model->client_comment)?>
					<?}?>
				</td>
				<td><?=$model->dateAddStr?></td>
				<td><?=$model->user->name?></td>
				<td><nobr><b><?=$model->debtRubStr?></b></nobr><br> руб</td>
				<td><?/*<nobr><?=$model->statsInStr?></nobr>*/?></td>
				<td>
					<?=$model->statusStr?>
					<?if($model->is_control and ($this->isGlobalFin() or $this->isAdmin())){?>
						<br><b>контрольный</b>
					<?}?>
				</td>
				<td>
					<?if(in_array($model->client->id, $uniqueClients)===false){//отменить только последний расчет?>

						<?$uniqueClients[] = $model->client->id;?>

						<?if($model->status != $model::STATUS_DONE or (!$model->btc_address and !$model->ltc_address)){?>
							<a href="<?=url('control/CalculateClientList', ['clientId'=>$model->client->id, 'action'=>'delete'])?>">
								<button class="btn btn-small btn-danger" type="button">удалить</button>
							</a>
							<br>
						<?}?>

						<?if(!in_array($model->status, [$model::STATUS_CANCEL, $model::STATUS_DONE]) or (!$model->btc_address and !$model->ltc_address)){?>
							<a href="<?=url('control/CalculateClientList', ['clientId'=>$model->client->id, 'action'=>'cancel'])?>">
								<button class="btn btn-small btn-warning" type="button">Отменить</button>
							</a>
							<br><br>
						<?}?>
					<?}?>

					<?if(in_array($model->status, [ClientCalc::STATUS_NEW, ClientCalc::STATUS_WAIT])){?>
						<a href="<?=url('control/CalculateClientEdit', ['id'=>$model->id])?>">
							<button class="btn btn-small btn-success" type="button">К оплате</button>
						</a>
					<?}?>
				</td>
			</tr>
		<?}?>
	</tbody>
</table>