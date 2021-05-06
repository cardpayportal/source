<?
/**
 * @var StoreApiWithdraw[] $models
 *
 */
?>

<table class="std padding">
	<tr>
		<td>Руб</td>
		<td>BTC</td>
		<td>USD</td>
		<td>Адрес</td>
		<td>Last Price<br> USD</td>
		<td>Курс<br> USD</td>
		<td>Комиссия<br> BTC</td>
		<td>Оплачен</td>
	</tr>

	<?foreach($models as $model){?>
		<tr>
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
