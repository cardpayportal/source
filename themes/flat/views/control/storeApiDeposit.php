<?
/**
 * @var ControlController $this
 * @var float $btcRate
 * @var string|false $depositAddress
 * @var int $changeAddressCount кол-во возможных смен адреса
 */

$this->title = 'Пополнение кошелька StoreApi';
?>

	<p>
		<?=$this->renderPartial('_storeApiMenu')?>
	</p>

<p><i>На этой странице можно сменить курс BTC, по которому производятся расчеты при выводе магазинам.</i></p>
<p><i>Введите курс, по которому покупалась последняя партия BTC/USD, затем переведите BTC на адрес, который получите</i></p>
<p><i>Не меняйте курс. пока на кошельке не закончится BTC по старому курсу</i></p>


<?if($depositAddress){?>
	<label>
		Адрес для пополнения:<br>
		<input class="form-control click2select" type="text" size="41" value="<?=$depositAddress?>">
		(курс: <?=$btcRate?> USD)
	</label>
<?}else{?>
	<div class="box box-bordered">
		<div class="box-title">
			<h3><i class="fa fa-bars"></i>Новая заявка</h3>
		</div>
		<div class="box-content">
			<form method="post" class="form-vertical form-bordered">
				<div class="form-group">
					<label for="textfield1" class="control-label">Курс BTC</label>
					<input type="text" name="params[btcRate]" value="<?=$btcRate?>" class="form-control" id="textfield1">
				</div>

				<div class="form-group">
					<div class="checkbox">
						<label>
							<input type="checkbox" name="params[changeAddress]" value="true"/>
							Сменить адрес
						</label>
					</div>
					<span class="help-block">
						(<i>Сменить адрес можно еще: <?=$changeAddressCount?> раз</i>)
					</span>
				</div>

				<div class="form-actions">
					<button type="submit" class="btn btn-primary" name="submit" value="Сохранить курс / получить адрес">Сохранить курс / получить адрес</button>
					<a href="<?=url('control/storeApi')?>"><button type="button" class="btn">Отмена</button></a>
				</div>
			</form>
		</div>
	</div>
<?}?>

