<meta http-equiv="Refresh" content="30" />
<?
/**
 * @var RisexTransaction[] $transactions
 * @var array      $params
 * @var array      $stats
 * @var int        $pages
 */
$this->title = 'p2p Сервис'

?>

<fieldset>
	<legend>Выберите даты отображаемых платежей</legend>
	<p>
	<form method="post" action="<?= url('p2pService/manager/list') ?>">
		с <input type="text" name="params[dateStart]" value="<?= $interval['dateStart'] ?>"/>
		до <input type="text" name="params[dateEnd]" value="<?= $interval['dateEnd'] ?>"/>
		<input type="submit" name="statsByDate" value="Показать"/>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<b>Всего принято за период: <?= formatAmount($stats['amountIn'], 0) ?></b>
	</form>

	</p>

	<p>
	<form method="post" action="<?= url('p2pService/manager/list') ?>" style="display: inline">
		<input type="hidden" name="params[dateStart]" value="<?= date('d.m.Y H:i', Tools::startOfDay(time())) ?>"/>
		<input type="hidden" name="params[dateEnd]"
			   value="<?= date('d.m.Y H:i', Tools::startOfDay(time() + 24 * 3600)) ?>"/>
		<input type="submit" name="statsToday" value="За сегодня"/>
	</form>

	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

	<form method="post" action="<?= url('p2pService/manager/list') ?>" style="display: inline">
		<input type="hidden" name="params[dateStart]"
			   value="<?= date('d.m.Y H:i', Tools::startOfDay(time() - 24 * 3600)) ?>"/>
		<input type="hidden" name="params[dateEnd]" value="<?= date('d.m.Y H:i', Tools::startOfDay(time())) ?>"/>
		<input type="submit" name="statsTomorrow" value="За вчера"/>
	</form>
	</p>
</fieldset>
<br>
<hr>
<form method="post">
	<p id="message" class="error"></p>
	<p>
		<label for="params[amount]">Сумма: (минимум <?=RisexTransaction::AMOUNT_MIN?>р)</label>
	</p>
	<p>
		<input id="amount" size="10" name="params[amount]" value="" type="number" min="<?=RisexTransaction::AMOUNT_MIN?>" max="<?=RisexTransaction::AMOUNT_MAX?>" required>
	</p>
	<p>
		<input onclick="checkData()" type="submit" name="exchange" value="Создать заявку">
	</p>
</form>
<hr>
<br>
<? if($transactions){ ?>

	<?if($user->client->checkRule('pagination')){?>
		<div class="pagination">
			<?$this->widget('CLinkPager', array(
				'pages' => $pages,
			))?>
		</div>
	<?}?>

	<strong>Важно! После оплаты по реквизитам нажмите кнопку "Оплатил"</strong><br>
	<strong>Если заявка создана с ошибкой воспользуйтесь кнопкой "Отменить"</strong><br><br>

	<table class="std padding" width="100%">

		<thead>
		<tr cols="5">
			<th><span class="withComment" title="">Карта</span></th>
			<th>Сумма</th>
			<th>Статус</th>
			<th>Дата</th>
			<th>Действие</th>
		</tr>
		</thead>

		<tbody>
		<? foreach($transactions as $model){ ?>
			<tr cols="5">
				<td><b><?= $model->requisites ? $model->requisitesStr : 'Подбор карты ...' ?></b>
					<br> карта на одну заявку !!! </td>
				<td><?= $model->fiat_amount.' '.$model->currencyStr ?></td>
				<td> <? if(in_array($model->status, [RisexTransaction::STATUS_ERROR, RisexTransaction::STATUS_AUTOCANCELED, RisexTransaction::STATUS_CANCELED])){ ?>
						<span class="error"><b><?= $model->statusStr ?></b></span>
					<? }elseif(in_array($model->status, [RisexTransaction::STATUS_SELLER_REQUISITE, RisexTransaction::STATUS_VERIFICATION])){ ?>
						<span class="processing"><b><?= $model->statusStr ?></b></span>
						<br><?= $model->dateCancelationStr ?>
					<? }elseif(in_array($model->status, [RisexTransaction::STATUS_FINISHED])){ ?>
						<span class="success"><b><?= $model->statusStr ?></b></span>
					<? }elseif(in_array($model->status, [RisexTransaction::STATUS_PAID])) { ?>
						<span class="warning withComment" title="Идет проверка платежа"><b><?= $model->statusStr ?></b></span>
					<? }else{ ?>
						<span class="dotted"><b><?= $model->statusStr ?></b></span>
					<?}?>
				</td>
				<td><?= $model->createdAtStr ?></td>
				<td><button
						<?if($model->status != RisexTransaction::STATUS_SELLER_REQUISITE){ echo 'disabled="disabled"';}?>
						type="button" class="acceptButton" value="<?= $model->transaction_id ?>">Оплатил</button>
				</td>
				<td>
					<button
						<?if(!in_array($model->status, [RisexTransaction::STATUS_SELLER_REQUISITE, RisexTransaction::STATUS_IN_DISPUTE, RisexTransaction::STATUS_VERIFICATION])){ echo 'disabled="disabled"';}?>
						type="button" class="cancelPaymentButton" value="<?= $model->transaction_id ?>">Отменить</button>
				</td>
			</tr>

		<? } ?>
		</tbody>
	</table>

	<?if($user->client->checkRule('pagination')){?>
		<div class="pagination">
			<?$this->widget('CLinkPager', array(
				'pages' => $pages,
			))?>
		</div>
	<?}?>

<? } ?>

<form method="post" style="display: none" id="acceptPaymentForm">
	<input type="hidden" name="params[transId]" value=""/>

	<p>
		<input type="submit" name="acceptPayment" value="подтвердить платеж">
	</p>
</form>

<form method="post" style="display: none" id="cancelPaymentForm">
	<input type="hidden" name="params[transId]" value=""/>

	<p>
		<input type="submit" name="cancelPayment" value="отменить заявку">
	</p>
</form>

<script>
	$(document).ready(function () {

		$('.cancelPaymentButton').click(function () {
			$('#cancelPaymentForm [name*=transId]').val($(this).attr('value'));
			$('#cancelPaymentForm [type=submit]').click();
		})
	});

	$(document).ready(function () {

		$('.acceptButton').click(function () {
			$('#acceptPaymentForm [name*=transId]').val($(this).attr('value'));
			$('#acceptPaymentForm [type=submit]').click();
		})
	});
</script>

