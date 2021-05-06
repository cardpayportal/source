<?php
/**
 *
 */
$this->title = 'Список кошей';
?>

<?if($wallets){?>
	<table id="table" class="std padding">
		<tr>
			<th>Номер</th>
			<th>Карта</th>
			<th>Лимит</th>
			<th>Принято</th>
			<th>Дата синхр.</th>
			<th>Client</th>
			<th>User</th>
			<th>Предыдущий User</th>
			<th>Тип</th>
			<th>ID</th>
			<th>Название</th>
			<th>Баланс</th>
			<th>ID Мерчанта</th>
		</tr>

		<?foreach($wallets as $wallet){?>
			<?
			$class = '';
			$title = '';

			if($wallet['protocol_type'] == 'CARD')
				$class = 'wait ';
			elseif($wallet['merchant_internal_user_id'])
				$class = 'success ';
			if($wallet['qiwi_blocked'] or $wallet['error']!='')
			{
				$title = $wallet['error'];
				$class = 'error ';
			}
			?>
			<tr class="<?=$class?>"
				title="<?=$title?>" >
				<td><?=$wallet['wallet']?></td>
				<td><?=$wallet['card_number']?></td>
				<td><?=$wallet['limit_in']?></td>
				<td><?=$wallet['amountStr']?></td>
				<td <?if(time() - $wallet['last_sync_time'] > 3600){?> class='error' title="Давно не обновлялся"<?}?>>
					<?=$wallet['last_sync_date']?>
				</td>
				<td><?=$wallet['client_name']?></td>
				<td><?=$wallet['user_name']?></td>
				<td><?=$wallet['last_user_name']?></td>
				<td><?=$wallet['protocol_type']?></td>
				<td><?=$wallet['id']?></td>
				<td><?=$wallet['wallet_name']?></td>
				<td><?=$wallet['balance']?></td>
				<td>
					<nobr><?=$wallet['merchant_internal_user_id']?></nobr>
					<?if($wallet['merchant_internal_user_id']){?>
						<br><br>
						<button type="button" class="deasignButton red" internalUserId="<?=$wallet['merchant_internal_user_id']?>" internalWalletId="<?=$wallet['id']?>"><nobr>Отвязать</nobr></button>
					<?}else{?>
						<form method="post" action="">
							<nobr>
								<?=CHtml::dropDownList('client_id'.$wallet['wallet_name'],'',
									Client::getArr(),
									array(
										'prompt'=>'Select Client',
										'ajax' => array(
											'type'=>'POST',
											'url'=>Yii::app()->createUrl('qiwi/main/loadUsers'),
											'update'=>'#'.$wallet['wallet_name'],
											'data'=>array('client_id'=>'js:this.value'),
										)));?>
								<input type="hidden" name="internaWalletlId" value="<?=$wallet['id']?>"/>
								<select name="userId" id="<?=$wallet['wallet_name']?>" >
									<option value="">Select User</option>
								</select>
								&nbsp
								<input type="submit" name="assign" value="Привязать"/>
							</nobr>
						</form>
					<?}?>
				</td>

			</tr>
		<?}?>
	</table>
<?}else{?>
	Нет записей
<?}?>




<script>
	$(document).ready(function(){
		$('.deasignButton').click(function () {
			$('#deasignForm [name*=internalWalletId]').val($(this).attr('internalWalletId'));
			$('#deasignForm [name*=internalUserId]').val($(this).attr('internalUserId'));
			$('#deasignForm [type=submit]').click();
		})
	});
	;
</script>

<form method="post" style="display: none" id="deasignForm">
	<input type="hidden" name="params[internalWalletId]" value="" />
	<input type="hidden" name="params[internalUserId]" value="" />
	<p>
		<input type="submit" name="deasignButton" value="отвязать">
	</p>
</form>