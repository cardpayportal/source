<?
	/**
	 * @var Account $model
	 * @var bool $smsEnabled
	 * @var bool $smsPaymentEnabled
	 * @var array $transactions
	 * @var string $status
	 * @var string $proxy
	 * @var string $browser
	 * @var string $outIp
	 * @var bool $isEmail
	 * @var float $balanceKzt
	 * @var array $stats

	 */
	$this->title = 'HistoryAdmin';
?>



<p>
	<strong><?=$model->login?> :</strong> <?=formatAmount($balance, 0)?> RUB <?=($model->client_id == 16) ? ', '.formatAmount($balanceKzt, 0).' KZT' : ''?>

	<?if($model->error){?>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<font color="red"><?=$model->error?></font>

		<?if($model->error == 'ban'){?>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="<?=url('account/unban', array('id'=>$model->id))?>">разбанить</a>
		<?}else{?>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="<?=url('account/clearError', array('id'=>$model->id))?>">стереть ошибку</a>
		<?}?>
	<?}?>
</p>


<p>Client : <?=$model->client->name?> (Cl<?=$model->client_id?>)</p>
<p>Group ID: <?=$model->group_id?></p>
<p>User: <?=$model->user->name?></p>
<p>Commission: <?=$model->commission?></p>
<p>Type: <?=$model->type?></p>
<p>Статус: <?=$status?></p>
<p>Прокси: <?=$proxy?></p>
<p>Браузер: <?=$browser?></p>
<p>OutIp: <?=$outIp?></p>
<p>Почта: <?if($isEmail){?><font color="green">прикреплена</font><?}else{?><font color="red">не прикреплена</font><?}?></p>
<p>Comment: <?=$model->comment?></p>
<p>
	<?if($smsEnabled === true){?>
		<font color="red">включена смс</font>
	<?}elseif($smsEnabled === false){?>
		<font color="green">смс отключена</font>
	<?}else{?>
		<font color="red">ошибка проверки смс</font>
	<?}?>
</p>

<p>
	<?if($smsPaymentEnabled === true){?>
		<font color="red">включены смс-платежи</font>
	<?}elseif($smsPaymentEnabled === false){?>
		<font color="green">смс-платежи отключены</font>
	<?}else{?>
		<font color="red">ошибка проверки смс-платежей</font>
	<?}?>
</p>

<hr>

<h2>Платежи (<?=count($transactions)?>)</h2>

	<?if($transactions){?>

		<p>
			<b>Сумма входящих(за сегодня):</b> <?=formatAmount($stats['today_amount'], 0)?> руб<br><br>
			<b>Сумма исходящих(за сегодня):</b> <?=formatAmount($stats['today_amount_out'], 0)?> руб<br><br>
			<b>Сумма входящих(за месяц):</b> <?=formatAmount($stats['month_amount'], 0)?> руб<br><br>
			<b>Сумма исходящих(за месяц):</b> <?=formatAmount($stats['month_amount_out'], 0)?> руб<br><br>
			<b>Комиссия всего:</b> <?=formatAmount($stats['commission_amount'], 0)?> руб<br><br>
			<b>Успешных платежей:</b> <?=formatAmount($stats['all_count'], 0)?> <br><br>
			<b>Сумма входящих:</b> <?=formatAmount($stats['in_amount'], 0)?> руб<br><br>
			<b>Сумма исходящих:</b> <?=formatAmount($stats['out_amount'], 0)?> руб<br><br>
			<b><span class="dotted" title="Уникальных киви-отправителей, киви-получателей(новая комса)">
					Уникальных кошельков(за сегодня):
				</span></b> <?=$stats['today_wallets_count']?>
			<br><br>
			<b>Уникальных за вчера:</b> <?=formatAmount($stats['yesterday_wallets_count'], 0)?><br><br>
		</p>

		<table class="std padding">
			<tr>
				<td>ID</td>
				<td>Тип</td>
				<td>Сумма</td>
				<td>Кошелек</td>
				<td>Дата</td>
				<td>Коммент</td>
				<td>Ошибка</td>
			</tr>

			<?foreach($transactions as $trans){?>
				<tr
					<?if($trans['status']==Transaction::STATUS_ERROR){?>
						class="error" title="<?=$trans['error']?>"
					<?}elseif($trans['status']==Transaction::STATUS_WAIT){?>
						class="wait" title="Не подтвержден"
					<?}else{?>
						class="<?=$trans['status']?>"
					<?}?>
				>
					<td><?=$trans['id']?></td>
					<td><?=$trans['type']?></td>
					<td>
						<?=formatAmount($trans['amount'], 2)?> <?=(isset($trans['currency'])) ? $trans['currency'] : '' ?>

						<?if($trans['commission'] > 0){?>
							<br>(<font title="комиссия" color="red">-<?=$trans['commission']?></font>)
						<?}?>
					</td>
					<td><?=$trans['wallet']?></td>
					<td><?=date('d.m.Y H:i:s', $trans['timestamp'])?></td>
					<td>
						<?if($trans['operationType'] == QiwiBot::OPERATION_TYPE_CONVERT){?>
							convert:<br><?=formatAmount($trans['amountFrom'], 2).' '.$trans['currencyFrom']?>
						<?}else{?>
							<?=$trans['comment']?>
						<?}?>
					</td>
					<td><?=$trans['error']?></td>
				</tr>
			<?}?>
		</table>

<?}else{?>
	нет платежей
<?}?>


