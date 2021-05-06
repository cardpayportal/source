<?

/**
 * @var Account $account
 * @var array $params
 */
$this->title = 'Информация об аккаунте';

if($account)
	$this->title .= ' '.$account->login;
?>


<h1><?=$this->title?></h1>


<?if($account){?>
	<table class="std padding">
		<tr>
			<td>Логин</td>
			<td>Email</td>
			<td>Пароль</td>
		</tr>

		<tr>
			<td><?=$account->login?></td>
			<td>
				<?if($account->email){?>
					<span class="selectText"><?=$account->email->email?></span> (<a target="_blank" href="http://<?=$account->email->server?>"><?=$account->email->server?></a>)<br>
					<span class="selectText"><?=$account->email->pass?></span><br>
				<?}else{?>
					нет
				<?}?>
			</td>
			<?//отдавать пароль если забанен?>
			<td><?=(in_array($account->error, array(Account::ERROR_BAN, Account::ERROR_LIMIT_OUT, Account::ERROR_PASSWORD_EXPIRED))) ? $account->pass : 'пароль доступен только у заблокированных кошельков'?></td>
		</tr>
	</table>
<?}?>


<form method="post">
	<p>
		<strong>Логин</strong><br>
		<input type="text" name="params[login]" value="<?=$login?>"/>
	</p>

	<p>
		<input type="submit" name="submit" value="Найти">
	</p>

</form>

