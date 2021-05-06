<?php
/**
 *
 */
$this->title = 'Список всех кошей';
?>

<div id="dynamic-component-demo" class="demo">
	<button
		v-for="tab in tabs"
		v-bind:key="tab.caption"
		v-bind:class="['tab-button', { active: currentTab === tab }]"
		v-on:click="currentTab = tab.content"
	>{{ tab.title }}</button>
	<keep-alive>
		<component
			v-bind:is="currentTabComponent"
			class="tab"
		></component>
	</keep-alive>
</div>

<script>
	new Vue({
		el: '#dynamic-component-demo',
		data: function () {
			return {
				tabs: [
					{
						caption: 'all',
						title: 'Показать все ',
						content: 'овадывоаывда'
					},
					{
						caption: 'free',
						title: 'Только свободные ',
						content: 'фывафывафывафва'
					},
					{
						caption: 'busy',
						title: 'Только присвоенные',
						content: 'Арфываваываывафхив'
					},
					{
						caption: 'new',
						title: 'Только новые',
						content: 'Арфываваываывафхив'
					}
				],
				currentTab: 'Выберите раздел сверху'
			}
		},
		computed: {
			currentTabComponent: function () {
				return Vue.component('tab-' + this.currentTab.toLowerCase(), {
					template: '<div>' + this.currentTab + '</div>'
				})
			}
		}
	})
</script>


<fieldset>
	<form method="post" action="">
		<span style="display: inline-block">

			<legend><h2>Статус</h2></legend>
			<p>
				<input type="radio" name="showData" value="all" <?if($_SESSION['showData']=='all') echo('checked="checked"');?>/>Показать все <br>
			</p>
			<p>
				<input type="radio" name="showData" value="free" <?if($_SESSION['showData']=='free') echo('checked="checked"');?>/>Только свободные <br>
			</p>
			<p>
				<input type="radio" name="showData" value="busy" <?if($_SESSION['showData']=='busy') echo('checked="checked"');?>/>Только присвоенные <br>
			</p>
			<p>
				<input type="radio" name="showData" value="new" <?if($_SESSION['showData']=='new') echo('checked="checked"');?>/>Только новые <br>
			</p>
		</span>
		<span style="display: inline-block">

			<legend><h2>Тип</h2></legend>
			<p>
				<input type="radio" name="showType" value="allTypes" <?if($_SESSION['showType']=='allTypes') echo('checked="checked"');?>/>Все типы <br>
			</p>
			<p>
				<input type="radio" name="showType" value="onlyCard" <?if($_SESSION['showType']=='onlyCard') echo('checked="checked"');?>/>Только карты <br>
			</p>
			<p>
				<input type="radio" name="showType" value="onlyWallet" <?if($_SESSION['showType']=='onlyWallet') echo('checked="checked"');?>/>Только кошельки <br>
			</p>
		</span>
		<span style="display: inline-block; padding-left: 20px;">
			<p>
				<input type="submit" name="acceptFilter" value="Применить">
			</p>
		</span>
	</form>
</fieldset>

<br>

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