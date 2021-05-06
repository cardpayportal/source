<?
/**
 * @var TestCardModel[] $models
 * @var array           $params
 */
$this->title = 'Редактирование тестовой карты'

?>


<form method="post">

	<p><label>Номер кошелька</label></p>
	<p>
		<input type="text" name="params[wallet]" value="<?= $params['wallet'] ?>" placeholder="Номер кошелька"/>
	</p>
	<p><label>Номер карты</label></p>
	<p>
		<input type="text" name="params[cardNumber]" value="<?= $params['cardNumber'] ?>" placeholder="Номер карты"/>
	</p>
	<p><label>Баланс</label></p>
	<p>
		<input type="text" name="params[balance]" value="<?= $params['balance'] ?>" placeholder="Баланс"/>
	</p>
	<p><label>Общий лимит</label></p>
	<p>
		<input type="text" name="params[totalLimit]" value="<?= $params['totalLimit'] ?>" placeholder="Общий лимит"/>
	</p>
	<p><label>Статус</label></p>
	<p>
		<select name="params[status]">
			<option selected="selected" disabled="disabled">Статус</option>
			<option value="active">Активен</option>
			<option value="inactive">Не активен</option>
		</select>
	</p>
	<p><label>Клиент</label></p>
	<p>
		<label>
			<?=CHtml::dropDownList('client_id','',
				Client::getArr(),
				array(
					'prompt'=>'Select Client',
					'ajax' => array(
						'type'=>'POST',
						'url'=>Yii::app()->createUrl('testCard/admin/loadUsers'),
						'update'=>'#user_id',
						'data'=>array('client_id'=>'js:this.value'),
					)));?>
		</label>
	</p>
	<p><label>Юзер</label></p>
	<p>
		<label>
			<?=CHtml::dropDownList('user_id','', array(), array('prompt'=>'Select User'));?>
		</label>
	</p>
	<p>
		<input type="submit" name="editWallet" value="Обновить">
	</p>
</form>





