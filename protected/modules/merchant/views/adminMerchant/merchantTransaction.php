<?
/**
 * @var MerchantUser $models
 */

$this->title = 'История платежей '
?>

<h2><?=$this->title?></h2>

<p><a href="<?=url(cfg('index_page'))?>">Назад</a></p>

<p>
	<?if($models){?>
	<table class="std padding">
		<tr>
			<td>ID</td>
			<td>Сумма</td>
			<td>Кошелек</td>
			<td>Дата</td>
		</tr>

		<?foreach($models as $model){?>
			<tr
				<?if($model->status==Transaction::STATUS_ERROR){?>
					class="error" title="<?=$model->error?>"
				<?}elseif($model->status==Transaction::STATUS_WAIT){?>
					class="wait" title="Не подтвержден"
				<?}else{?>
					class="<?=$trans->status?>"
				<?}?>
			>
				<td><?=$model->id?></td>
				<td><?=$model->amount?></td>
				<td><?=$model->wallet?></td>
				<td><?=$model->dateAddStr?></td>
			</tr>
		<?}?>
	</table>
<?}else{?>
	нет платежей
<?}?>
</p>
