<?
/**
 * @var ProxyController $this
 * @var bool $replaceEnabled
 * @var Proxy[] $models
 */
$this->title = 'Список прокси'
?>

<h1><?=$this->title?></h1>

<p><a href="<?=url('proxy/account')?>">Привязка прокси</a> </p>

<?if($models){?>

	<p>
		<form method="post">
			<input type="submit" name="clearAllStats" value="Очистить всю статистику"/>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
			<input type="submit" name="toggleReplace"
			<?if($replaceEnabled){?>
				value="Откл. автозамену"
			<?}else{?>
				value="Вкл. автозамену"
			<?}?>
			title="Включает-отключает автозамену дохлых прокси"
			/>
		</form>
	</p>

	<table class="std padding">
		<tr>
			<?$model = current($models)?>

			<td><?=$model->attributeLabel('id')?></td>
			<td>Прокси</td>
			<td>Стабильность</td>
			<td>Последний запрос</td>
			<td><?=$model->attributeLabel('external_ip')?></td>
			<td>Категория</td>
			<td>Кол-во аккаунтов</td>
			<td>Дата ребута</td>
			<td>Действие</td>
		</tr>

		<?foreach($models as $model){?>
			<tr>
				<td><?=$model->id?></td>
				<td><?=$model->strShort?></td>
				<td><?=$model->ratingStr?></td>
				<td><?=$model->lastResponseStr?></td>
				<td>
					<?=$model->external_ip?>

					<?/*дубликаты внешних ip*/?>
					<?if($model->externalIpDublicateCount > 0){?>
						(повторов: <?=$model->externalIpDublicateCount?>)
					<?}?>

				</td>
				<td><?=$model->comment?></td>
				<td><?=$model->accountCount?></td>
				<td><?=$model->resetDateStr?></td>
				<td>
					<form action="" method="post">
						<input type="hidden" name="id" value="<?=$model->id?>"/>
						<input type="submit" name="clearStats" value="Очистить статистику"><br>

						<?if($model->reset_url){?>
							<br>
							<input type="submit" name="reboot" value="Перезагрузить" class="green"><br>
						<?}?>
					</form>
				</td>
			</tr>
		<?}?>

	</table>
<?}?>
