<?
$this->title = 'Платежи StoreApi';

/**
 * @var StoreApiTransaction[] $models
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
	Всего: <b><?=$stats['count']?></b> платежей,&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	Подтверждено RUB: <b><?=formatAmount($stats['successAmount'], 0)?></b>  из <b><?=formatAmount($stats['allAmount'], 0)?></b>,
	Подтверждено KZT: <b><?=formatAmount($stats['successAmountKzt'], 0)?></b> из <b><?=formatAmount($stats['allAmountKzt'], 0)?></b>
</p>

<?if($models){?>
	<table class="std padding">
		<tr>
			<td>Магазин</td>
			<td>Сумма</td>
			<td>Кошелек</td>
			<td>От</td>
			<td>Статус</td>
			<td>ID</td>
			<td>Дата добавления</td>
			<td>Дата оплаты</td>
		</tr>

		<?foreach($models as $model){?>
			<tr>
				<td>Store<?=$model->id?></td>
				<td><?=$model->amountStr?></td>
				<td><?=$model->account->login?></td>
				<td><?=$model->wallet_from?></td>
				<td><?=$model->statusStr?></td>
				<td><?=$model->qiwi_id?></td>
				<td><?=$model->dateAddStr?></td>
				<td><?=$model->datePayStr?></td>
			</tr>
		<?}?>
	</table>
<?}else{?>
	платежи не найдены
<?}?>