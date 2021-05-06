<?
/**
 * @var ControlController $this
 * @var Transaction[] $lastTransactions
 * @var string $badWordsContent
 * @var array $stats
 *
 */

$this->title = 'Монитор комментов';

?>

<h1><?=$this->title?></h1>

<h2>Последние платежи</h2>

<?if($lastTransactions){?>
	<i>за 24 часа, ограничение отображения: 1000</i>
	<p>
		Всего: <b><?=count($lastTransactions)?></b>, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		Bad comment: <b><?=$stats['badCommentCount']?></b>
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

		<?foreach($lastTransactions as $trans){?>
			<tr class="<?=($trans->isBadComment) ? 'error' : ''?>">
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
				<td><nobr><?=$trans->amountStr?></nobr></td>
				<td><?=$trans->statusStr?></td>
				<td><?=date(cfg('dateFormat'), $trans->date_add)?></td>
				<td><?=$trans->qiwi_id?></td>
			</tr>
		<?}?>

	</table>

<?}else{?>
	платежей не найдено
<?}?>

<br>
<br>

<h2>Запрещенные слова</h2>

<form method="post">
	<p>
		<b><i>Регулярные выражения (по одному на строку), пр: !comment.+!</i></b><br>
		<textarea cols="45" rows="15" name="params[badWordsContent]"><?=$badWordsContent?></textarea>
	</p>

	<p><input type="submit" name="save" value="Сохранить"></p>
</form>
