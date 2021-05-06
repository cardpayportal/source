<?
/**
 * @var YandexAccount[] $models
 * @var array $params
 * @var array $stats
 */
$this->title = 'Яндекс кошельки'

?>

<h1><?=$this->title?></h1>

<form method="post">

	<p>
		<b>Токены</b>(каждый с новой строки | номер карты)<br>
		<textarea cols="55" rows="10" name="params[wallets]"><?=$params['wallets']?></textarea>
	</p>
	<p>
		<strong>Клиент</strong><br>
		<label>
			<?=CHtml::dropDownList('clientId','',
				Client::getArr(),
				array(
					'prompt'=>'Select Client',
					'ajax' => array(
						'type'=>'POST',
						'url'=>Yii::app()->createUrl('yandexAccount/admin/loadUsers'),
						'update'=>'#userId',
						'data'=>array('clientId'=>'js:this.value'),
					)));?>
		</label>
	</p>
	<p>
		<strong>User</strong><br>
		<label>
			<?=CHtml::dropDownList('userId','', array(), array('prompt'=>'Select User'));?>
		</label>
	</p>

	<p>
		<input type="submit" name="add" value="Добавить">
	</p>
</form>

<?if($models){?>

	<br>
	<hr>
	<br>

	<table class="std padding">

		<thead>
			<tr>
				<th>ID</th>
				<th>Кошелек</th>
				<th>Карта</th>
				<th>Баланс</th>
				<th>Ошибка</th>
				<th>Выведено за месяц</th>
				<th>Принято за месяц</th>
				<th>Лимит In</th>
				<th>Клиент</th>
				<th>Юзер</th>
				<th>Проверен</th>
				<th>Добавлен</th>
				<th>Действие</th>
			</tr>
		</thead>

		<tbody>
			<? $timestampStart = Tools::startOfMonth(); ?>
			<?foreach($models as $model){?>
				<tr <?if($model->error){?> class=error<?}?>>
					<td><?=$model->id?></td>
					<td><b><?=$model->wallet?></b></td>
					<td><b><?=$model->cardNumberStr?></b></td>
					<td><?=formatAmount($model->balance, 0)?></td>
					<td><?=$model->error?></td>
					<td><?=formatAmount($model->OutAmountMonth, 0)?></td>
					<td><?=formatAmount($model->InAmountMonth, 0)?></td>
					<td><?=$model->LimitInMonthStr?></td>
					<td><?=$model->client->name?></td>
					<td><?=$model->user->name?></td>
					<td><?=$model->dateCheckStr?></td>
					<td><?=date('d.m.Y', $model->date_add)?></td>
					<td>
						<form method="post">
							<input type="hidden" name="id" value="<?=$model->id?>">

							<?if($model->hidden){?>
								<button type="submit" name="toggleHidden" class="orange" value="Отменить скрытие" title="Возвращает кошелек в общий список видимых">
									<i class="fa fa-eye"></i>Отменить скрытие
								</button>
							<?}else{?>
								<button type="submit" name="toggleHidden" class="green" value="Скрыть кошелек" title="Скрывает кошелек из списка видимых, помещает в список скрытых">
									<i class="fa fa-eye-slash"></i>скрыть кошелек
								</button>
							<?}?>
							<br>
							<br>
							<input type="submit" name="updateTransactions" value="обновить с <?=date('d.m.Y H:i', $timestampStart)?>"
								   title="обновит платежи с <?=date('d.m.Y H:i', $timestampStart)?>">
							<input type="hidden" name="timestampStart" value="<?=$timestampStart?>">
						</form>
					</td>
				</tr>
			<?}?>
		</tbody>

	</table>

<?}?>
