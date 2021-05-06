<?
$this->title = 'Выводы StoreApi';

/**
 * * @var ControlController $this
 * @var StoreApiWithdraw[] $models
 * @var array $filter
 * @var array $stats
 *
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
	Всего: <b><?=$stats['count']?></b> выводов &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	на сумму:
	<b style="color: #33CC00"><?=formatAmount($stats['amountRub'], 0)?> руб</b>,
	<b style="color: brown"><?=formatAmount($stats['amountBtc'], 5)?> btc</b>,
	<b style="color: #333399"><?=formatAmount($stats['amountUsd'], 0)?> usd</b>
</p>

<?if($models){?>
	<?$this->renderPartial('_withdrawList', ['models'=>$models])?>
<?}else{?>
	выводы не найдены
<?}?>

