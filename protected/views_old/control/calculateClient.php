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
 */

if($lastCalcArr)
	$this->title = 'Рассчитать '.$client->name;
else
	$this->title = 'Установка последнего расчета к '.$client->name;

?>

<h1 align="center"><?=$this->title?></h1>

<form method="post">

		<?if($lastCalcArr){?>

			<h2>Последние расчеты</h2>

			<?if($recalcResult['diffAmount'] != 0){?>
				<p><b>Несоответствия на сумму: <?=$recalcResult['diffAmount']?> руб</b> (учтено в сумме расчета)</p>

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
								&nbsp;&nbsp;&nbsp;<?=$transaction->amountStr?> руб
								&nbsp;&nbsp;&nbsp;<?=$transaction->dateAddDbStr?>

						<?}?>
					</p>
				<?}?>
			<?}?>

			<?$this->renderPartial('_calcList', array('models'=>$lastCalcArr))?>

		<?}else{?>
			расчетов ранее не производилось
		<?}?>

	<?if(!$lastCalcArr){?>
		<p>
			<font color="orange">Не найден последний расчет клиента. Добавьте вручную дату полного расчета.</font>
		</p>
	<?}?>

	<?if($calcParams['clFinOrdersInProcess']){?>
		<font color="red">Внимание! Клиент все еще выводит средства. Расчет не производить</font>
	<?}?>

	<p>
		<?if($lastCalcArr){?>
			<strong>Расчет от:</strong><br>
		<?}else{?>
			<strong>Дата полного расчета:</strong><br>
		<?}?>

		<input type="text" value="<?=$calcParams['dateStart']?>"
			<?if($lastCalcArr){?>
				disabled="disabled"
			<?}else{?>
				name="params[date_add]"<?//первый расчет ставится дата вручную?>
			<?}?>
			id="dateAdd"
			class="recalcAmountRub"
		>

		<?if($lastCalcArr){?>
			Приход: <b><?=formatAmount($statsIn, 0)?> руб</b><br>
			<?//фиксируем дату добавления(чтобы небыло расхождений сразу после)?>
			<input type="hidden" name="params[date_add]" value="<?=time()?>">
		<?}?>
	</p>

	<p>
		<strong>Сумма руб</strong><br>
		<input type="text" name="params[amount_rub]" value="<?=($params['amount_rub']) ? $params['amount_rub'] : $calcParams['amount_rub']?>" id="amountRub">

		долг при прошлом расчете: <?=$lastCalc->debtRubStr?> (входит в сумму расчета)

		<?if(!$lastCalcArr){?>
			<br><i>сумма этого расчета не будет учитываться при дальнейших подсчетах (используется только дата)</i>
		<?}?>
	</p>

	<p>
		<strong>Сумма USD</strong><br>
		<input type="text" name="params[amount_usd]" value="<?=($params['amount_usd']) ? $params['amount_usd'] : $calcParams['amount_usd']?>" id="amountUsd">
	</p>

	<p>
		<strong>Курс</strong><br>
		<span id="rateUsd"><?=$calcParams['rateUsd']?></span>(<?=$calcParams['rateSourceName']?>: <span id="rateUsdSource"><?=$calcParams['rateUsdSource']?></span>)
	</p>

	<p>
		<strong>Примечание</strong> (не обязательно, видно клиенту)<br>
		<input type="text" name="params[comment]" value="<?=$params['comment']?>">
	</p>

	<?if($lastCalcArr){?>
		<p>
			<input type="checkbox" name="params[is_control]" value="1" <?=($params['is_control']) ? 'checked="checked"' : ''?>>
			<strong><span class="dotted" title="Если наша сумма меньше или равна той, что назвал клиент">Контрольный расчет</span></strong>
		</p>
	<?}?>

	<p>
		<input type="submit" name="calculate" value="Добавить" id="submit">
	</p>
</form>


<?/*<p>Текущий баланс исходящих: <?=formatAmount(Client::getSumOutBalance($user->client_id), 0)?></p>*/?>

<?if($calcParams['clFinOrders']){?>
	<h2>Выводы клФина (<?=$calcParams['finOrdersDateStart']?> - <?=$calcParams['finOrdersDateEnd']?>)</h2>

	<p>
		Общая сумма: <?=formatAmount($calcParams['clFinOrdersTotal'], 0)?>
	</p>

	<?$this->renderPartial('_globalFinOrderList', array(
		'models'=>$calcParams['clFinOrders'],
	))?>
<?}?>

<?/*if($calcParams['clFinOrders']){?>
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

		setInterval(function(){
			location.reload();
		},1200000);	//20 min
	})



</script>