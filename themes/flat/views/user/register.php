<?
/**
 * @var UserController $this
 * @var array $params
 */
$this->title = 'Регистрация';
?>
<?/*

<form method="post">


	<p>
		<strong>Тип пользователя</strong><br />

		<?if($this->isAdmin()){?>
			<select name="params[role]">
				<?foreach(User::roleArr() as $key=>$val){?>
					<option value="<?=$key?>"<?if($key==$params['role']){?> selected="selected"<?}?>><?=$val?></option>
				<?}?>
			</select>
		<?}else{?>
			менеджер
		<?}?>
	</p>

	<p>
		<strong>Пароль</strong><br />
		<input type="text" name="params[pass]" value="<?=$params['pass']?>" />
	</p>
	
	<p>
		<strong>Имя</strong><br />
		<input type="text" name="params[name]" value="<?=$params['name']?>" />
	</p>
	
	
	
	<p>
		<input type="submit" name="register" value="Зарегистрировать" />
	</p>

</form>
*/?>

<div class="box box-bordered">
	<div class="box-title">
		<h3><i class="fa fa-bars"></i>Новый пользователь</h3>
	</div>
	<div class="box-content">
		<form method="post" class="form-vertical form-bordered">

			<?if($this->isAdmin()){?>
				<div class="form-group">
					<label for="field1" class="control-label">Клиент</label>
					<select name="params[client_id]" class="form-control" id="field1">
						<option value="">НЕ ВЫБРАНО</option>
						<?foreach(Client::getArr() as $key=>$val){?>
							<option value="<?=$key?>"<?if($key==$params['client_id']){?> selected="selected"<?}?>><?=$val?></option>
						<?}?>
					</select>
				</div>
			<?}?>

			<div class="form-group">
				<label for="field2" class="control-label">Логин</label>
				<input type="text" name="params[login]" value="<?=$params['login']?>" id="field2" class="form-control"/>
				<span class="help-block">
					обязательный параметр
				</span>
			</div>

			<div class="form-group">
				<label for="field3" class="control-label">Тип пользователя: </label>
				<?if($this->isAdmin()){?>
					<select name="params[role]" id="field3" class="form-control">
						<?foreach(User::roleArr() as $key=>$val){?>
							<option value="<?=$key?>"<?if($key==$params['role']){?> selected="selected"<?}?>><?=$val?></option>
						<?}?>
					</select>
				<?}else{?>
					менеджер
				<?}?>
			</div>

			<div class="form-group">
				<label for="field4" class="control-label">Пароль</label>
				<input type="text" name="params[pass]" value="<?=$params['pass']?>" id="field4" class="form-control"/>
			</div>

			<div class="form-group">
				<label for="field5" class="control-label">Имя</label>
				<input type="text" name="params[name]" value="<?=$params['name']?>" id="field5" class="form-control"/>
			</div>

			<div class="form-actions">
				<button type="submit" class="btn btn-primary" name="register" value="register">Зарегистрировать</button>
				<a href="<?=url('user/list')?>"><button type="button" class="btn">Отмена</button></a>
			</div>
		</form>
	</div>
</div>
