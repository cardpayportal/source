<?
/**
 * @var IntellectAccount[] $models
 * @var array $params
 * @var array $stats
 */
$this->title = 'Аккаунты Intellect Money'

?>

<h1><?=$this->title?></h1>

<form method="post">

	<p>
		<b>Аккаунты, каждый с новой строки</b>( internalId | email | password | formId | pinCode )<br>
		<textarea cols="80" rows="10" name="params[accounts]"><?=$params['accounts']?></textarea>
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
						'url'=>Yii::app()->createUrl('intellectMoney/admin/loadUsers'),
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
				<th>internalId</th>
				<th>dayLimit</th>
				<th>monthLimit</th>
				<th>Баланс</th>
				<th>Почта</th>
				<th>Пароль</th>
				<th>formId</th>
				<th>pinCode</th>
				<th>Клиент</th>
				<th>Юзер</th>
				<th>Добавлен</th>
				<th>Действие</th>
			</tr>
		</thead>

		<tbody>
			<?foreach($models as $model){?>
				<tr>
					<td><?=$model->id?></td>
					<td><b><?=$model->internal_account_id?></b></td>
					<td><b><?=$model->limitInDayStr?></b></td>
					<td><b><?=$model->limitInMonthStr?></b></td>
					<td><b><?=$model->balance?></b></td>
					<td><b><?=$model->email?></b></td>
					<td><?=$model->pass?></td>
					<td><?=$model->form_id?></td>
					<td><?=$model->pin_code?></td>
					<td><?=$model->client->name?></td>
					<td><?=$model->user->name?></td>
					<td><?=date('d.m.Y', $model->date_add)?></td>
					<td></td>
				</tr>
			<?}?>
		</tbody>

	</table>

<?}?>
