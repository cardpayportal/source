<?php
/**
 * @var ControlController $this
 * @var array $config
 * @var array $params
 */

$this->title = 'Настройки';
?>

<h1><?=$this->title?></h1>

<form method="post">

	<p>
		<b>Источник курса BTC_USD</b><br>
		<select name="params[btc_usd_rate_source]">

			<?foreach(ClientCalc::getBtcUsdRateSourceArr() as $key=>$name){?>
				<option value="<?=$key?>"
				<?if($key == $config['btc_usd_rate_source']){?> selected<?}?>
				><?=$name?></option>
			<?}?>

		</select>

	</p>
	<input type="submit" name="save" value="Сохранить">
</form>


