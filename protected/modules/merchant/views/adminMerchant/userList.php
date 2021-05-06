<?php
/**
 * @var MerchantUser[] $models
 */
$this->title = 'Список пользователей мерчанта';
?>

<fieldset>
	<legend>Добавить пользователя</legend>
	<form method="post" action="">
		<p>
			<strong>Client</strong><br>
			<label>
				<?=CHtml::dropDownList('client_id','',
					Client::getArr(),
					array(
						'prompt'=>'Select Client',
						'ajax' => array(
							'type'=>'POST',
							'url'=>Yii::app()->createUrl('merchant/adminMerchant/loadUsers'),
							'update'=>'#user_id',
							'data'=>array('client_id'=>'js:this.value'),
						)));?>
			</label>
		</p>
		<p>
			<strong>User</strong><br>
			<label>
				<?=CHtml::dropDownList('user_id','', array(), array('prompt'=>'Select User'));?>
			</label>
		</p>
		<p>
			<strong>email</strong><br>
			<input type="text" name="params[email]" value="<?=$params['email']?>"/>
		</p>
		<p>
			<input type="submit" name="add" value="Добавить"/>
		</p>
	</form>
</fieldset>
<br>

<?if($models){?>
	<table id="table" class="std padding">
		<tr>
			<th>Client</th>
			<th>User</th>
			<th> internal userId </th>
			<?/*<th> Логин </th>*/?>
			<th> email </th>
			<th>кол-во кошей</th>
			<?/*<th>Действие</th>*/?>
		</tr>

		<?foreach($models as $model){?>
			<tr>
				<td><?=$model->client->name.'(ID='.$model->client->id.')'?></td>
				<td><?=$model->user->name?></td>
				<td><nobr><?=$model->internal_id?></nobr></td>
				<?/*<td><?=$model->login?></td>*/?>
				<td><?=$model->email?></td>
				<td><a href="<?=url('merchant/adminMerchant/userWalletList',['merchantUserId'=>$model->id])?>" target="_blank"><?=$model->walletCount?></a></td>
				<?/*<td>
					<form method="post" action="">
						<input type="hidden" value="<?=$model->id?>" name="id">
						<input type="submit" name="assingWallet" value="Назначить кошелек">
					</form>

					<br><a href="<?=url('qiwi/main/userList', ['deleteUserId'=>$model->internal_id])?>"><button type="button" title="Удалить юзера">Удалить</button></a>
				</td>*/?>
			</tr>
		<?}?>
	</table>
<?}else{?>
	Нет записей
<?}?>
<br>


