<?
/**
 * @var array $stats
 * @var array $params
 * @var array $groupArr
 */

$this->title = 'Лимиты'
?>

<h1><?=$this->title?></h1>

<p>
	<a href="<?=url('client/list')?>">Список клиентов</a>
</p>


<table class="std padding">

	<tr>
		<td></td>
		<td><nobr>Свободных IN</nobr></td>
		<td><nobr>Лимит IN</nobr></td>
		<td>GroupId</td>
		<td>Транзитных</td>
		<td>Исходящих</td>
		<td><nobr>Лимит Transit</nobr></td>
		<td><nobr>Лимит Out</nobr></td>
	</tr>

	<?foreach($stats as $clientId=>$stat){?>
		<tr>
			<td>
				<b><?=$stat['model']->name?></b>
				<?if(!$stat['model']->is_active){?>
					<br><span class="error">(отключен)</span>
				<?}?>
				<?if($stat['model']->description){?>
					<br><i><?=$stat['model']->descriptionStr?></i>
				<?}?>
			</td>
			<td>
				<?if($stat['countFreeInAccountsWarn']){?>
					<span class="red"><?=$stat['countFreeInAccounts']?></span>
				<?}else{?>
					<span class="green"><?=$stat['countFreeInAccounts']?></span>
				<?}?>

					/<?=$stat['countFreeInAccountsFull']?>
			</td>

			<td>
				<?if($stat['limitInWarn']){?>
					<span class="red">
				<?}else{?>
					<span class="green">
				<?}?>
						<?=formatAmount($stat['limitIn'])?>
					</span>
			</td>

			<td>
				<?foreach($groupArr as $groupId=>$arr){?>
					<p>
						<?=$groupId?>

						<?if(isset($stat['fullGroups'][$groupId])){?>
							<span class="withComment" title="Есть зеленые входящие">full</span>
						<?}?>
					</p>
				<?}?>

			</td>

			<td>
				<?foreach($groupArr as $groupId=>$arr){?>
					<p>
						<?if($stat['countTransitAccountsWarn'][$groupId]){?>
							<span class="red">
						<?}else{?>
							<span class="green">
						<?}?>
								<?=formatAmount($stat['countTransitAccounts'][$groupId], 0)?>
							</span>
					</p>
				<?}?>
			</td>

			<td>
				<?foreach($groupArr as $groupId=>$arr){?>
				<p>
					<?if($stat['countOutAccountsWarn'][$groupId]){?>
						<span class="red">
					<?}else{?>
						<span class="green">
					<?}?>
							<?=formatAmount($stat['countOutAccounts'][$groupId], 0)?>
						</span>
				</p>
				<?}?>
			</td>



			<td>
				<?foreach($groupArr as $groupId=>$arr){?>
					<p>
						<?if($stat['limitTransit'][$groupId]<10000){?>
							<span class="red">
						<?}elseif($stat['limitTransitWarn'][$groupId]){?>
							<span class="orange">
						<?}else{?>
							<span class="green">
						<?}?>
								<?=formatAmount($stat['limitTransit'][$groupId], 0)?>
							</span>
					</p>
				<?}?>
			</td>

			<td>
				<?foreach($groupArr as $groupId=>$arr){?>
					<p>
						<?if($stat['limitOut'][$groupId]<10000){?>
						<span class="red">
						<?}elseif($stat['limitOutWarn'][$groupId]){?>
							<span class="orange">
						<?}else{?>
								<span class="green">
						<?}?>
						<?=formatAmount($stat['limitOut'][$groupId], 0)?>
							</span>
					</p>
				<?}?>
			</td>
		</tr>

	<?}?>

</table>

<br><br>
<form action="" method="post">

	<p>
		<label>
		<strong>Добавить аккаунты (+79642343434 pass)</strong><br/>
		<textarea cols="55" rows="25" name="params[phones]"><?=$params['phones']?></textarea>
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
		<strong>Client</strong><br>

		<label>
		<select name="params[clientId]">
			<?foreach(Client::getArr() as $id=>$name){?>
				<option value="<?=$id?>"
					<?if($params['clientId']==$id){?>
						selected="selected"
					<?}?>
				><?=$name?></option>
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
		<input type="submit" name="addAccounts" value="Добавить"/>
	</p>

</form>