<?
/**
 * @var TestCardModel[] $models
 * @var array           $params
 * @var array           $stats
 */
$this->title = 'Карты'

?>

<hr>
<fieldset>
	<legend>Выберите даты отображаемых платежей</legend>
	<p>
	<form method="post" action="<?= url('testCard/manager/list') ?>">
		с <input type="text" name="params[dateStart]" value="<?= $interval['dateStart'] ?>"/>
		до <input type="text" name="params[dateEnd]" value="<?= $interval['dateEnd'] ?>"/>
		<input type="submit" name="stats" value="Показать"/>
	</form>
	</p>

	<p>
	<form method="post" action="<?= url('testCard/manager/list') ?>" style="display: inline">
		<input type="hidden" name="params[dateStart]" value="<?= date('d.m.Y H:i', Tools::startOfDay(time())) ?>"/>
		<input type="hidden" name="params[dateEnd]"
			   value="<?= date('d.m.Y H:i', Tools::startOfDay(time() + 24 * 3600)) ?>"/>
		<input type="submit" name="stats" value="За сегодня"/>
	</form>

	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

	<form method="post" action="<?= url('testCard/manager/list') ?>" style="display: inline">
		<input type="hidden" name="params[dateStart]"
			   value="<?= date('d.m.Y H:i', Tools::startOfDay(time() - 24 * 3600)) ?>"/>
		<input type="hidden" name="params[dateEnd]" value="<?= date('d.m.Y H:i', Tools::startOfDay(time())) ?>"/>
		<input type="submit" name="stats" value="За вчера"/>
	</form>
	</p>
</fieldset>

<? if($models){ ?>

	<p>
		<b>Всего принято: <?= formatAmount($stats['amount'], 0) ?></b>
	</p>

	<table class="std padding" width="100%">

		<thead>
		<tr>
			<th>Кошелек</th>
			<th><span class="withComment" title="">Карта</span>
			</th>
			<th>Баланс</th>
			<th>Всего принято</th>
			<th>Статус</th>
			<th>Общий лимит</th>
		</tr>
		</thead>

		<tbody>
		<? foreach($models as $model){ ?>
			<?if($model->hidden == true) continue;?>
			<tr>
				<td><b><?= $model->wallet ?></b></td>
				<td><b><?= $model->cardNumberStr ?></b></td>
				<td><?= $model->balanceStr ?></td>
				<td><?= $model->totalAmount ?></td>
				<td>
					<? if($model->error){ ?>
						<span class="error"><?= $model->error ?></span>
					<? }else{ ?>
						<span class="success">активен</span>
					<? } ?>
				</td>
				<td>
					<?= $model->limitInMonthStr ?>
				</td>
			</tr>
			<tr>
				<td colspan="7">
					<table class="noBorder trHeight" style="margin-left: 10px; width: 100%;">
						<? if($transactions = $model->transactionsManager){ ?>
							<tr>
								<td><b>Сумма</b></td>
								<td><b>Дата</b></td>
								<td><b>Комментарий</b></td>
							</tr>
							<? foreach($transactions as $key => $trans){ ?>
								<tr class="success"
									<? if($key > 2){ ?>
										data-param="toggleRow"
										style="display: none;"
									<? } ?>
								>
									<td width="100"><?= $trans->amount ?></td>
									<td><?= date('d.m.Y H:i', $trans->date_add) ?></td>
									<td><?= $trans->comment ?></td>
								</tr>
							<? } ?>
						<? } ?>
					</table>
					<? if(isset($transactions) and count($transactions) > 2){ ?>
						<button type="button" class="btn btn-info btn-mini showTransactions btn--icon">
							<i class="fa fa-caret-down"></i>показать все
						</button>

						<button type="button" class="btn btn-info btn-mini hideTransactions btn--icon"
								style="display: none">
							<i class="fa fa-caret-up"></i>скрыть
						</button>
					<? } ?>
				</td>
			</tr>

		<? } ?>
		</tbody>
	</table>


<? } ?>


<script>
	$(document).ready(function () {

		<?//показать все транзакции аккаунта?>
		$(document).on("click", ".showTransactions", function () {
			$(this).parent().find('tr[data-param=toggleRow]').show();
			$(this).text('Скрыть');
			$(this).removeClass('showTransactions');
			$(this).addClass('hideTransactions');
		});

		<?//скрыть старые транзакции?>
		$(document).on("click", ".hideTransactions", function () {
			$(this).parent().find('tr[data-param=toggleRow]').hide();
			$(this).text('Показать все');
			$(this).removeClass('hideTransactions');
			$(this).addClass('showTransactions');
		});

		$('.walletVisibleControl').click(function () {
			$('#walletVisibleForm [name*=walletId]').val($(this).attr('value'));
			$('#walletVisibleForm [name*=hidden]').val($(this).attr('visible'));
			$('#walletVisibleForm [type=submit]').click();
		});

		$('.deleteWalletButton').click(function () {
			$('#deleteWalletForm [name*=walletId]').val($(this).attr('value'));
			$('#deleteWalletForm [type=submit]').click();
		});

		$('.deleteTransactionButton').click(function () {
			$('#deleteTransactionForm [name*=transactionId]').val($(this).attr('value'));
			$('#deleteTransactionForm [type=submit]').click();
		});

		$('.editWalletButton').click(function () {
			$('#editWalletForm [name*=walletId]').val($(this).attr('value'));
			$('#editWalletForm [type=submit]').click();
		});
	});
</script>

<form method="post" style="display: none" id="walletVisibleForm">
	<input type="hidden" name="params[walletId]" value="" />
	<input type="hidden" name="params[hidden]" value="" />
	<p>
		<input type="submit" name="walletVisibleSubmit" value="Вкл/откл отображение кошелька">
	</p>
</form>

<form method="post" style="display: none" id="deleteWalletForm">
	<input type="hidden" name="params[walletId]" value="" />
	<p>
		<input type="submit" name="deleteWalletSubmit" value="Удаление кошелька">
	</p>
</form>

<form method="post" style="display: none" id="deleteTransactionForm">
	<input type="hidden" name="params[transactionId]" value="" />
	<p>
		<input type="submit" name="deleteTransactionSubmit" value="Удаление транзакции">
	</p>
</form>

<form method="post" style="display: none" id="editWalletForm">
	<input type="hidden" name="params[walletId]" value="" />
	<p>
		<input type="submit" name="editWalletSubmit" value="Редактирование кошелька">
	</p>
</form>
