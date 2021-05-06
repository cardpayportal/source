<?php
/**
 *
 */
$this->title = 'Список доступных для привязки кошей';
?>

<br>
<form action="" method="post" name="">
	<p>
		<label>
			<strong>Добавить карту киви ( формат: +7XXXXXXXXXX-НОМЕР_КАРТЫ_БЕЗ_ПРОБЕЛОВ)</strong><br/>
			<br>
			<textarea style="width: 700px" rows="12" name="params[qiwiCardStr]"><?=$qiwiCardStr ?></textarea>
		</label>
	</p>

	<p>
		<input type="submit" name="addQiwiCardStr" value="Добавить"/>
	</p>

</form>
<br>

<?if($wallets){?>
	<table id="table" class="std padding">
		<tr>
			<th>ID</th>
			<th>Номер</th>
			<th>Название</th>
			<th>Баланс</th>
			<th>Дата</th>
		</tr>

		<?foreach($wallets as $wallet){?>
			<tr>
				<td><?=$wallet['id']?></td>
				<td><?=$wallet['wallet']?></td>
				<td><?=$wallet['wallet_name']?></td>
				<td><?=$wallet['balance']?></td>
				<td><?=$wallet['date']?></td>

			</tr>
		<?}?>
	</table>
<?}else{?>
	Нет записей
<?}?>



