<?$this->title = 'Перевод средств'?>

<h2><?=$this->title?></h2>

<h5>Сейчас на кошельках: <?=$outAmount?> руб</h5>

<?if($isGlobalFin){?>
	<p style="color: red">На клиенте включен globalFin</p>
<?}?>

<form method="post">
	
	<p>
		<p><strong>Список переводов: (каждый с новой строки)</strong></p>
		<p style="font-style: italic;">
		пример: <br />
		+79994527364;23444.21<br />
		+79494527364;111<br />
		+79494527364;111;flash - приоритетный платеж<br />
		</p>
		<textarea name="params[transContent]" cols="55" rows="10"><?=$params['transContent']?></textarea>

		<div class="payButtons">
			<input type="submit" name="setAmount25" value="25 000" class="blue">

			<input type="submit" name="setAmount50" value="50 000" class="orange">

			<input type="submit" name="setAmount100" value="100 000" class="green">

			<input type="text" name="setAmountValue" value="<?=$setAmountValue?>"/><input type="submit" name="setAmountCustom" value="ok" class="custom">
		</div>
	</p>
	
	<p>
		<strong>Комментарий:</strong><br />
		<input type="text" name="params[comment]" value="<?=$params['comment']?>" />
	</p>
	
	<p>
		<strong>Платежный пароль:</strong><br />
		<input type="password" name="params[extra]" value="<?=$params['extra']?>" />
	</p>
	
	<p>
		<input type="submit" name="add" value="Отправить" />
	</p>
	
</form>

&nbsp;