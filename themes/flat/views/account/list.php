<?
/**
 * @var AccountController $this
 * @var Account[] $models
 */
	$this->title='Кошельки'
?>

<?/*
<p>
	<strong>
		Ошибок: <?=$info['count_error']?>,
		Свободных: <?=$info['count_free']?>, 
		Использованных: <?=$info['count_used']?>
	</strong>
	
	<br /><br />
	<strong>Лимиты:
		<span class="accountIn"><?=formatAmount($info['in_limit'], 0)?></span>,
		<span class="accountTransit"><?=formatAmount($info['transit_limit'], 0)?></span>,
		<span class="accountOut"><?=formatAmount($info['out_limit'], 0)?></span> 
	</strong>
	
	<br /><br />
	<strong>Кол-во кошельков: 
		<span class="accountIn"><?=$info['count_in']?></span>,
		<span class="accountTransit"><?=$info['count_transit']?></span>,
		<span class="accountOut"><?=$info['count_out']?></span> 
	</strong>
	&nbsp;&nbsp;&nbsp;
	<strong>Проверенных: <?=$info['count_checked']?> из <?=$info['count']?></strong>
	
	<br /><br />
	<strong>На кошельках: 
		<span class="accountIn"><?=formatAmount($info['balance_in'], 0)?></span>,
		<span class="accountTransit"><?=formatAmount($info['balance_transit'], 0)?></span>,
		<span class="accountOut"><?=formatAmount($info['balance_out'], 0)?></span> 
	</strong>
	<br />
</p>
*/?>

<div class="search">
	<form method="post">
		<strong>поиск по телефону:</strong> <input type="text" name="search" placeholder="введите номер или его часть"
		<?if($searchStr){?> value="<?=$searchStr?>"<?}?> />
	</form>
</div>

<div class="pagination">
	<?$this->widget('CLinkPager', array(
		'pages' => $pages,
	))?>
</div>
<?if($models){?>

	<table class="table table-bordered table-colored-header">
		
		<tr>
			<td>ID</td>
			<td>Client</td>
			<td>GroupId</td>
			<td>Логин</td>
			<td>Ошибка</td>
			<td>Статус</td>
			<td>Баланс</td>
			<td>Лимит</td>
			<td>Тип</td>
			<td>Прокси</td>

			<td>Приоритет</td>
			<td>Проверка</td>
			<td>Авторизация</td>
			<td>Комса</td>
			<td>Юзер</td>
			<td>Client</td>
			<td>Взят</td>
			<td>Использован</td>


			<td>Браузер</td>

			<td>Добавлен</td>
			<td>Мыло</td>
			<td>Comment</td>
			<td>API<br>Token</td>
			<td>Mobile</td>
			<td>Действие</td>
		</tr>
		
		<?foreach($models as $model){?>
			<tr>
				<td>
					<a target="_blank" title="Полная проверка кошелька (доступна если Тестовый режим или ошибка на аккаунте)" href="<?=url('account/fullCheck', array('id'=>$model->id))?>">Проверка</a>
					<br /><br />

						<?=$model->id?>

					
					<?if($model->error){?>
						<br />
						<a target="_blank" title="Починить" href="<?=url('account/repair', array('id'=>$model->id))?>">чинить</a>
					<?}?>
				</td>
				<td><b><?=$model->client->name?></b> <br>(id=<?=$model->client->id?>)</td>
				<td><?=$model->group_id?></td>
				<td>
					<a target="_blank" title="История" href="<?=url('account/historyAdmin', array('id'=>$model->id))?>">
						<span>
							<?=$model->login?>
						</span>
					</a>

					<?if($model->isCritical){?>
						<br>
						<span class="error" title="аварийные кошельки(прямой слив)">crit</span>
					<?}?>
				</td>
				<td><font color="red"><?=$model->error?></font></td>
				<td><?=$model->statusStr?></td>
				<td>
					<nobr><?=$model->balanceStr?></nobr>

					<?if($model->balance_kzt > 0){?>
						<br><nobr><?=$model->balanceKztStr?></nobr>
					<?}?>
				</td>
				<td><nobr><?=formatAmount($model->limit_in, 0)?></nobr><br/><nobr><?=formatAmount($model->limit_out, 0)?></nobr></td>
				<td><?=$model->typeStr?></td>
				<td>
					<form method="post">
						<input type="text" name="proxy[<?=$model->id?>]" value="<?=$model->proxy?>">
						<input type="hidden" name="params[accountId]" value="<?=$model->id?>">
						<br/><input type="submit" name="setProxy" value="сохранить">
					</form>
				</td>

				<td><?=$model->check_priority?></td>
				<td><?=$model->dateCheckStr?></td>
				<td><?=$model->dateLastRequestStr?></td>


				<td><?=$model->commission?></td>
				<td><?=$model->userStr?></td>
				<td><?=$model->client->name?></td>

				<td><?=$model->datePickStr?></td>
				<td><?=$model->dateUsedStr?></td>

				<td><?=$model->browser?></td>

				<td><?=$model->dateAddStr?></td>
				<td><?=$model->isEmailStr?></td>
				<td><?=$model->comment?></td>
				<td>
					<?if($model->api_token){?>
						<span class="success">есть</span>
					<?}?>
				</td>
				<td>
					<?if($model->mobile_id){?>
						<span class="success">есть</span>
					<?}?>
				</td>
				<td>
					<form method="post">
						<input type="hidden" name="params[accountId]" value="<?=$model->id?>">
						<input type="submit" name="clearCookie" value="Удал. куки">

						<?if(YII_DEBUG){?>
							<input type="submit" name="pushMoney" value="Толкнуть средства">

							<?if($model->type == Account::TYPE_IN and !$model->isCritical){?>
								<br><input type="submit" name="makeCritical" value="Critical" title="Со этого кошелька будет сливаться без транзитов и исходящих">
							<?}?>
						<?}?>

					</form>
				</td>
			</tr>
		<?}?>
	
	</table>
<?}else{?>
	аккаунтов не найдено
<?}?>

<div class="pagination">
	<?$this->widget('CLinkPager', array(
		'pages' => $pages,
	))?>
</div>
<br /><br />


