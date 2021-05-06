<?
$this->title = 'StoreApi';

/**
 * @var StoreApiTransaction[] $errorTransactions
 * @var Transaction[] $unknownTransactions
 * @var bool $withdrawEnabled
 * @var float $btceBalanceBtc
 * @var float $btceBalanceUsd
 * @var int $btceBalanceTimestamp
 * @var string $noticeJabber
 * @var float $noticeMinBalance
 * @var bool $getWalletsEnabled
 * @var float  $noticeMinBalanceBtc
 * @var float $balanceBtc
 * @var int $balanceBtcTimestamp
 * @var StoreApiWithdraw[] $unconfirmedWithdraws
 * @var float $networkCommission
 */
?>

<p>
	<?=$this->renderPartial('_storeApiMenu')?>
</p>

<p>
	Баланс аккаунта: <?=formatAmount($balanceBtc, 5)?> btc
	(<?=($balanceBtcTimestamp) ? date(cfg('dateFormat'), $balanceBtcTimestamp) : '-----'?>)
	<a href="<?=url('control/StoreApiDeposit')?>">пополнить</a>
	<br>
	Курс: <?=config('storeApiBtcRate')?> usd
	<br>
	Комиссия сети: <?=$networkCommission?> BTC
</p>

<form method="post">
	<table class="padding">
		<?if($getWalletsEnabled){?>
			<tr>
				<td><span class="success">Выдача кошельков включена</span></td>
				<td>
					<button type="submit" class="btn btn-small btn-warning"
							name="switchGetWallets" value="Отключить">Отключить</button>
				</td>
			</tr>
		<?}else{?>
			<tr>
				<td><span class="error">Выдача кошельков отключена</span></td>
				<td>
					<button type="submit" class="btn btn-small btn-success"
							name="switchGetWallets" value="Включить">Включить</button>
				</td>
			</tr>
		<?}?>

		<?if($withdrawEnabled){?>
			<tr>
				<td><span class="success">Выводы включены</span></td>
				<td>
					<button type="submit" class="btn btn-small btn-warning"
							name="switchWithdraw" value="Отключить">Отключить</button>
				</td>
			</tr>
		<?}else{?>
			<tr>
				<td><span class="error">Выводы отключены</span></td>
				<td>
					<button type="submit" class="btn btn-small btn-success"
							name="switchWithdraw" value="Включить">Включить</button>
				</td>
			</tr>
		<?}?>

		<tr>
			<td>Приоритет BTC-платежей</td>
			<td>
				<label>
					<select name="params[priority]">
						<?foreach($withdrawPriorityArr as $key=>$name){?>
							<option value="<?=$key?>"
									<?if($key == $withdrawPriority){?>selected="selected"<?}?>
							>
								<?=$name?>
							</option>
						<?}?>
					</select>
					&nbsp;&nbsp;&nbsp;&nbsp;
					<button type="submit" class="btn btn-small btn-success"
							name="changePriority" value="Сменить">Сменить</button>
				</label>
			</td>
		</tr>

	</table>
</form>

<form method="post">

	<h2>Уведомление</h2>

	<i>(Предупреждение о низком балансе)</i>

	<?/*
	<p>
		<label>Минимальный баланс для уведомления</label>
		<input type="text" name="params[noticeMinBalance]" value="<?=$noticeMinBalance?>"> usd
	</p>
	*/?>
	<p>
		<label>
			Минимальный баланс для уведомления (BTC)
			<input type="text" name="params[noticeMinBalanceBtc]" value="<?=$noticeMinBalanceBtc?>" class="form-control">
		</label>
	</p>

	<p>
		<label>
			Запасной Jabber
			<input type="text" name="params[noticeJabber]" value="<?=$noticeJabber?>" class="form-control">
		</label>
		<i>(Если на смене нет GlobalFin-а, то слать сюда)</i>
	</p>

	<p>
		<button type="submit" class="btn btn-primary"
				name="save" value="Сохранить">Сохранить</button>
	</p>
</form>

<?if($errorTransactions){?>
	<h2>Проблемные платежи (<?=count($errorTransactions)?>) (пришли по апи, не пришли на кошелек)</h2>

	<table class="table table-bordered table-colored-header">
		<thead>
			<th>ID</th>
			<th>Кошелек</th>
			<th>От</th>
			<th>Сумма</th>
			<th>QiwiID платежа</th>
			<th>Статус</th>
			<th>Магазин</th>
			<th>Дата добавления</th>
			<th>Дата Оплаты</th>
			<th>Действие</th>
		</thead>

		<?foreach($errorTransactions as $model){?>
			<tr>
				<td><?=$model->id?></td>
				<td>
					<a href="<?=url('account/list', array('login'=>trim($model->account->login, '+')))?>" title="информация о кошельке"><?=$model->account->login?></a><br>
					проверен: <?=$model->account->dateCheckStr?><br>
					<?if($model->account->error){?>
						<span class="error"><?=$model->account->error?></span>
					<?}?>
				</td>
				<td><?=$model->wallet_from?></td>
				<td>
					<?=$model->amount?> <?=$model->currency?>
				</td>
				<td><?=$model->qiwi_id?></td>
				<td><?=$model->statusStr?></td>
				<td><?=$model->store_id?></td>
				<td><?=$model->dateAddStr?></td>
				<td><?=$model->datePayStr?></td>
				<td>
					<form method="post">
						<input type="hidden" name="id" value="<?=$model->id?>">
						<button type="submit" class="btn btn-small btn-warning" title="помечает платеж найденным"
								name="confirm" value="Подтвердить">Подтвердить</button><br>

						<button type="submit" class="btn btn-small btn-success" title="например удалить тестовый платеж"
								name="delete" value="Удалить">Удалить</button>
					</form>
				</td>
			</tr>
		<?}?>
	</table>
<?}else{?>
	<p>Проблемных платежей не найдено</p>
<?}?>


<?if($unknownTransactions){?>
	<h2>Неизвестные платежи (<?=count($unknownTransactions)?>) (пришли на кошелек, не пришли по апи)</h2>

	<table class="table table-bordered table-colored-header">
		<thead>
			<th>StoreId</th>
			<th>Кошелек</th>
			<th>Сумма</th>
			<th>QiwiID платежа</th>
			<th>Статус</th>
			<th>От</th>
			<th>Дата добавления</th>
		</thead>

		<?foreach($unknownTransactions as $model){?>
			<tr>
				<td>store<?=$model->user->store->store_id?></td>
				<td><?=$model->account->login?></td>
				<td>
					<?=$model->amount?>
					<?if($model->convert_id){?>
						<br>
						<?=$model->transactionKztStr?>
					<?}?>
				</td>
				<td><?=$model->qiwi_id?></td>
				<td><?=$model->statusStr?></td>
				<td><?=$model->wallet?></td>
				<td><?=$model->dateAddStr?></td>
			</tr>
		<?}?>
	</table>
<?}else{?>
	<p>Неизвестных платежей не найдено</p>
<?}?>

<?if($unconfirmedWithdraws){?>
	<h2>Неподтвержденные выводы (<?=count($unconfirmedWithdraws)?>) (не подтверждены сетью)</h2>

	<?$this->renderPartial('_withdrawList', ['models'=>$unconfirmedWithdraws])?>
<?}else{?>
	<p>Неподтвержденных выводов не найдено</p>
<?}?>
