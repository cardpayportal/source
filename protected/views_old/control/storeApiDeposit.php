<?
/**
 * @var ControlController $this
 * @var float $btcRate
 * @var string|false $depositAddress
 */

$this->title = 'Пополнени кошелька StoreApi';
?>

<h1><?=$this->title?></h1>

	<p>
		<?=$this->renderPartial('_storeApiMenu')?>
	</p>

<p><i>На этой странице можно сменить курс BTC, по которому производятся расчеты при выводе магазинам.</i></p>
<p><i>Введите курс, по которому покупалась последняя партия BTC/USD, затем переведите BTC на адрес, который получите</i></p>
<p><i>Не меняйте курс. пока на кошельке не закончится BTC по старому курсу</i></p>


<?if($depositAddress){?>
	<h3>Адрес для пополнения: <input class="click2select" type="text" size="41" value="<?=$depositAddress?>"> (курс: <?=$btcRate?> USD)</h3>
<?}else{?>
<form method="post">

	<p>
		<b>Курс BTC</b><br>
		<input type="text" name="params[btcRate]" value="<?=$btcRate?>"> USD
	</p>

	<p>
		<input type="submit" name="submit" value="Сохранить курс / получить адрес">
	</p>

</form>
<?}?>