<?
/**
 * @var string $url
 * @var array $methods
 * @var array $params
 */
?>

<p>
	Url = <?=$url?>
</p>

<form action="<?=$url?>" method="post" target="_blank">
	<p>
		<b>Ключ</b><br>
		<input name="key" value="<?=$key?>">
	</p>

	<p>
		<b>Метод</b><br>
		<select name="method">
			<?foreach($methods as $method){?>
				<option value="<?=$method?>">
					<?=$method?>
				</option>
			<?}?>
		</select>
	</p>

	<p>
		<b>Сумма</b><br>
		<input name="amount" value="5000">
	</p>

	<p>
		<b>Кошельки</b><br>
		<input name="wallets[]" value=""><br>
		<input name="wallets[]" value=""><br>
		<input name="wallets[]" value=""><br>
		<input name="wallets[]" value=""><br>
	</p>

	<p>
		<input type="submit" name="submit" value="Отправить">
	</p>
</form>