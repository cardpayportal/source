<?
	$this->title = 'Регистрация';
	$this->layout = 'main';

?>
<div class="center">
<h2><?=$this->title?></h2>

<form method="post">


	<?if($this->isAdmin()){?>
		<p>
			<strong>Клиент</strong><br />

			<select name="params[client_id]">
				<option value="">НЕ ВЫБРАНО</option>
				<?foreach(Client::getArr() as $key=>$val){?>
					<option value="<?=$key?>"<?if($key==$params['client_id']){?> selected="selected"<?}?>><?=$val?></option>
				<?}?>
			</select>
		</p>
	<?}?>

	<p>
		<strong>Логин</strong> (обязательно)<br />
		<input type="text" name="params[login]" value="<?=$params['login']?>" />
	</p>

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