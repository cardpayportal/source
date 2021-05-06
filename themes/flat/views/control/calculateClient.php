<?
/**
 * @var Client $client
 * @var array $params
 * @var array $calcParams
 * @var Account $warnAccount
 * @var ClientCalc[] $lastCalcArr последние расчеты
 * @var ClientCalc $lastCalc последний расчет
 * @var string $clientCalcPercent процент для курса
 * @var string $clientCalcBonus бонус к проценту для курса
 * @var array $recalcResult
 * @var float $statsIn	приход с момента последнего расчета
 * @var ManagerOrder[] $orders
 * @var float $statsQiwi
 * @var float $statsWex
 * @var float $statsYandex
 */

if($lastCalcArr)
	$this->title = 'Рассчитать '.$client->name;
else
	$this->title = 'Установка последнего расчета к '.$client->name;
?>

<form method="post" class="form-vertical form-bordered">

	<?if($client->calc_mode == Client::CALC_MODE_ORDER){?>
	<div id="calcForm">
		<?}?>

		<div class="box box-bordered">
			<div class="box-title">
				<h3>
					<i class="fa fa-calculator"></i>Добавить расчет
				</h3>
			</div>
			<div class="box-content">
				<?if($lastCalcArr and $recalcResult['diffAmount'] != 0){?>
					<p><b>Несоответствия на сумму: <?=formatAmount($recalcResult['diffAmount'], 2)?> руб</b> (учтено в сумме расчета)</p>

					<?foreach($recalcResult['lateTransactions'] as $calcId=>$transactions){?>
						<?
						/**
						 * @var Transaction[] $transactions
						 */
						?>
						<p style="color: red">
							Расчет <?=$calcId?>:
							<?foreach($transactions as $transaction){?>
								&nbsp;&nbsp;&nbsp;<br>
								<?=$transaction->account->login?>
								&nbsp;&nbsp;&nbsp;<?=$transaction->amountStr?>
								&nbsp;&nbsp;&nbsp;<?=$transaction->dateAddStr?> (обнаружен: <?=$transaction->dateAddDbStr?>)
								&nbsp;&nbsp;&nbsp;<?=$transaction->qiwi_id?>

							<?}?>
						</p>
					<?}?>
				<?}?>

					<div class="form-group">
						<label for="dateAdd" class="control-label">
							<?if($lastCalcArr){?>
								Расчет от:
							<?}else{?>
								Дата полного расчета:
							<?}?>
						</label>

						<input type="text" value="<?=$calcParams['dateStart']?>"
							<?if($lastCalcArr){?>
								disabled="disabled"
							<?}else{?>
								name="params[date_add]"<?//первый расчет ставится дата вручную?>
							<?}?>
							   id="dateAdd"
							   class="recalcAmountRub form-control"
						>
						<?if($lastCalcArr){?>
							<span class="help-block">
								Приход: <b><?=formatAmount($statsIn, 0)?> руб</b><br>
								<br>
								<b>Qiwi</b>: <?=formatAmount($statsQiwi, 0)?> руб
								<br><b>WEX</b>: <?=formatAmount($statsWex, 0)?> руб
									(-<?=(cfg('wexPercent')*100)?>%) = <?=formatAmount($statsWex * (1 - cfg('wexPercent')), 0)?>
								<br><b>Yandex</b>: <?=formatAmount($statsYandex, 0)?> руб<br>
								<?//фиксируем дату добавления(чтобы небыло расхождений сразу после)?>
								<input type="hidden" name="params[date_add]" value="<?=time()?>">
							</span>
						<?}?>
					</div>

					<div class="form-group">
						<label for="amountRub" class="control-label">Сумма (руб)</label>
						<input type="text" name="params[amount_rub]"

							<?if($client->calc_mode == Client::CALC_MODE_ORDER){?>
								readonly="readonly"
								value="0"
							<?}else{?>
								value="<?=($params['amount_rub']) ? $params['amount_rub'] : $calcParams['amount_rub']?>"
							<?}?>

							id="amountRub"
							class="form-control"/>

						<span class="help-block">
							долг при прошлом расчете: <?=$lastCalc->debtRubStr?> (входит в сумму расчета)

							<?if(!$lastCalcArr){?>
								<i>сумма этого расчета не будет учитываться при дальнейших подсчетах (используется только дата)</i>
							<?}?>
						</span>
					</div>

					<div class="form-group">
						<label for="amountUsd" class="control-label">Сумма USD</label>
						<input type="text" name="params[amount_usd]" value="<?=($params['amount_usd']) ? $params['amount_usd'] : $calcParams['amount_usd']?>" id="amountUsd" class="form-control">

						<span class="help-block">
							<b>Курс:</b> <span id="rateUsd"><?=$calcParams['rateUsd']?></span>(<?=$calcParams['rateSourceName']?>: <span id="rateUsdSource"><?=$calcParams['rateUsdSource']?></span>)
						</span>
					</div>

					<div class="form-group">
						<label for="textfield1" class="control-label">Примечание</label>
						<input type="text" name="params[comment]" value="<?=$params['comment']?>" class="form-control" id="textfield1">
						<span class="help-block">
							видно клиенту
						</span>
					</div>

					<?if($lastCalcArr){?>

						<div class="form-group">
							<label class="control-label">Дополнительно</label>
							<div class="checkbox">
								<label>
									<input type="checkbox" name="params[is_control]" value="1"
										<?=($params['is_control']) ? 'checked="checked"' : ''?> id="textfield2">
									контрольный расчет
								</label>
							</div>

							<span class="help-block">
									Поставьте Контрольный расчет, если наша сумма меньше или равна той, что назвал клиент.
									<br>Контрольные расчеты ускоряют подсчеты.
									<br><i>помечает все завершенные заявки оплаченными</i>
							</span>
						</div>
					<?}?>
					<div class="form-actions">
						<button type="submit" class="btn btn-primary" name="calculate" value="Добавить" id="submit">Добавить</button>
						<a href="<?=url(cfg('index_page'))?>"><button type="button" class="btn">Отмена</button></a>
					</div>


				<?if($calcParams['clFinOrders']){?>
					<h2>Выводы клФина (<?=$calcParams['finOrdersDateStart']?> - <?=$calcParams['finOrdersDateEnd']?>)</h2>

					<p>Общая сумма: <?=formatAmount($calcParams['clFinOrdersTotal'], 0)?></p>

					<?$this->renderPartial('_globalFinOrderList', array(
						'models'=>$calcParams['clFinOrders'],
					))?>
				<?}?>
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
					<div id="orderListContentGf1">
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

<div class="box box-bordered">
		<div class="box-title">
			<h3>
				<i class="fa fa-bars"></i>Последние расчеты
			</h3>
		</div>
		<div class="box-content">
			<?if($lastCalcArr){?>
				<form method="post">
					<?$this->renderPartial('_calcList', array('models'=>$lastCalcArr))?>
				</form>
			<?}else{?>
				<p>
					<span class="orange">Не найден последний расчет клиента. Добавьте вручную дату полного расчета.</span>
				</p>
			<?}?>
		</div>
</div>




<?/*
if($calcParams['clFinOrders']){?>
	<h2>Выводы глобалФина (<?=$calcParams['finOrdersDateStart']?> - <?=$calcParams['finOrdersDateEnd']?>)</h2>

	<p>
		Общая сумма: <?=formatAmount($calcParams['globalFinOrdersTotal'], 0)?>
	</p>

	<?$this->renderPartial('_globalFinOrderList', array(
		'models'=>$calcParams['globalFinOrders'],
	))?>
<?}*/?>

<script>
	$(document).ready(function(){
		//пересчет при смене даты
		$('.recalcAmountRub').change(function(){

			$('#amountRub').prop('disabled', true);
			$('#amountUsd').prop('disabled', true);
			$('#rateUsd').text('');
			$('#rateUsdSource').text('');

			sendRequest('<?=url('control/ajaxRecalc')?>', 'clientId=<?=$client->id?>&dateStart='+$('#dateAdd').val(), function(response){
				$('#amountRub').val(response.amount_rub).prop('disabled', false);
				$('#amountUsd').val(response.amount_usd).prop('disabled', false);
				$('#rateUsd').text(response.rateUsd);
				$('#rateUsdSource').text(response.rateUsdSource);
			});
		});

		//recalc after change amount_rub
		$('#amountRub').change(function(){
			$('#amountUsd').prop('disabled', true);

			sendRequest('<?=url('control/ajaxRecalcUsd')?>', 'amountRub='+$(this).val()+'&rateUsd='+$('#rateUsd').text(), function(response){
				$('#amountUsd').val(response.amountUsd).prop('disabled', false);
				$('#submit').prop('disabled', false);
			});
		});

		$('#amountRub').keyup(function(){
			$('#submit').prop('disabled', true);
			$('#amountUsd').prop('disabled', true);
		});

		<?//если расчет по заявкам?>
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

		setInterval(function(){
			location.reload();
		},1200000);	//20 min
	})
</script>