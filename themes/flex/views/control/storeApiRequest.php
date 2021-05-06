<?
$this->title = 'Запросы StoreApi';

/**
 * @var StoreApiRequest[] $models
 * @var ControlController $this
 * @var array $filter
 * @var array $stats
 */
?>

<h1><?=$this->title?></h1>

<p>
	<?=$this->renderPartial('_storeApiMenu')?>
</p>

<p>
	<?=$this->renderPartial('//layouts/_filterForm', array('filter'=>$filter))?>
</p>

<p>
	Всего: <b><?=$stats['count']?></b> запросов
</p>

<?if($models){?>
	<table class="std padding">
		<tr>
			<td>ID</td>
			<td>StoreId</td>
			<td>Params</td>
			<td>Answer</td>
			<td>DateAdd</td>
		</tr>

		<?foreach($models as $model){?>
			<tr>
				<td><?=$model->id?></td>
				<td><?=$model->store_id?></td>
				<td><?=$model->params?></td>
				<td><?=$model->answer?></td>
				<td><?=$model->dateAddStr?></td>
			</tr>
		<?}?>

	</table>
<?}else{?>
	запросов не найдено
<?}?>