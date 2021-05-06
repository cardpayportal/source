<?
/**
 * @var ControlController $this
 * @var Transaction[] $badTransactions
 * @var array $stats
 *
 */

$this->title = 'Монитор платежей';

?>

<h1><?=$this->title?></h1>

<?if($badTransactions){?>
	<i>за 24 часа, ограничение вывода: 1000</i>
	<p>
		Всего: <b><?=$stats['count']?></b>
	</p>
	<table class="std padding">
		<tr>
			<td>Клиент</td>
			<td>Менеджер</td>
			<td>Кошелек</td>
			<td>Комментарий</td>
			<td>Сумма</td>
			<td>Статус</td>
			<td>Дата</td>
			<td>ID</td>
		</tr>

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

	</table>

<?}else{?>
	платежей не найдено
<?}?>
