<?
/**
 * @var MerchantTransaction $trans
 */
?>
<tr
	<?if($trans->status==Transaction::STATUS_ERROR){?>
		class="error" title="ID: <?=$trans->id?> <?=$trans->error?>"
	<?}elseif($trans->status==Transaction::STATUS_WAIT){?>
		class="wait" title="ID: <?=$trans->id?> Не подтвержден"
	<?}elseif($trans->status===Transaction::STATUS_SUCCESS){?>
		<?if($trans->type===Transaction::TYPE_OUT){?>
			class="ratTransaction"
		<?}else{?>
			class="success"
		<?}?>

		title="ID: <?=$trans->id?>"
	<?}?>

	<?if($num > 2){?>
		data-param="toggleRow"
		style="display: none;"
	<?}?>
>

	<td><?=$trans->typeStr?></td>
	<td><?=$trans->amountStr?></td>
	<td><?=$trans->commentStr?></td>
	<td><?=$trans->walletStr?></td>
	<td><?=$trans->dateAddStr?></td>
	<td><?=Tools::shortText($trans->error, 20)?></td>
</tr>