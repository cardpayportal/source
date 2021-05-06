<?
/**
 * @var array $result
 */
?>

<?$this->title = 'Инструменты'?>

<h3>Информация об аккаунте</h3>

<form method="post">
	<p>
		<strong>Логин:</strong><br/>
		<input type="text" name="params[login]" value="<?=$params['login']?>">
	</p>

	<p>
		<input type="submit" name="accountInfo" value="Информация об аккаунте"/>
	</p>
</form>


<?if($accountInfo){?>
	<div>
		<table class="std padding">
			<tbody>
				<tr>
					<td>Логин</td>
					<td><?=$accountInfo['attributes']['login']?></td>
				</tr>

				<tr>
					<td>Статус</td>
					<td><?=$accountInfo['status']?></td>
				</tr>

				<tr>
					<td>Ошибка</td>
					<td>
						<?if($accountInfo['attributes']['error']){?>
							<font color="red"><?=$accountInfo['attributes']['error']?></font>
						<?}else{?>
							<font color="green">нет</font>
						<?}?>
					</td>
				</tr>

				<tr>
					<td>Персона</td>
					<td><?=$accountInfo['person']?></td>
				</tr>

				<tr>
					<td>Email</td>
					<td><?=$accountInfo['email']?></td>
				</tr>

				<tr>
					<td>Категория</td>
					<td><?=$accountInfo['attributes']['category']?></td>
				</tr>

				<tr>
					<td>Дата проверки</td>
					<td><?=$accountInfo['dateCheck']?></td>
				</tr>

				<tr>
					<td>Экспортирован</td>
					<td><?=$accountInfo['export']?></td>
				</tr>

				<tr>
					<td>Карта</td>
					<td><?=$accountInfo['card']?></td>
				</tr>
			</tbody>

		</table>

	</div>
<?}?>

<hr>

<h3>Обработать контент</h3>

<?if($result['content']){?>
	<b>Результат</b><br>
	<textarea cols="45" rows="10"><?=$result['content']?></textarea>
<?}?>

<form method="post">
	<p>
		<textarea name="params[content]" cols="55" rows="15"><?=$params['content']?></textarea>

		<?if($loginCondition){?>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
			<textarea cols="55" rows="15"><?=$loginCondition?></textarea>
		<?}?>
	</p>

	<p>
		<input type="submit" name="loginCondition" value="Логины (sql)">
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<input type="submit" name="cardCondition" value="Карты (sql)">
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<input type="submit" name="universalCondition" value="Универсально">
	</p>

	<p>
		<input type="text" name="params[var]" value="<?=$params['var']?>" placeholder="переменная"/>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<input type="text" name="params[modifier]" value="<?=$params['modifier']?>" placeholder="модификатор" title="left | right | both"/>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<input type="submit" name="likeCondition" value="Like">

	</p>

	<br><br>
	<hr>
	<br><br>

	<h2>Прокси</h2>
	<?if($result['proxy']){?>
		<p>
			<b>Результат</b><br>
			<textarea cols="45" rows="10"><?=$result['proxy']?></textarea>
		</p>
	<?}?>
	<p>
		<textarea cols="45" rows="10" name="params[proxyStr]"><?=$params['proxyStr']?></textarea><br>
		<b>Логин</b>:<br> <input type="text" name="params[proxyLogin]" value="<?=$params['proxyLogin']?>"><br>
		<b>Парол</b>:<br> <input type="text" name="params[proxyPass]" value="<?=$params['proxyPass']?>"><br>
		<b>Порт</b>:<br> <input type="text" name="params[proxyPort]" value="<?=$params['proxyPort']?>"><br>
		<br>
		<input type="submit" name="formatProxy" value="Форматировать прокси">
	</p>


</form>


<hr>

<h3>Timestamp</h3>

<?if($result['timestamp']){?>
	<?=$result['timestamp']?>
<?}?>

<form method="post">

	<p>
		<input type="text" name="params[timestampStr]" value="<?=$params['timestampStr']?>" />
	</p>

	<p>
		<input type="submit" name="timestamp" value="Timestamp"/>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<input type="submit" name="date" value="Date"/>
	</p>

</form>
