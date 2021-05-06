<?php
/**
 * @var ControlController $this
 * @var array $params
 * @var ClientCalc $model
 * @var float $btcRateBitfinex
 */
$this->title = 'Редактировать расчет '.$model->id." ({$model->client->name})";
?>

<h1><?=$this->title?></h1>

<div id="calcForm">
<form method="post">
	<p>
		<label>
			Курс BTC<br>
			<input type="text" name="params[btc_rate]" id="btcRate"
				   value="<?=($params['btc_rate']) ? $params['btc_rate'] : $model->btc_rate ?>">
			<br>
			<i>Текущий курс: <?=$btcRateBitfinex?></i> (Bitfinex)
		</label>
	</p>

	<p>
		<label>
			Сумма BTC<br>

			<?if($model->ltc_address){?>
				<span style="font-weight: bold; color: red;">пересчитать сумму в LTC</span><br>
			<?}?>

			<input type="text" name="params[amount_btc]" readonly="readonly" class="click2select"
				   value="<?=$model->amount_btc?>" id="btcAmount"> BTC
		</label>
	</p>

	<?if($model->ltc_address){?>
		<p>
			<label>
				Адрес LTC<br>
				<span style="font-weight: bold; color: red;">расчет в LTC</span><br>
				<input type="text" class="click2select" readonly="readonly"
					   value="<?=$model->ltc_address?>" size="45">
			</label>
		</p>
	<?}else{?>
		<p>
			<label>
				Адрес BTC<br>
				<input type="text" class="click2select" readonly="readonly"
					   value="<?=$model->btc_address?>" size="45">
			</label>
		</p>
	<?}?>


	<p>
		<label>
			Комментарий<br>
			<textarea name="params[comment]" rows="5" cols="45"><?=$params['comment']?></textarea>
		</label>
	</p>

	<?if($model->client_comment){?>
		<p>
			Комментарий клиента:<br>
			<?=htmlspecialchars($model->client_comment)?>
		</p>
	<?}?>

	<p>
		<b><?=formatAmount($model->amount_rub, 0)?></b> RUB<br>
		<b><?=formatAmount($model->amount_usd, 2)?></b> USD<br>
		<b>Добавлен <?=$model->dateAddStr?></b>
	</p>

	<p><input type="submit" name="save" value="Я оплатил"></p>

</form>

<input type="hidden" id="amountUsd" value="<?=$model->amount_usd?>">
<input type="hidden" id="currentRateBtc" value="<?=$btcRateBitfinex?>">
</div>

<?if($model->client->calc_mode == Client::CALC_MODE_ORDER){?>
	<div id="orderList">
		<?foreach($model->orders as $order){?>
			<b>Заявка #<?=$order->id?> (<?=$order->dateAddStr?> - <?=$order->dateEndStr?>)</b>
			<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
			<?=$order->user->name?>
			<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
			Принято: <?=formatAmount($order->amountIn)?> из <?=formatAmount($order->amount_add)?>
		<?}?>
	</div>
<?}?>

<div class="clear"></div>

<script>
	$(document).ready(function(){

		var amountUsd = $('#amountUsd').val();
		var currentRateBtc = $('#currentRateBtc').val();

		$('#btcRate').blur(function(){
			$('#btcAmount').val((amountUsd / $(this).val()).toFixed(8));
		});
	});
</script>