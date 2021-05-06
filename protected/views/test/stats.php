<?
/**
 * @var array stats
 * @var array params
 */
?>


<form method="post">
	<p>
		<b>Client</b><br>
		<select name="clientId">
			<?/*<option value="">Все</option>*/?>
			<?foreach(Client::clientList() as $id=>$name){?>
				<option value="<?=$key?>"><?=$name?></option>
			<?}?>
		</select>
		<input type="text" name="params[clientId]" value="<?=$params['clientId']?>">
	</p>

	<p>
		От <input type="text" name="params[dateStart]" value="<?=$params['dateStart']?>">
		До <input type="text" name="params[dateEnd]" value="<?=$params['dateEnd']?>">
	</p>

	<p>
		<input type="submit" name="submit" value="Показать">
	</p>
</form>
