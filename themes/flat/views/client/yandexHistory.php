<?php
/**
 * @var array $history
 * @var string $user
 */
?>

<p>
	<strong><?=$user?></strong>
</p>

<h2>Платежи (<?=count($history)?>)</h2>

<?if($history){?>
	<table class="std padding">

		<?foreach($history as $trans){?>
			<tr
				<?if($trans['status'] == 'success'){?>
					<?if($model->isLinked){?>
						class="linked" title="привязан"
					<?}else{?>
						class="success"
					<?}?>
				<?}else{?>
					class="error"
				<?}?>
			>
				<td>#<?=$trans['id']?></td>
				<td><?=$trans['amount']?> <?=$trans['currency']?></td>
				<td><?=date('d.m.Y H:i', $trans['date'])?></td>
			</tr>
		<?}?>

	</table>
<?}else{?>
	нет платежей
<?}?>
