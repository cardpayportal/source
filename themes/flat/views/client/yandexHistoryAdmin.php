<?php
/**
 * @var array $history
 * @var string $user
 * @var string $wexAccountId
 * @var string $yandex
 * @var string $exchange
 * @var string $out
 * @var string $error
 */
$this->title = 'История Яндекс '.$user;
?>

<p>
	<h2><?=$user?></h2>
</p>

<strong>Платежи (<?=count($history)?>)</strong><br>

<?if($history){?>
	<fieldset>
		<legend>Выберите тип отображаемых платежей</legend>
		<form method="post" action="" name="filter">
			<div>
				<input type="checkbox" id="yandex" name="yandex"
					   value="yandex" <?if($yandex){?>checked="checked"<?}?> />
				<label for="yandex">Яндекс</label>
			</div>

			<div>
				<input type="checkbox" id="exchange" name="exchange"
					   value="exchange" checked="checked" />
				<label for="exchange">Обмен</label>
			</div>

			<div>
				<input type="checkbox" id="out" name="out"
					   value="out"  checked="checked" />
				<label for="out">Вывод</label>
			</div>

			<div>
				<input type="checkbox" id="error" name="error"
					   value="error"  checked="checked" />
				<label for="error">Неподтвержденный вывод</label>
			</div>
			<br>
			<input type="submit" name="show" value="Отобразить">
		</form>
	</fieldset>
	<br>
	<strong>страница <?=$pageNum?></strong>
	<br>
	<div class="navigation">
	<?for($i = 1; $i <= $history[0]['pageCount']; $i++){?>
		<a href="<?=url('client/yandexHistoryAdmin', ['wexAccountId'=>$wexAccountId, 'user'=>$user, 'pageNum'=>$i])?>"><?=$i?></a>&nbsp
	<?}?>
	</div>
	<br>
	<table class="std padding">

		<?foreach($history as $trans){?>
			<!--если установлен фильтр на яндекс платежи-->
			<?if(($yandex && $trans['comment'] == 'Payment from Yandex.Money') ||
				//если установлен фильтр на обмен
				($exchange && $trans['type'] == 'Расход') ||
				//если установлен фильтр на вывод
				($out && $trans['type'] == 'Вывод' && $trans['status'] == 'Завершено') ||
				//если установлен фильтр на неподтвержденный вывод
				($error && $trans['type'] == 'Вывод' && $trans['status'] == 'Не подтверждено')
			){?>
				<tr
					<?if($trans['type'] == 'Вывод' && $trans['status'] == 'Не подтверждено'){?>
						class="error"
					<?}elseif($trans['type'] == 'Вывод' && $trans['status'] == 'Завершено'){?>
						class="new"
					<?}elseif($trans['type'] == 'Расход'){?>
						class="wait"
					<?}else{?>
						class="success"
					<?}?>
				>
					<td>#<?=$trans['id']?></td>
					<td><?=$trans['amount']?> <?=$trans['currency']?></td>
					<td><?=$trans['type']?></td>
					<td><?=date('d.m.Y H:i', $trans['date'])?></td>
					<td>
						<?if($trans['type'] == 'Вывод' && $trans['status'] == 'Не подтверждено'){?>
							<?=str_replace([
								'Отменить',
								'Прислать письмо еще раз',
							],
							[
								'<a href="'.url("client/YandexHistoryAdmin", ["user"=>$user,"wexAccountId"=>$wexAccountId, "transactionId"=>$trans["id"]]).'"> Отменить</a>',
								'<a href="'.url("client/YandexHistoryAdmin", ["user"=>$user,"wexAccountId"=>$wexAccountId, "transactionId"=>$trans["id"]]).'"> Прислать письмо еще раз</a>'
							],
								$trans['comment']
							);?>
						<?}else{?>
							<?=$trans['comment']?>
						<?}?>
						<?if($trans['txid']){?>
							<br>
							<span class="shortContent"><button><nobr>Copy TXID</nobr></button></span>
							<input style="display: none" type="text" size="30" value="<?=$trans['txid']?>" class="click2select fullContent">
						<?}?>
					</td>
				</tr>
			<?}?>
		<?}?>

	</table>
<?}else{?>
	нет платежей
<?}?>

<script>

</script>