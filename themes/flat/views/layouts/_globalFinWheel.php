<?
	$currentUser = User::getUser();

	if(isset($_POST['dropWheel']))
	{
		if($currentUser->dropWheel())
			$this->success('Штурвал успешно отпущен');
		else
			$this->error('Ошибка: '.User::$lastError);
	}
	elseif(isset($_POST['takeWheel']))
	{
		if($currentUser->takeWheel())
			$this->success('Штурвал успешно взят');
		else
			$this->error('Ошибка: '.User::$lastError);
	}

	$wheelUser = User::getWheelUser();

?>

<li class='grey-4' title="Управление переводами клиентов. Одновременно может быть только один GlobalFin">
	<i class="fa fa-key"></i>
	<div class="details">
		<form method="post">
			<span>
				У штурвала:
				<?if($wheelUser and $wheelUser->id == $currentUser->id){?>
					<?=$wheelUser->name?>
				<?}else{?>
					<?if(!$wheelUser){?>
						никого
					<?}else{?>
						<?=$wheelUser->name?>
					<?}?>
				<?}?>
			</span>
			<span>
				<?if($wheelUser and $wheelUser->id == $currentUser->id){?>
					<button class="btn btn-warning btn-small" name="dropWheel">Отпустить штурвал</button>
				<?}else{?>
					<button class="btn btn-info btn-small" name="takeWheel">Взять штурвал</button>
				<?}?>
			</span>
			<span>
				<?$rate = ClientCalc::getCurrentBtcUsdRateSource()?>
				Текущий курс: <?=$rate['value']?>

				<?if($wheelUser and $wheelUser->id == $currentUser->id){?>
					(<a href="<?=url('control/config')?>" title="изменить"><?=$rate['name']?></a>)
				<?}else{?>
					(<?=$rate['name']?>)
				<?}?>

			</span>

		</form>
	</div>
</li>