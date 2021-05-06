<?
/**
 * @var array $stats
 * @var array $params
 * @var array $groupArr
 * @var string $lastClientId
 * @var array $outAmountWithGroups
 */

$this->title = 'Лимиты'
?>

<h1><?=$this->title?></h1>

<script>
	$(document).ready(function() {

		function sumAll() {
			var countToAdd = 0;

			$('.std input').each(function() {
				var $input = $(this).parent().find('input');
				countToAdd += +parseInt($input.val());
			});

			var maxCountWallets = parseInt($('.maxCountWallets').text());
			if(countToAdd > maxCountWallets)
			{
				alert('Максимальное кол-во кошелей для добавления: ' + maxCountWallets);
				return true;
			}

		};

		$('.minus').click(function () {
			var $input = $(this).parent().find('input');
			var count = parseInt($input.val()) - 1;
			count = count < 0 ? 0 : count;
			$input.val(count);
			$input.change();
			sumAll();
			return false;
		});
		$('.plus').click(function () {
			var $input = $(this).parent().find('input');
			var count = parseInt($input.val()) + 1;
			if(count > 9)
			{
				count = 9;
				alert('Не больше ' + count + ' в одну группу');
				return false;
			}

			$input.val(count);
			$input.change();
			if(sumAll())
				count--;
			$input.val(count);
			$input.change();
			return false;
		});

	});
</script>


<!--максиальное кол-во кошей для добавления-->
<span class="maxCountWallets" style="visibility: hidden;"><?=Account::autoAddCount()?></span>

<p>
	<a href="<?=url('client/list')?>">Список клиентов</a>
</p>

<form action="" method="post">
	<input type="submit" name="addAccountsFromTableFields" value="Добавить по таблице"/><br>

	<table class="std padding">

		<tr>
			<td></td>
			<td><nobr>Свободных IN</nobr></td>
			<td><nobr>Лимит IN</nobr></td>
			<td>GroupId</td>
			<td colspan="2">Транзитных</td>
			<td colspan="2">Исходящих</td>
			<td><nobr>Лимит Transit</nobr></td>
			<td><nobr>Лимит Out</nobr></td>
			<td><nobr>Баланс</nobr></td>
		</tr>
		<?foreach($stats as $clientId=>$stat){?>
			<tr style="border: 2px solid black;">
				<td rowspan="6">
					<b><?=$stat['model']->name?></b>
					<br>(ClientId<?=$stat['model']->id?>)
					<?if(!$stat['model']->is_active){?>
						<br><span class="error">(отключен)</span>
					<?}?>
					<?if($stat['model']->description){?>
						<br><i><?=$stat['model']->descriptionStr?></i>
					<?}?>
				</td>
				<td rowspan="6">
					<?if($stat['countFreeInAccountsWarn']){?>
						<span class="red"><?=$stat['countFreeInAccounts']?></span>
					<?}else{?>
						<span class="green"><?=$stat['countFreeInAccounts']?></span>
					<?}?>

					/<span title="кошельков с фул идентом" class="dotted"><?=$stat['countFreeInAccountsFull']?></span>
					/<span title="кошельков с апи-токеном" class="dotted"><?=$stat['countFreeInAccountsApi']?></span>
					<br><br>
					<div class="amount">
						<span class="minus">-</span>
						<input type="text" readonly="readonly" name="countToAdd[<?=$stat['model']->id?>][0][in]" value="0" size="1"/>
						<span class="plus">+</span>
					</div>
				</td>

				<td  rowspan="6">
					<?if($stat['limitInWarn']){?>
					<span class="red">
				<?}else{?>
						<span class="green">
				<?}?>
				<?=formatAmount($stat['limitIn'])?>
					</span>
				</td>
			</tr>

			<?foreach($groupArr as $groupId=>$arr){?>
				<tr>
					<td>
						<?=$groupId?>

						<?if(isset($stat['fullGroups'][$groupId])){?>
							<span class="withComment" title="Есть зеленые входящие">full</span>
						<?}?>
					</td>
					<td style="border-right: none">
						<?if($stat['countTransitAccountsWarn'][$groupId]){?>
						<span class="red">
					<?}else{?>
							<span class="green">
					<?}?>
					<?=formatAmount($stat['countTransitAccounts'][$groupId], 0)?>
						</span>
					</td>
					<td style="border-left: none">
						<nobr>
							<div class="amount">
								<span class="minus">-</span>
								<input type="text" readonly name="countToAdd[<?=$stat['model']->id;?>][<?=$groupId;?>][transit]" value="0" size="1"/>
								<span class="plus">+</span>
							</div>
						</nobr>
					</td>
					<p>
					</p>
					<td style="border-right: none">
						<?if($stat['countOutAccountsWarn'][$groupId]){?>
						<span class="red">
				<?}else{?>
							<span class="green">
				<?}?>
				<?=formatAmount($stat['countOutAccounts'][$groupId], 0)?>
					</span>
					</td>
					<td style="border-left: none">
						<nobr>
							<div class="amount">
								<span class="minus">-</span>
								<input type="text" readonly name="countToAdd[<?=$stat['model']->id;?>][<?=$groupId;?>][out]" value="0" size="1"/>
								<span class="plus">+</span>
							</div>
						</nobr>
					</td>
					<td>
						<?if($stat['limitTransit'][$groupId]<10000){?>
						<span class="red">
					<?}elseif($stat['limitTransitWarn'][$groupId]){?>
							<span class="orange">
					<?}else{?>
								<span class="green">
					<?}?>
					<?=formatAmount($stat['limitTransit'][$groupId], 0)?>
						</span>
					</td>
					<td>
						<?if($stat['limitOut'][$groupId]<10000){?>
						<span class="red">
					<?}elseif($stat['limitOutWarn'][$groupId]){?>
							<span class="orange">
					<?}else{?>
								<span class="green">
					<?}?>
					<?=formatAmount($stat['limitOut'][$groupId], 0)?>
						</span>
					</td>
					<td>
						<?=formatAmount($outAmountWithGroups[$stat['model']->id][$groupId], 0)?>
						<span class="wait" title="на сколько кошельков сливается с этой группы в данный момент">
							<br>
							(<?=FinansistOrder::getWalletCountAtGroup($stat['model']->id, $groupId, true)?>)
						</span>
					</td>
				</tr>
			<?}?>
		<?}?>
	</table>
	<p>
		<input type="submit" name="addAccountsFromTableFields" value="Добавить по таблице"/>
	</p>
</form>

<br><br>

<form action="" method="post">

	<p>
		<label>
			<strong>Добавить аккаунты (+79642343434 pass токен [пркоси])</strong><br/>

			<i>
				если надо добавить с прокси но без токена то вбить вместо токена любую НЕ 32-символьную строку
			</i>
			<br>

			<textarea style="width: 700px" rows="15" name="params[phones]"><?=$params['phones']?></textarea>
		</label>
	</p>

	<p>
		<strong>Client</strong><br>

		<label>
			<select name="params[clientId]">
				<?foreach(Client::getArr() as $id=>$name){?>
					<option value="<?=$id?>"
						<?if($params['clientId']==$id){?>
							selected="selected"
						<?}?>
					><b><?=$name?></b> (id=<?=$id?>)</option>
				<?}?>
			</select>
		</label>
	</p>

	<p>
		<strong>Тип</strong><br/>

		<label>
			<select name="params[type]">
				<?foreach(Account::typeArr() as $name=>$value){?>
					<option value="<?=$name?>"
						<?if($params['type']==$name){?>
							selected="selected"
						<?}?>
					><?=$value?></option>
				<?}?>
			</select>
		</label>

	</p>

	<p>
		<strong>Группа</strong><br/>

		<label>
			<select name="params[groupId]">
				<option value=""></option>

				<?foreach(Account::getGroupArr() as $id=>$arr){?>
					<option value="<?=$id?>" <?if($params['groupId']==$id){?>selected="selected"<?}?>><?=$id?></option>
				<?}?>

			</select>
		</label>
	</p>

	<p>
		<input type="checkbox" name="params[isCritical]" value="1"
			   <?if($params['isCritical']){?>checked="checked"<?}?>>
		<span class="dotted" title="Фул иденты для критического режима (IN => Слив), не выдаются в обычном режиме">Критические кошельки</span>
		<br><i>кошельки должны быть входящими</i>
	</p>
	<p>
		<input type="checkbox" name="params[withOutCheck]" value="1"
			   <?if($params['withOutCheck']){?>checked="checked"<?}?>>
		<span class="dotted" title="Кошельки сразу поступают в работу">Без проверки</span>
	</p>
	<p>
		<input type="submit" name="addAccounts" value="Добавить"/>
	</p>

</form>

