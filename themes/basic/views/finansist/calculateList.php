<?
/**
 * @var FinansistController $this
 * @var ClientCalc[] $models
 * @var array $calcParams
 * @var array $params
 * @var ManagerOrder[] $orders
 * @var Client $client
 */

$this->title = 'Расчеты';
?>

<h2>Создание нового расчета</h2>

<?if($client->calc_mode == Client::CALC_MODE_ORDER){?>
	<p>Выберите заявки, сумма будет рассчитана автоматически.</p>
<?}?>

<form method="post">
	<div id="calcForm">
		<p>
			<label>
				<b>Сумма</b>
					<?if($client->calc_mode == Client::CALC_MODE_AMOUNT){?>
						(макс <?=formatAmount($calcParams['amount_rub'], 0)?> руб
						с <?=$calcParams['dateStart']?>)
					<?}?>
				<br>
				<input type="text" name="params[amount_rub]"
					   id="amountRub"
					<?if($client->calc_mode == Client::CALC_MODE_ORDER){?>
						readonly="readonly"
						value="0"
					<?}else{?>
						value="<?=($params['amount_rub']) ? $params['amount_rub'] : $calcParams['amount_rub']?>"
					<?}?>
				> руб
			</label>
			<br>&nbsp;<span id="amountUsd" style="font-weight: bold"><?=$calcParams['amount_usd']?></span> USD
			<br>&nbsp;<span id="amountBtc" style="font-weight: bold"><?=$calcParams['amountBtc']?></span> BTC
			<br>(возможны отклонения, в зависимости от курса на момент оплаты)
		</p>

		<p>
			<label>
				<b>Адрес BTC</b><br>
				<input type="text" name="params[btc_address]" value="<?=$params['btc_address']?>" size="45">
			</label>
		</p>

		<p>
			<label>
				<b>Адрес LTC</b><br>
				<input type="text" name="params[ltc_address]" value="<?=$params['ltc_address']?>" size="45">
			</label>
		</p>

		<p>
			<label>
				<b>Платежный пароль</b><br>
				<input type="password" name="params[extra]" value="<?=$params['extra']?>">
			</label>
		</p>

		<p>
			<label>
				Комментарий<br>
				<textarea cols="45" rows="3" name="params[client_comment]"><?=$params['client_comment']?></textarea>
			</label>
		</p>

		<p><input type="submit" name="add" value="Создать"></p>

		<input type="hidden" name="params[date_add]" value="<?=time()?>">


		<input type="hidden" id="rateUsd" value="<?=$calcParams['rateUsd']?>">
		<input type="hidden" id="rateBtc" value="<?=$calcParams['rateBtc']?>">
	</div>


	<?if($client->calc_mode == Client::CALC_MODE_ORDER){?>
		<div id="orderList">
			<?foreach($orders as $order){?>
				<p>
					<input type="checkbox" name="params[orderIds][<?=$order->id?>]" value="<?=$order->amountIn?>"/>
					<b>Заявка #<?=$order->id?> (<?=$order->dateAddStr?> - <?=$order->dateEndStr?>)</b>
					<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<?=$order->user->name?>
					<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					Принято: <?=formatAmount($order->amountIn)?> из <?=formatAmount($order->amount_add)?>
				</p>
			<?}?>
		</div>
	<?}?>
</form>

<div class="clear"></div>

<script>
	$(document).ready(function(){

		<?//расчеты?>
		var rateUsd = $('#rateUsd').val();
		var rateBtc = $('#rateBtc').val();

		$('#amountRub').blur(function(){
			$('#amountUsd').text(($(this).val() / rateUsd).toFixed(2));
			$('#amountBtc').text(($(this).val() / rateUsd / rateBtc).toFixed(8));
		});

		<?if($client->calc_mode == Client::CALC_MODE_ORDER){?>
			$('#orderList input[type=checkbox]').checked = false;

			$('#orderList input[type=checkbox]').click(function(){
				if($(this).prop('checked'))
					$('#amountRub').val($('#amountRub').val()*1 + $(this).val()*1);
				else
					$('#amountRub').val($('#amountRub').val()*1 - $(this).val()*1);

				$('#amountRub').blur();
			});
		<?}?>
	});
</script>

<br><hr>

<h1><?=$this->title?></h1>


<?if($models){?>
	<table class="std padding" style="width: 700px">
		<tr>
			<td>ID</td>
			<td>Сумма</td>
			<td>Примечание</td>
			<td>BTC адрес</td>
			<?if($client->calc_mode == Client::CALC_MODE_ORDER){?>
				<td>Заявки</td>
			<?}?>
			<td>Статус</td>
			<td>Добавлен</td>
		</tr>

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
				<?}?>
			>
				<td><?=$model->id?></td>
				<td>
					<nobr><b><?=formatAmount($model->amount_rub, 0)?> RUB</b></nobr><br>
					<nobr><?=formatAmount($model->amount_usd, 2)?> USD</nobr><br>

					<?if($model->amount_btc > 0){?>
						<nobr><?=formatAmount($model->amount_btc, 8)?> BTC</nobr><br>
						(курс: <?=formatAmount($model->btc_rate)?>)
					<?}?>
				</td>
				<td <?if($model->client_comment){?>style="text-align: left"<?}?>>
					<?if($model->client_comment){?>
						<b>Оператор:</b><br>
					<?}?>
					<?=$model->comment?>
					<?if($model->client_comment){?>
						<br>
						<b>Я:</b><br>
						<?=htmlspecialchars($model->client_comment)?>
					<?}?>
				</td>
				<td><span class="dotted" title="<?=($model->ltc_address) ? $model->ltc_address : $model->btc_address?>">
						<?=$model->addressShort?>
					</span>
				</td>
				<?if($client->calc_mode == Client::CALC_MODE_ORDER){?>
					<td>
						<?foreach($model->orders as $order){?>
							#<?=$order->id?><br>
						<?}?>
					</td>
				<?}?>

				<td><?=$model->statusStr?></td>
				<td><?=$model->dateAddStr?></td>
			</tr>
		<?}?>
	</table>
<?}else{?>
	расчетов не найдено
<?}?>
<script>
	setTimeout(function(){
		window.location.reload(1);
	}, 600000);
</script>