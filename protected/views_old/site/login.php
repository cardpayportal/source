<?
	$this->title = 'Вход';
	$this->layout = 'auth';
?>

<h2><?=$this->title?></h2>

<form method="post">

	<p>
		<strong>Логин</strong><br />
		<input id="login" type="text" name="params[login]" value="<?=$params['login']?>" tabindex="1" />
	</p>
	
	<p>
		<strong>Пароль</strong><br />
		<input type="password" name="params[pass]" value="" tabindex="2" />
	</p>
	
	<p>
		<input type="submit" name="sign_in" value="Войти" tabindex="4" />
	</p>

</form>

<script>
	$('#login').focus();
</script>