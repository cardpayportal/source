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

<form method="post" class="form-vertical form-bordered">
<?if($client->calc_mode == Client::CALC_MODE_ORDER){?>
<div id="calcForm">
<?}?>
	<div class="box box-bordered">
		<div class="box-title">
			<h3><i class="fa fa-bars"></i>Новый расчет</h3>
		</div>
		<div class="box-content nopadding">
				<div class="form-group">
					<label for="amountRub" class="control-label">Сумма (руб)</label>
					<input type="text" name="params[amount_rub]"
						<?if($client->calc_mode == Client::CALC_MODE_ORDER){?>
							readonly="readonly"
							value="0"
						<?}else{?>
							value="<?=($params['amount_rub']) ? $params['amount_rub'] : $calcParams['amount_rub']?>"
						<?}?>
						id="amountRub" class="form-control">


					<span class="help-block">
						<?if($client->calc_mode == Client::CALC_MODE_AMOUNT){?>
							(максимум <?=formatAmount($calcParams['amount_rub'])?> руб с <?=$calcParams['dateStart']?>)
						<?}elseif($client->calc_mode == Client::CALC_MODE_ORDER){?>
							<p>Выберите заявки, сумма будет рассчитана автоматически.</p>
						<?}?>
					</span>

				</div>

				<div class="form-group">
					<label for="textfield1" class="control-label">Адрес BTC</label>
					<input type="text" name="params[btc_address]"
						   value="<?=$params['btc_address']?>"
						   id="textfield1" class="form-control">
				</div>

				<div class="form-group">
					<label for="textfield11" class="control-label">Адрес LTC</label>
					<input type="text" name="params[ltc_address]"
						   value="<?=$params['ltc_address']?>"
						   id="textfield11" class="form-control">
				</div>

				<div class="form-group">
					<label for="textfield2" class="control-label">Платежный пароль</label>
					<input type="password" name="params[extra]" value="<?=$params['extra']?>"
						   id="textfield2" class="form-control">
				</div>

				<div class="form-group">
					<label for="textfield3" class="control-label">Комментарий</label>
					<textarea name="params[client_comment]" id="textfield3" class="form-control"><?=$params['client_comment']?></textarea>
				</div>

				<div class="form-actions">
					<button type="submit" class="btn btn-primary" name="add" value="Создать">Создать</button>
					<a href="<?=url('finansist/calculateList')?>"><button type="button" class="btn">Отмена</button></a>
				</div>

				<input type="hidden" name="params[date_add]" value="<?=time()?>">


			<input type="hidden" id="rateUsd" value="<?=$calcParams['rateUsd']?>">
			<input type="hidden" id="rateBtc" value="<?=$calcParams['rateBtc']?>">
		</div>
	</div>
<?if($client->calc_mode == Client::CALC_MODE_ORDER){?>
</div>
<?}?>

<?if($client->calc_mode == Client::CALC_MODE_ORDER){?>
	<div id="orderList">
		<div class="box box-bordered">
			<div class="box-title">
				<h3><i class="fa fa-bars"></i>Неоплаченные заявки</h3>
			</div>
			<div class="box-content nopadding">
				<div id="orderListContent">
					<?foreach($orders as $order){?>
						<div class="form-group">
							<input type="checkbox" name="params[orderIds][<?=$order->id?>]" value="<?=$order->amountIn?>"/>
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


<div class="box box-bordered">
	<div class="box-title">
		<h3><i class="fa fa-bars"></i>Список расчетов</h3>
	</div>
	<div class="box-content nopadding">
		<?if($models){?>
			<table class="table table-bordered table-colored-header ">
				<thead>
					<th>ID</th>
					<th>Сумма</th>
					<th>Примечание</th>
					<th>Адрес</th>
					<?if($client->calc_mode == Client::CALC_MODE_ORDER){?>
						<td>Заявки</td>
					<?}?>
					<th>Статус</th>
					<th>Добавлен</th>
				</thead>

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
							<td><?=($model->ltc_address) ? $model->ltc_address : $model->btc_address?></td>

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
				</tbody>

			</table>
		<?}else{?>
			расчетов не найдено
		<?}?>
	</div>
</div>