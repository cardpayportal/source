<?
/**
 * @var ControlController $this
 * @var Transaction[] $badTransactions
 * @var array $stats
 *
 */

$this->title = 'Монитор платежей';

?>

<?if($badTransactions){?>
	<i>за 24 часа, ограничение вывода: 1000</i>
	<p>
		Всего: <b><?=$stats['count']?></b>
	</p>
	<table class="table table-nomargin table-bordered table-colored-header">
		<thead>
			<th>Клиент</th>
			<th>Менеджер</th>
			<th>Кошелек</th>
			<th>Комментарий</th>
			<th>Сумма</th>
			<th>Статус</th>
			<th>Дата</th>
			<th>ID</th>
		</thead>
		<tbody>
			<?foreach($badTransactions as $trans){?>
				<tr>
					<td><?=$trans->account->client->name?></td>
					<td><?=$trans->user->name?></td>
					<td>
						<?if($trans->account->error){?>
							<span class="error"><b><?=$trans->account->login?> (<?=$trans->account->error?>)</b></span>
						<?}else{?>
							<b><?=$trans->account->login?></b>
						<?}?>
						<br>от<?=$trans->wallet?>

					</td>
					<td><?=$trans->comment?></td><?//todo: js-сокращение длины?>
					<td><?=$trans->amountStr?></td>
					<td><?=$trans->statusStr?></td>
					<td><?=date(cfg('dateFormat'), $trans->date_add)?></td>
					<td><?=$trans->qiwi_id?></td>
				</tr>
			<?}?>
		</tbody>

	</table>

<?}else{?>
	платежей не найдено
<?}?>
