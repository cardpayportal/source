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
	<table class="table table-nomargin table-bordered table-colored-header">
		<thead>
			<th>ID</th>
			<th>StoreId</th>
			<th>Params</th>
			<th>Answer</th>
			<th>DateAdd</th>
		</thead>

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