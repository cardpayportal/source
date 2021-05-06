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
 */
?>

<h1><?=$this->title?></h1>

<p>
	<?=$this->renderPartial('_storeApiMenu')?>
</p>

<p>
	Баланс аккаунта: <?=formatAmount($balanceBtc, 5)?> btc
	(<?=($balanceBtcTimestamp) ? date(cfg('dateFormat'), $balanceBtcTimestamp) : '-----'?>)
	<a href="<?=url('control/StoreApiDeposit')?>">пополнить</a>
	<br>
	Курс: <?=config('storeApiBtcRate')?> usd
</p>

	<form method="post">
		<p>
			<?if($getWalletsEnabled){?>
				<span class="success">Выдача кошельков включена</span>
				<input type="submit" name="switchGetWallets" value="Отключить">
			<?}else{?>
				<span class="error">Выдача кошельков отключена</span>
				<input type="submit" name="switchGetWallets" value="Включить">
			<?}?>
		</p>
	</form>

<form method="post">
	<p>
		<?if($withdrawEnabled){?>
			<span class="success">Выводы включены</span>
			<input type="submit" name="switchWithdraw" value="Отключить">
		<?}else{?>
			<span class="error">Выводы отключены</span>
			<input type="submit" name="switchWithdraw" value="Включить">
		<?}?>
	</p>
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
		<label>Минимальный баланс для уведомления</label>
		<input type="text" name="params[noticeMinBalanceBtc]" value="<?=$noticeMinBalanceBtc?>"> btc
	</p>

	<p>
		<label>Запасной Jabber</label>
		<input type="text" name="params[noticeJabber]" value="<?=$noticeJabber?>">
		<i>(Если на смене нет GlobalFin-а, то слать сюда)</i>
	</p>

	<p>
		<input type="submit" name="save" value="Сохранить">
	</p>
</form>

<?if($errorTransactions){?>
	<h2>Проблемные платежи (<?=count($errorTransactions)?>) (пришли по апи, не пришли на кошелек)</h2>

	<table class="std padding">
		<tr>
			<td>ID</td>
			<td>Кошелек</td>
			<td>От</td>
			<td>Сумма</td>
			<td>QiwiID платежа</td>
			<td>Статус</td>
			<td>Магазин</td>
			<td>Дата добавления</td>
			<td>Дата Оплаты</td>
			<td>Действие</td>
		</tr>

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
						<input type="submit" name="confirm" value="Подтвердить" class="red" title="помечает платеж найденным"><br>
						<input type="submit" name="delete" value="Удалить" class="green" title="например удалить тестовый платеж">
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

	<table class="std padding">
		<tr>
			<td>StoreId</td>
			<td>Кошелек</td>
			<td>Сумма</td>
			<td>QiwiID платежа</td>
			<td>Статус</td>
			<td>От</td>
			<td>Дата добавления</td>
		</tr>

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
