<?
/**
 * @var FinansistOrder[] $models
 * @var array $warnings
 * @var array $selectedWallets
 */
$this->title = 'Список переводов'

?>

<h2><?=$this->title?> (<?=count($models)?>)</h2>

<p>
	<a href="<?=url('finansist/globalOrderAdd')?>">Добавить</a>
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

<?foreach($warnings as $msg){?>
	<p class="error"><?=$msg?></p>
<?}?>


<form action="" method="post">
	<strong>Клиент:</strong>
	<select name="filter[clientId]">
		<option value="">Все</option>
		<?foreach(Client::getArr() as $id=>$name){?>
			<option value="<?=$id?>"
				<?if($filter['clientId']==$id){?>
					selected="selected"
				<?}?>
			><?=$name?></option>
		<?}?>
	</select>
	&nbsp;&nbsp;&nbsp;
	<strong>Телефон:</strong>
	<input type="text" name="filter[to]" value="<?=$filter['to']?>" style="width: 120px"/>
	&nbsp;&nbsp;&nbsp;
	<strong>Дата:</strong>
	с <input type="text" name="filter[date_start]" value="<?=$filter['date_start']?>" style="width: 120px"/>
	по <input type="text" name="filter[date_end]" value="<?=$filter['date_end']?>" style="width: 120px"/>
	&nbsp;&nbsp;&nbsp;
	<input type="submit" value="Фильтровать"/>
</form>

<?if($models){?>

	<?if($selectedWallets){?>
		<div id="checkArea">
			<b>Выбранные кошельки:</b> (<?=count($selectedWallets)?>)<br>
			<textarea rows="<?=(count($selectedWallets) + 2)?>" cols="15"><?foreach($selectedWallets as $wallet){?><?=$wallet,"\r\n"?><?}?></textarea>
		</div>
	<?}?>

	<form method="post">
		<br>
		<input class="selectWallets" type="submit" name="selectWallets" value="Выбрать кошельки" title="Если отмечены заявки то выдает из них, если не отмечены то выдает все залитые" >
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<input class="selectWalletsComplete" type="submit" name="selectWalletsComplete" value="Выбрать завершенные" title="Выбрать все кошельки с завершенных заявок, которые еще небыли отмечены" >
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<a href="<?=url('finansist/selectHistory')?>">история</a>
		<br>
		<table class="std padding">
			<tr>
				<td>ID <input type="checkbox" id="checkAll"></td>
				<td>Клиент</td>
				<td>Кому</td>
				<td>Сумма</td>
				<td>Статус</td>
				<td>Отправлено</td>
				<td>Дата добавления</td>
				<td>Автор</td>
				<td>Цепочка</td>
			</tr>

			<?foreach($models as $model){?>
				<tr
					<?if($model->date_select){?>
						class="selected"
						title="выбрано"
					<?}else{?>
						<?if($model->amount_send > $model->amount){?>
							class="orderOverPay" title="переплата на <?=formatAmount($model->amount_send - $model->amount)?>"
						<?}elseif($model->priority==FinansistOrder::PRIORITY_BIG){?>
							class="orderPriorityBig"
						<?}?>
					<?}?>
				>
					<td>
						<input type="checkbox" name="ids[]" value="<?=$model->id?>" class="check">
						<?=$model->id?>
					</td>
					<td><?=$model->client->name?></td>
					<td><?=$model->to?></td>
					<td>
						<?=$model->amountStr?> <br>руб

						<?if($model->comment){?>
							<span class="dotted" title="<?=$model->comment?>">комментарий</span>
						<?}?>
					</td>
					<td>
						<?=$model->statusStr?>
					</td>
					<td><?=$model->amountSendStr?><br> руб</td>
					<td><?=$model->dateAddStr?></td>
					<td><?=$model->userStr?></td>
					<td><?=$model->groupIdStr?></td>
				</tr>
			<?}?>
		</table>
		<input class="selectWallets" type="submit" name="selectWallets" value="Выбрать кошельки" title="Если отмечены заявки то выдает из них, если не отмечены то выдает все залитые" >
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<a href="<?=url('finansist/selectHistory')?>">история</a>
	</form>
<?}else{?>
	переводов не найдено
<?}?>

<script>
	setTimeout(function(){
		location.href = '<?=url('finansist/globalOrderList')?>';
	}, 300000);

	$('#checkArea textarea').click(function(){
		$(this).select();
	});

</script>

