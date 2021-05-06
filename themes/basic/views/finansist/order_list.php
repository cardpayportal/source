<?$this->title = 'Список переводов'?>

<h2><?=$this->title?></h2>

<p>
	<a href="<?=url('finansist/orderAdd')?>">Добавить</a>
</p>

<p>
	<strong>
		Всего переводов: <?=formatAmount($info['order_count'])?> (<?=formatAmount($info[amount_send], 0)?> руб.),
		&nbsp;&nbsp;
		В ожидании: <?=$info['wait_count']?>
		&nbsp;&nbsp;
		С ошибкой: <?=$info['error_count']?>
	</strong>
</p>


<p>
	Поступило за сегодня:<br />
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Фактических: <strong><?=formatAmount($stats['today']['fact_amount'], 0)?></strong><br />
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Запоздалых: <strong><?=formatAmount($stats['today']['late_amount'], 0)?></strong><br />
</p>

<p>
	Поступило за вчера:<br />
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Фактических: <strong><?=formatAmount($stats['yesterday']['fact_amount'], 0)?></strong><br />
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Запоздалых: <strong><?=formatAmount($stats['yesterday']['late_amount'], 0)?></strong><br />
</p>

<p>
	Поступило текущую неделю:<br />
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Фактических: <strong><?=formatAmount($stats['curWeek']['fact_amount'], 0)?></strong><br />
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Запоздалых: <strong><?=formatAmount($stats['curWeek']['late_amount'], 0)?></strong><br />
</p>

<?if($this->isFinansist() or $this->isAdmin()){?>
	<p>
		Поступило прошлую неделю:<br />
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Фактических: <strong><?=formatAmount($stats['lastWeek']['fact_amount'], 0)?></strong><br />
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Запоздалых: <strong><?=formatAmount($stats['lastWeek']['late_amount'], 0)?></strong><br />
	</p>
<?}?>


<p style="color: red;">
	Если в статусе написано: "В процессе"(желтым) и есть еще какое-то сообщение, то попытки перевести средства продолжаются.
	<br />
	Возможно у получателя достигнут максимальный баланс на кошельке
</p>

<p style="color: red;">
	Если написано "Ошибка"(красным), то перевод остановлен.
</p>

<p>
<form action="" method="post">
	<strong>Телефон:</strong>
	<input type="text" name="filter[to]" value="<?=$filter['to']?>"/>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<strong>Дата:</strong>
	с <input type="text" name="filter[date_start]" value="<?=$filter['date_start']?>"/>
	по <input type="text" name="filter[date_end]" value="<?=$filter['date_end']?>"/>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<input type="submit" value="Фильтровать"/>
</form>
</p>

<?if($models){?>
	<table class="std padding">
		<tr>
			<td>ID</td>
			<td>Кому</td>
			<td>Сумма</td>
			<td>Статус</td>
			<td>Отправлено</td>
			<td>Дата добавления</td>
			<td>Автор</td>
		</tr>

		<?foreach($models as $model){?>
			<tr
				<?if($model->amount_send > $model->amount){?>
					class="orderOverPay" title="переплата на <?=formatAmount($model->amount_send - $model->amount)?>"
				<?}elseif($model->priority==FinansistOrder::PRIORITY_BIG){?>
					class="orderPriorityBig"
				<?}?>
			>
				<td><?=$model->id?></td>
				<td><?=$model->to?></td>
				<td>
					<?=$model->amountStr?> руб

					<?if($model->comment){?>
						<span class="dotted" title="<?=$model->comment?>">комментарий</span>
					<?}?>
				</td>
				<td>
					<?=$model->statusStr?>
				</td>
				<td><?=$model->amountSendStr?> руб</td>
				<td><?=$model->dateAddStr?></td>
				<td>
					<?if($model->user->role == User::ROLE_GLOBAL_FIN){?>
						globalFin
					<?}else{?>
						<?=$model->userStr?>
					<?}?>
				</td>
			</tr>
		<?}?>
	</table>
<?}else{?>
	переводов не найдено
<?}?>

<script>
	setTimeout(function(){
		location.href = '<?=url('finansist/orderList')?>';
	}, 180000);
</script>
