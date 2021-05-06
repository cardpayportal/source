<?
/**
 * @var SimTransaction $model
 */
?>

<?$this->widget('zii.widgets.grid.CGridView', [
	'id'=>'transactions',
	'dataProvider'=>$model->search(),
	'filter'=>$model,
	'columns'=>[
		'id',
		'value',
		'amount',
		[
			'class'=>'CButtonColumn',
		],
	],
]);
?>