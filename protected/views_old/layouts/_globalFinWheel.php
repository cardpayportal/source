<?
	$currentUser = User::getUser();

	if($_POST['dropWheel'])
	{
		if($currentUser->dropWheel())
			$this->success('Штурвал успешно отпущен');
		else
			$this->error('Ошибка: '.User::$lastError);
	}
	elseif($_POST['takeWheel'])
	{
		if($currentUser->takeWheel())
			$this->success('Штурвал успешно взят');
		else
			$this->error('Ошибка: '.User::$lastError);
	}

	$wheelUser = User::getWheelUser();

?>

<form method="post">
	<strong title="Управление переводами клиентов. Одновременно может быть только один GlobalFin">У штурвала: </strong>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<?if($wheelUser and $wheelUser->id == $currentUser->id){?>
		<font color="red"> <?=$wheelUser->name?></font>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<input type="submit" name="dropWheel" value="Отпустить штурвал">
	<?}else{?>
		<?if(!$wheelUser){?>
			<font color="green">никого</font>
		<?}else{?>
			<font color="red"> <?=$wheelUser->name?></font>
		<?}?>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<input type="submit" name="takeWheel" value="Взять штурвал">
	<?}?>
</form>