<?
/**
 * @var ControlController $this
 * @var array $params
 * @var string $token
 *
 */

$this->title = 'Персональный токен';
?>

<h1>Получение персонального токена</h1>

<p>
	<a href="<?=url('control/apiTokenPersonal')?>">в начало</a>

</p>

<?if($token){?>
	<form method="post">
		<p>
			<b>Смс</b><br>
			<input type="text" name="params[sms]" value="<?=$params['sms']?>">
		</p>

		<p>
			<input type="submit" name="submitSms" value="Отправить">
		</p>
	</form>
<?}else{?>
	<form  method="post">
		<p>
			<b>Логин</b><br>
			<input type="text" name="params[login]" value="<?=$params['login']?>">
		</p>

		<p>
			<b>Пароль</b><br>
			<input type="text" name="params[pass]" value="<?=$params['pass']?>">
		</p>

		<p>
			<input type="submit" name="submitToken" value="Отправить">
		</p>
	</form>
<?}?>
