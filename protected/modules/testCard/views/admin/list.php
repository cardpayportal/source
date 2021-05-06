<?
/**
 * @var TestCardModel[] $models
 * @var array           $params
 * @var array           $stats
 */
$this->title = 'Карты'

?>


<form method="post">
	<p>
		<input type="text" name="params[wallet]" value="<?= $params['wallet'] ?>" placeholder="Номер кошелька"/>
		&nbsp;
		&nbsp;
		&nbsp;
		<input type="text" name="params[cardNumber]" value="<?= $params['cardNumber'] ?>" placeholder="Номер карты"/>
		<?/*&nbsp;
		&nbsp;
		&nbsp;
		<input type="text" name="params[balance]" value="<?= $params['balance'] ?>" placeholder="Баланс"/>*/?>
		&nbsp;
		&nbsp;
		&nbsp;
		<input type="text" name="params[totalLimit]" value="<?= $params['totalLimit'] ?>" placeholder="Общий лимит"/>
		<br><br>
		<select name="params[status]">
			<option selected="selected" disabled="disabled">Статус</option>
			<option value="active">Активен</option>
			<option value="inactive">Не активен</option>
		</select>
		&nbsp;
		&nbsp;
		&nbsp;
		<label>
			<?=CHtml::dropDownList('client_id','',
				Client::getArr(),
				array(
					'prompt'=>'Select Client',
					'ajax' => array(
						'type'=>'POST',
						'url'=>Yii::app()->createUrl('testCard/admin/loadUsers'),
						'update'=>'#user_id',
						'data'=>array('client_id'=>'js:this.value'),
					)));?>
		</label>
		&nbsp;
		&nbsp;
		&nbsp;
		<label>
			<?=CHtml::dropDownList('user_id','', array(), array('prompt'=>'Select User'));?>
		</label>
		&nbsp;
		&nbsp;
		&nbsp;
		<input type="submit" name="addWallet" value="Добавить кошелек">
	</p>
</form>

<hr>
<?/*?>
<fieldset>
	<legend>Выберите даты отображаемых платежей</legend>
	<p>
	<form method="post" action="<?= url('testCard/admin/list') ?>">
		с <input type="text" name="params[dateStart]" value="<?= $interval['dateStart'] ?>"/>
		до <input type="text" name="params[dateEnd]" value="<?= $interval['dateEnd'] ?>"/>
		<input type="submit" name="stats" value="Показать"/>
	</form>
	</p>

	<p>
	<form method="post" action="<?= url('testCard/admin/list') ?>" style="display: inline">
		<input type="hidden" name="params[dateStart]" value="<?= date('d.m.Y H:i', Tools::startOfDay(time())) ?>"/>
		<input type="hidden" name="params[dateEnd]"
			   value="<?= date('d.m.Y H:i', Tools::startOfDay(time() + 24 * 3600)) ?>"/>
		<input type="submit" name="stats" value="За сегодня"/>
	</form>

	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

	<form method="post" action="<?= url('testCard/admin/list') ?>" style="display: inline">
		<input type="hidden" name="params[dateStart]"
			   value="<?= date('d.m.Y H:i', Tools::startOfDay(time() - 24 * 3600)) ?>"/>
		<input type="hidden" name="params[dateEnd]" value="<?= date('d.m.Y H:i', Tools::startOfDay(time())) ?>"/>
		<input type="submit" name="stats" value="За вчера"/>
	</form>
	</p>
</fieldset>

<?*/?>

<? if($models){ ?>

	<p>
		<?/*?><b>Всего принято: <?= formatAmount($stats['amount'], 0) ?></b><?*/?>
	</p>

	<table class="std padding" width="100%">

		<thead>
		<tr>
			<th>Кошелек</th>
			<th><span class="withComment" title="Карта яндекс деньги это тоже самое что и ваш кошелек ЯД">Карта</span>
			</th>
			<th>Клиент</th>
			<th>Юзер</th>
			<th>Баланс</th>
			<th>Всего принято</th>
			<th>Статус</th>
			<?/*<th>Суточный лимит</th>*/?>
			<th>Общий лимит</th>
			<th>Действие</th>
		</tr>
		</thead>

		<tbody>
		<? foreach($models as $model){ ?>
			<tr>
				<td><b><?= $model->wallet ?></b></td>
				<td><b><?= $model->cardNumberStr ?></b></td>
				<td><b><?= $model->client->name ?></b></td>
				<td><b><?= $model->user->name ?></b></td>
				<td><?= $model->balanceStr ?></td>
				<td><?= $model->totalAmount ?></td>
				<td>
					<? if($model->error){ ?>
						<span class="error"><?= $model->error ?></span>
					<? }elseif($model->status == 'inactive'){ ?>
						<span class="error">не активен</span>
					<? }else{ ?>
						<span class="success">активен</span>
					<? } ?>
				</td>
				<td>
					<?= $model->limitInMonthStr ?>
				</td>
				<td>
					<a href="<?=url('testCard/admin/editCard', ['cardId'=>$model->id])?>" target="_blank"><button type="button" title="Редактировать" value="<?=$model->id?>">Редактировать</button></a><br><br>
					<button class="deleteWalletButton" value="<?=$model->id?>">Удалить</button><br><br>
					<?if($model->hidden){?>
						<button type="button" class="walletVisibleControl red" value="<?=$model->id?>" visible="<?=$model->hidden?>">Показать</button>
					<?}else{?>
						<button type="button" class="walletVisibleControl green" value="<?=$model->id?>" visible="<?=$model->hidden?>">Скрыть</button>
					<?}?>
				</td>
			</tr>
				<tr>
					<td colspan="9">
						<table class="noBorder trHeight" style="margin-left: 10px; width: 100%;">
							<? if($transactions = $model->transactionsManager){ ?>
								<tr>
									<td><b>Сумма</b></td>
									<td><b>Дата</b></td>
									<td><b>Комментарий</b></td>
									<td><b>Действие</b></td>
								</tr>
								<? foreach($transactions as $key => $trans){ ?>
									<tr class="success"
										<? if($key > 2){ ?>
											data-param="toggleRow"
											style="display: none;"
										<? } ?>
									>
										<td width="100"><input type="text" name="params[amount]" value="<?= $trans->amount ?>"/></td>
										<td><input type="text" name="params[dateAdd]" value="<?= date('d.m.Y H:i', $trans->date_add) ?>"/></td>
										<td><input type="text" name="params[comment]" value="<?= $trans->comment ?>"/></td>
										<td><?/*<button>Сохранить</button>*/?>&nbsp;&nbsp;&nbsp;<button class="deleteTransactionButton" value="<?=$trans->id?>">Удалить</button></td>
									</tr>
								<? } ?>
							<? } ?>
							<form method="post">
							<tr class="success">
								<input type="hidden" name="params[walletId]" value="<?= $model->id ?>"/>
								<input type="hidden" name="params[userId]" value="<?= $model->user_id ?>"/>
								<input type="hidden" name="params[clientId]" value="<?= $model->client_id ?>"/>
								<td><input type="text" name="params[amount]" placeholder="сумма"/></td>
								<td><input type="text" name="params[date]" value="<?= date('d.m.Y H:i', time()) ?>"/></td>
								<td><input type="text" name="params[comment]" placeholder="комментарий"/></td>
								<td><input type="submit" value="Добавить транзакцию" name="addTransaction[<?= $model->id ?>]"/></td>
							</tr>
							</form>
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


