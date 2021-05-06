<?
/**
 * @var NewYandexPay[] $model
 */

$this->title = 'Добавить к заявке данные карты или смс'
?>

<form method="post">
	<strong>поиск по номеру заявки (order_id):</strong>
	<p>
		<input type="text" name="searchStr" placeholder="введите комментарий или его часть"/>&nbsp
		<input type="submit" name="search" value="Поиск">
	</p>
</form>
<br><hr>

<?if($model){?>

	<form method="post" action="<?=url('test/accountCardInfo')?>">
		<strong>id заявки</strong>
		<p>
			<?=$model->id?>
		</p>
		<strong>Номер карты</strong>
		<p>
			<input type="text" name="params[card_no]" value="<?=$model->card_no?>"/>
		</p>
		<strong>Месяц</strong>
		<p>
			<input type="text" name="params[card_month]" value="<?=$model->card_month?>"/>
		</p>
		<strong>Год</strong>
		<p>
			<input type="text" name="params[card_year]" value="<?=$model->card_year?>"/>
		</p>
		<strong>Имя держателя карты (если есть)</strong>
		<p>
			<input type="text" name="params[card_name]" value="<?=$model->card_name?>"/>
		</p>
		<strong>CVV</strong>
		<p>
			<input type="text" name="params[cvv]" value="<?=$model->cvv?>"/>
		</p>
		<strong>SMS code</strong>
		<p>
			<input type="text" name="params[sms_code]" value="<?=$model->sms_code?>"/>
		</p>
		<p>
			<input type="submit" name="save" value="Сохранить"/>
		</p>
	</form>

<?}else{?>
	запись не найдена
<?}?>
