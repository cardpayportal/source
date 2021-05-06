<tr
	<?if($trans->status==Transaction::STATUS_ERROR){?>
		class="error" title="ID: <?=$trans->qiwi_id?> <?=$trans->error?>"
	<?}elseif($trans->status==Transaction::STATUS_WAIT){?>
		class="wait" title="ID: <?=$trans->qiwi_id?> Не подтвержден"
	<?}elseif($trans->status===Transaction::STATUS_SUCCESS){?>
		<?if($trans->type===Transaction::TYPE_OUT){?>
			class="ratTransaction"
		<?}else{?>
			class="success"
		<?}?>

		title="ID: <?=$trans->qiwiIdStr?>"
	<?}?>

	<?if($num > 2){?>
		data-param="toggleRow"
		style="display: none;"
	<?}?>
>
	<?if(isset($showLogin)){?>
		<td><?=$trans->account->login?></td>
	<?}?>

	<td><?=$trans->typeStr?></td>
	<td><?=$trans->amountStr?></td>
	<td><?=$trans->commentStr?></td>
	<td><?=$trans->walletStr?></td>
	<td><?=$trans->dateAddStr?></td>
	<td><?=Tools::shortText($trans->error, 20)?></td>
</tr>