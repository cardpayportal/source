<?
/**
 * @var ProxyController $this
 * @var bool $replaceEnabled
 * @var Proxy[] $models
 * @var array $params
 * @var array $stats
 */
$this->title = 'Прокси'
?>

<h1><?=$this->title?></h1>

<p><a href="<?=url('proxy/account')?>">Привязка прокси</a> </p>

<br>
<div>
	<p>Добавить прокси</p>
	<form method="post">
		<textarea rows="10" cols="45" name="params[proxyContent]"><?=$params['proxyContent']?></textarea>
		<br>
		<input type="checkbox" name="params[isPersonal]" value="true"
			<?if($params['isPersonal']) echo 'checked="checked"'?>> персональные(1прокси=1кошель)
		<br>
		<input type="checkbox" name="params[isYandex]" value="true"
			<?if($params['isYandex']) echo 'checked="checked"'?>> прокси для Яндекса
		<br>

		<p>
			<b>Категория:</b><br>
			<select name="params[category]">
				<option value="">без категории</option>
				<?foreach (Proxy::getCategories() as $category) {?>
					<option value="<?=$category?>" <?if($params['category']==$category){?>selected<?}?>>
						<?=$category?>
					</option>
				<?}?>
			</select>
		</p>

		<br>
		<input type="submit" name="add" value="Добавить"/>
	</form>
</div>
<br>
<br>
<hr>

<?if($models){?>

	<p>
		<b>Свободных персональных: </b> <?=formatAmount($stats['freePersonalCount'], 0)?>
	</p>

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

	<p>
		<b>Категории: </b>
		<a href="<?=url('proxy/list')?>">Все</a> &nbsp;&nbsp;&nbsp;
		<a href="<?=url('proxy/list', ['category'=>''])?>">Без категории</a> &nbsp;&nbsp;&nbsp;
		<?foreach (Proxy::getCategories() as $category) {?>
			<a href="<?=url('proxy/list', ['category'=>$category])?>"><?=ucfirst($category)?></a>&nbsp;&nbsp;&nbsp;
		<?}?>
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
			<td>Коммент</td>
			<td>Кол-во аккаунтов</td>
			<td>Привязка</td>
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
				<td><b><?=$model->category?></b></td>
				<td><?=$model->comment?></td>

				<td><?=$model->accountCount?></td>
				<td><?=$model->account->login?></td>
				<td><?=$model->resetDateStr?></td>
				<td>
					<form action="" method="post">
						<input type="hidden" name="id" value="<?=$model->id?>"/>
						<input type="submit" name="clearStats" value="Очистить статистику"><br>

						<?if($model->reset_url){?>
							<br>
							<input type="submit" name="reboot" value="Перезагрузить" class="green"><br>
						<?}?>
						<br>
						<input type="submit" name="delete" value="Удалить" class="red"><br>
					</form>
				</td>
			</tr>
		<?}?>

	</table>
<?}?>
