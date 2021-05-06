<meta http-equiv="Refresh" content="30" />
<?
/**
 * @var RisexTransaction[] $transactions
 * @var array           $params
 * @var array           $stats
 * @var array           $data
 */
$this->title = 'Сделки'

?>

<fieldset>
	<legend>Выберите даты отображаемых платежей</legend>
	<p>
	<form method="post" action="<?= url('p2pService/admin/list') ?>">

		<b>Клиент: (можно выбрать несколько)</b><br>
		<select name="params[clientId][]" multiple>
			<?foreach(Client::getActiveClients() as $clientModel){?>

				<?
				if(in_array($clientModel->id, $filter['clientIds']))
					$selected = "selected";
				else
					$selected = "";
				?>

				<option <?=$selected?> value="<?=$clientModel->id?>"><?=$clientModel->name?></option>
			<?}?>
		</select>

		<b>От</b> <input type="text" name="params[dateStart]" value="<?=$interval['dateStart']?>">
		<b>До</b> <input type="text" name="params[dateEnd]" value="<?=$interval['dateEnd']?>">

		<input type="submit" name="statsByDate" value="Показать"/>
	</form>

	</p>

</fieldset>
<br>


<hr><br>
<form method="post">
	<p id="message" class="error"></p>
	<p>
		<label for="params[amount]">Сумма: (минимум 200р)</label>
	</p>
	<p>
		<input id="amount" size="10" name="params[amount]" value="" type="number" min="200" max="100000" required>
	</p>
	<p>
		<input onclick="checkData()" type="submit" name="exchange" value="Обменять">
	</p>
</form>

<br><br>


<? if($data){ ?>

	<?foreach($data as $clientName=>$clientInfo){?>

		<?foreach($clientInfo as $userName=>$userInfo){?>
			<p>
				<b>
					<?= $clientName ?>&nbsp;&nbsp;&nbsp;
					<?= $userName ?>&nbsp;&nbsp;&nbsp;
					<b>Всего принято за период: <?= formatAmount($userInfo['stats']['amount'], 0) ?></b>
				</b>
			</p>

			<table class="std padding" width="100%">

				<thead>
				<tr>
					<th><span class="withComment" title="Кошелек">Карта</span></th>
					<th>Статус</th>
					<th>Отдано</th>
					<th>Получено</th>
					<th>Курс</th>
					<th>Комиссия</th>
					<th>Комиссия заказа</th>
					<th>Клиент</th>
					<th>Юзер</th>
					<th>Дата</th>
					<th>Действие</th>
				</tr>
				</thead>

				<tbody>
				<? foreach($userInfo['transactions'] as $model){ ?>
					<tr>
						<td><b><nobr><?= $model->requisitesStr ?></nobr></b></td>
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
						<td><b><?= $model->fiat_amount.' '.$model->currencyStr ?></b></td>
						<td><b><?= $model->crypto_amount.' '.$model->crypto_currency ?></b></td>
						<td><b><?= $model->price ?></b></td>
						<td><b><?= $model->commissions_client ?></b></td>
						<td><b><?= $model->commissions_offer ?></b></td>
						<td><b><?= $model->client->name ?></b></td>
						<td><b><?= $model->user->name ?></b></td>
						<td><?= $model->createdAtStr ?></td>

						<td>
							<button
								<?if(!in_array($model->status, [RisexTransaction::STATUS_SELLER_REQUISITE, RisexTransaction::STATUS_IN_DISPUTE, RisexTransaction::STATUS_VERIFICATION])){ echo 'disabled="disabled"';}?>
								type="button" class="cancelPaymentButton" value="<?= $model->transaction_id ?>">Отменить</button>
						</td>
					</tr>

				<? } ?>
				</tbody>
			</table>

		<? } ?>
		<br><br>
	<? } ?>

<? } ?>

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


	function checkData() {
		var obj = document.getElementById("amount");
		if(!obj.checkValidity()) {
			document.getElementById("message").innerHTML = obj.validationMessage;
		}
	}
</script>


