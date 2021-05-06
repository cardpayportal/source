<?php
/**
 * @var TransactionWex[] $history
 * @var string $user
 */
?>

<p>
	<strong><?=$user?></strong>
</p>

<h2>Платежи (<?=count($history)?>)</h2>

<?if($history){?>
	<table class="std padding">

		<?foreach($history as $model){?>
			<tr
				<?if($model->status == 'success'){?>
					<?if($model->isLinked){?>
						class="linked" title="привязан"
					<?}else{?>
						class="success"
					<?}?>
				<?}else{?>
					class="error"
				<?}?>
			>
				<td>#<?=$model->wex_id?></td>
				<td><?=$model->originalAmount?> <?=$model->currency?></td>
				<td><?=date('d.m.Y H:i', $model->date_add)?></td>
			</tr>
		<?}?>

	</table>
<?}else{?>
	нет платежей
<?}?>
