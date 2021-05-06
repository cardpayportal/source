<?
/**
 * @var YandexTransaction $trans
 */
?>
<tr
	<?if($trans->status==Transaction::STATUS_ERROR){?>
		class="error" title="ID: <?=$trans->id?> <?=$trans->error?>"
	<?}elseif($trans->status==Transaction::STATUS_WAIT){?>
		class="wait" title="ID: <?=$trans->id?> Не подтвержден"
	<?}elseif($trans->status===Transaction::STATUS_SUCCESS){?>
		class="success" title="ID: <?=$trans->id?>"
	<?}?>

	<?if($num > 2){?>
		data-param="toggleRow"
		style="display: none;"
	<?}?>
>

	<td><?=$trans->amount?></td>
	<td><?=date('d.m.Y H:i', $trans->date_add)?></td>
	<td><?=$trans->comment?></td>
</tr>