<?
/**
 * @var PanelController $this
 * @var array $params
 * @var NewYandexPay[] $models
 */

$this->title = 'Поиск заявки Yandex';
?>

<h1><?=$this->title?></h1>



<form method="post">
	<strong>поиск по комменту:</strong>
	<p>
		<input type="text" name="searchStr" placeholder="введите комментарий или его часть"/>&nbsp
		<input type="submit" name="search" value="Поиск">
	</p>
</form>
<br>

<form method="post">
	<strong>поиск по id:</strong>
	<p>
		<input type="text" name="id" placeholder="введите id заявки(id базы данных, не коммент!)"/>&nbsp
		<input type="submit" name="searchById" value="Поиск">
	</p>
</form>
<br>

<?if($models){?>

	<table class="std padding">

		<thead>
		<th>ID</th>
		<th>Сумма</th>
		<th>Статус</th>
		<th>Добавлен</th>
		<?if(!$this->isManager()){?>
			<th>Юзер</th>
		<?}?>
		<th>Коммент</th>
		<th>Ссылка</th>
		<th>Действие</th>
		</thead>

		<?foreach($models as $model){?>
			<tr>
				<td><?=$model->id?></td>
				<td><b><?=$model->amountStr?></b></td>
				<td>
					<?if($model->status == NewYandexPay::STATUS_SUCCESS){?>
						<span class="success"><?=$model->statusStr?></span>
						<br><?=$model->datePayStr?>
					<?}elseif($model->status == NewYandexPay::STATUS_ERROR){?>
						<span class="error"><?=$model->statusStr?></span>
						<br>(<?=$model->error?>)
					<?}elseif($model->status == NewYandexPay::STATUS_WORKING){?>
						<span class="accountTransit"><?=$model->statusStr?></span>
					<?}elseif($model->status == NewYandexPay::STATUS_WAIT){?>
						<span class="wait"><?=$model->statusStr?></span>
					<?}?>
				</td>
				<td>
					<?=$model->dateAddStr?>

					<?if($model->created_by_api){?>
						<br>
						(<span class="success" title="получен через API">API</span>)
					<?}?>
				</td>
				<?if(!$this->isManager()){?>
					<th><?=$model->user->name?></th>
				<?}?>
				<td>
					<?=$model->comment?>
				</td>
				<td>
					<span class="shortContent"><?=$model->urlShort?></span>
					<input style="display: none" type="text" size="40" value="<?=$model->url?>" class="click2select fullContent">
					<br>
					<b>кош:</b> <?=$model->wallet?>
					<br>
					<b>unique id:</b> <?=$model->unique_id?>
				</td>
				<td>
					<?if($model->status == NewYandexPay::STATUS_WAIT){?>
						<form method="post" action="">
							<input type="hidden" name="params[id]" value="<?=$model->id?>">
							<input type="submit" name="confirm" value="Подтвердить" title="помечает платеж подтвержденным">
						</form>
					<?}?>
				</td>
			</tr>
		<?}?>
	</table>
<?}else{?>
	записей не найдено
<?}?>

