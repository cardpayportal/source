<?php
/**
 * @var ControlController $this
 * @var array $params
 * @var ClientCalc $model
 * @var float $btcRateBitfinex
 */
$this->title = 'Редактировать расчет '.$model->id;
?>

<form method="post" class="form-vertical form-bordered">
	<?if($model->client->calc_mode == Client::CALC_MODE_ORDER){?>
	<div id="calcForm">
	<?}?>
		<div class="box box-bordered">
			<div class="box-title">
				<h3><i class="fa fa-bars"></i>Редактировать расчет</h3>
			</div>
			<div class="box-content nopadding">

					<div class="form-group">
						<label for="btcRate" class="control-label">Курс BTC</label>
						<input type="text" name="params[btc_rate]" id="btcRate"
							   value="<?=($params['btc_rate']) ? $params['btc_rate'] : $model->btc_rate ?>"
							   class="form-control">
						<span class="help-block">
							<?$rate = ClientCalc::getCurrentBtcUsdRateSource()?>
							BTC_USD: <?=$rate['value']?> (<?=$rate['name']?>)
						</span>
					</div>

					<div class="form-group">

						<?if($model->ltc_address){?>
							<p style="color: red; font-weight: bold">пересчитать сумму в LTC</p>
						<?}?>

						<label for="btcAmount" class="control-label">Сумма BTC</label>
						<input type="text" name="params[amount_btc]" readonly="readonly"
							   class="click2select form-control"
							   value="<?=$model->amount_btc?>" id="btcAmount">
					</div>

					<?if($model->ltc_address){?>

						<div class="form-group">
							<p style="color: red; font-weight: bold">расчет в  LTC</p>
							<label for="field1" class="control-label">Адрес LTC</label>
							<input type="text" class="click2select form-control" readonly="readonly"
								   value="<?=$model->ltc_address?>" id="field1">
						</div>
					<?}else{?>
						<div class="form-group">
							<label for="field11" class="control-label">Адрес BTC</label>
							<input type="text" class="click2select form-control" readonly="readonly"
								   value="<?=$model->btc_address?>" id="field11">
						</div>
					<?}?>

					<div class="form-group">
						<label for="field2" class="control-label">Комментарий</label>
						<textarea name="params[comment]" rows="5" cols="45"
								  class="form-control" id="field2"><?=$params['comment']?></textarea>
					</div>

					<div class="form-group">
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
					</div>


					<div class="form-actions">
						<button type="submit" class="btn btn-primary" name="save" value="Я оплатил">Я оплатил</button>
						<a href="<?=url('control/calculateClientList')?>"><button type="button" class="btn">Отмена</button></a>
					</div>
			</div>
		</div>
	<?if($model->client->calc_mode == Client::CALC_MODE_ORDER){?>
	</div>
	<?}?>

	<input type="hidden" id="amountUsd" value="<?=$model->amount_usd?>">
	<input type="hidden" id="currentRateBtc" value="<?=$btcRateBitfinex?>">

	<?if($model->client->calc_mode == Client::CALC_MODE_ORDER){?>
		<div id="orderList">
			<div class="box box-bordered">
				<div class="box-title">
					<h3><i class="fa fa-bars"></i>Неоплаченные заявки</h3>
				</div>
				<div class="box-content nopadding">
					<div id="orderListContentGf">
						<?foreach($model->orders as $order){?>
							<div class="form-group">
								<b>Заявка #<?=$order->id?> (<?=$order->dateAddStr?> - <?=$order->dateEndStr?>)</b>
								<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
								<?=$order->user->name?>
								<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
								Принято: <?=formatAmount($order->amountIn)?> из <?=formatAmount($order->amount_add)?>
							</div>
						<?}?>
					</div>
				</div>
			</div>
		</div>
	<?}?>

</form>

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