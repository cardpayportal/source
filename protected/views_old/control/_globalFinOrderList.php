<?php
/**
 * @var FinansistOrder $models
*/
?>
<table class="std padding">
	<tr>
		<td>ID</td>
		<td>Клиент</td>
		<td>Кому</td>
		<td>Сумма</td>
		<td>Статус</td>
		<td>Отправлено</td>
		<td>Дата добавления</td>
		<td>Автор</td>
	</tr>

	<?foreach($models as $model){?>
		<tr
			<?if($model->amount_send > $model->amount){?>
				class="orderOverPay" title="переплата на <?=formatAmount($model->amount_send - $model->amount)?>"
			<?}elseif($model->priority==FinansistOrder::PRIORITY_BIG){?>
				class="orderPriorityBig"
			<?}?>
		>
			<td><?=$model->id?></td>
			<td><?=$model->client->name?></td>
			<td><?=$model->to?></td>
			<td>
				<?=$model->amountStr?> руб

				<?if($model->comment){?>
					<span class="dotted" title="<?=$model->comment?>">комментарий</span>
				<?}?>
			</td>
			<td>
				<?=$model->statusStr?>
			</td>
			<td><?=$model->amountSendStr?> руб</td>
			<td><?=$model->dateAddStr?></td>
			<td><?=$model->userStr?></td>
		</tr>
	<?}?>
</table>