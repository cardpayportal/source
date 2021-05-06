<?
/**
 * @var StoreApiWithdraw[] $models
 *
 */
?>

<table class="table table-bordered table-colored-header">
	<thead>
		<th>ID</th>
		<th>Магазин</th>
		<th>Руб</th>
		<th>BTC</th>
		<th>USD</th>
		<th>Адрес</th>
		<th>Last Price<br> USD</th>
		<th>Курс<br> USD</th>
		<th>Комиссия<br> BTC</th>
		<th>Оплачен</th>
	</thead>

	<?foreach($models as $model){?>
		<tr>
			<td><a href="<?=url('control/storeApiTransactions', array('datePay'=>$model->date_pay))?>" title="список оплат"><?=$model->id?></a></td>
			<td><?=$model->store_id?></td>
			<td><nobr><?=formatAmount($model->amount_rub, 0)?></nobr></td>
			<td><nobr><?=formatAmount($model->amount_currency, 8)?></nobr></td>
			<td><nobr><?=formatAmount($model->amountUsd, 0)?></nobr></td>
			<td>
				<?=$model->wallet?>
				<?if($model->withdraw_id){?>
						<br><span <?=($model->isConfirmed === true) ? 'class="success dotted" title="подтвержден"' : (($model->isConfirmed === false) ? 'class="error dotted" title="не подтвержден"' : '' )?>">транзакция</span>
						(<span class="dotted" title="кол-во подтверждений сети"><a target="_blank" href="http://blockchain.info/tx/<?=$model->withdraw_id?>"><?=$model->confirmationsStr?></a></span>)
					<?}?>
			</td>
			<td><nobr><?=$model->btcLastPriceStr?></nobr></td>
			<td><nobr><?=$model->usdRateStr?></nobr></td>
			<td><nobr><?=formatAmount($model->network_fee, 6)?></nobr></td>
			<td><nobr><?=$model->datePayStr?></nobr></td>
		</tr>
	<?}?>
</table>
