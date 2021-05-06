<?
/**
 * @var FinansistOrder[] $models
 * @var array $warnings
 * @var array $selectedWallets
 * @var array $filter
 */
$this->title = 'Список переводов ('.count($models).')';

?>

<div class="box">
	<?foreach($warnings as $msg){?>
		<p class="error"><?=$msg?></p>
	<?}?>

	<p>
		<a href="<?=url('finansist/globalOrderAdd')?>">
			<button class="btn btn-primary btn--icon">
				<i class="fa fa-plus"></i>Добавить
			</button>
		</a>
	</p>

	<?if($selectedWallets){?>
		<div id="checkArea">
			<b>Выбранные кошельки:</b> (<?=count($selectedWallets)?>)<br>
			<textarea rows="<?=(count($selectedWallets) + 2)?>" cols="15"><?foreach($selectedWallets as $wallet){?><?=$wallet,"\r\n"?><?}?></textarea>
		</div>
	<?}?>
</div>

<div class="box box-bordered">
	<div class="box-title">
		<h3><i class="fa fa-filter"></i>фильтр переводов</h3>
	</div>
	<div class="box-content nopadding">
		<form method="post" class="form-vertical form-bordered">
			<div class="form-group">
				<label for="textfield1" class="control-label col-sm-2">Клиент</label>
				<select name="filter[clientId]" class="form-control" id="textfield1">
					<option value="">Все</option>
					<?foreach(Client::getArr() as $id=>$name){?>
						<option value="<?=$id?>"
							<?if($filter['clientId']==$id){?>
								selected="selected"
							<?}?>
						><?=$name?></option>
					<?}?>
				</select>
			</div>
			<div class="form-group">
				<label for="textfield2" class="control-label col-sm-2">Кому</label>
				<input type="text" name="filter[to]" value="<?=$filter['to']?>" class="form-control" id="textfield2"/>
				<span class="help-block">фильтр по номеру получателя</span>
			</div>

			<div class="form-group">
				<label for="textfield3" class="control-label col-sm-2">Дата С</label>
				<input type="text" name="filter[date_start]" value="<?=$filter['date_start']?>" class="form-control" id="textfield3"/>
			</div>

			<div class="form-group">
				<label for="textfield4" class="control-label col-sm-2">Дата По</label>
				<input type="text" name="filter[date_end]" value="<?=$filter['date_end']?>" class="form-control" id="textfield4"/>
			</div>

			<div class="form-actions">
				<button type="submit" class="btn btn-primary" name="stats" value="Фильтровать">Фильтровать</button>
			</div>
		</form>
	</div>
</div>

<div class="box box-bordered">
	<div class="box-title">
		<h3>
			<i class="fa fa-bars"></i>
			Всего переводов: <?=formatAmount($info['order_count'])?> (<?=formatAmount($info[amount_send], 0)?> руб.),
			&nbsp;&nbsp;
			В ожидании: <?=$info['wait_count']?>
			&nbsp;&nbsp;
			С ошибкой: <?=$info['error_count']?>
		</h3>
	</div>
	<div class="box-content">
		<form method="post">
			<br>
			<button type="submit" name="selectWallets" class="btn btn-small btn-teal btn--icon" title="Если отмечены заявки то выдает из них, если не отмечены то выдает все залитые" value="Выбрать кошельки">
				Выбрать кошельки
			</button>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
			<button type="submit" name="selectWalletsComplete" class="btn btn-small btn-lime btn--icon" title="Выбрать все кошельки с завершенных заявок, которые еще небыли отмечены" value="Выбрать завершенные">
				Выбрать завершенные
			</button>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
			<a href="<?=url('finansist/selectHistory')?>">
				<button type="button" class="btn btn-small btn-teal btn--icon">
					история
				</button>
			</a>
			<br>

			<table class="table table-bordered table-colored-header">
				<thead>
					<th>ID <input type="checkbox" id="checkAll"></th>
					<th>Клиент</th>
					<th>Кому</th>
					<th>Сумма</th>
					<th>Статус</th>
					<th>Отправлено</th>
					<th>Дата добавления</th>
					<th>Автор</th>
					<th>Цепочка</th>
				</thead>

				<tbody>
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
				</tbody>
			</table>

			<br>
			<button type="submit" name="selectWallets" class="btn btn-small btn-teal btn--icon" title="Если отмечены заявки то выдает из них, если не отмечены то выдает все залитые" value="Выбрать кошельки">
				Выбрать кошельки
			</button>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
			<a href="<?=url('finansist/selectHistory')?>">
				<button type="button" class="btn btn-small btn-teal btn--icon">
					история
				</button>
			</a>

		</form>
	</div>
</div>

<script>
	setTimeout(function(){
		location.href = '<?=url('finansist/globalOrderList')?>';
	}, 300000);

	$('#checkArea textarea').click(function(){
		$(this).select();
	});

</script>
