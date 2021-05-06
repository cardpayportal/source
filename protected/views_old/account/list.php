<?
/**
 * @var AccountController $this
 * @var Account[] $models
 */
	$this->title='Список кошельков'
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

	<table class="std padding">
		
		<tr>
			<td>ID</td>
			<td>ClientName</td>
			<td>GroupId</td>
			<td>Логин</td>
			<td>Статус</td>
			<td>Баланс</td>
			<td>Лимит</td>
			<td>Тип</td>

			<td>Приоритет</td>
			<td>Проверка</td>
			<td>Авторизация</td>
			<td>Ошибка</td>
			<td>Юзер</td>
			<td>Client</td>
			<td>Взят</td>
			<td>Использован</td>

			<td>Прокси</td>
			<td>Браузер</td>

			<td>Добавлен</td>
			<td>Мыло</td>
			<td>Comment</td>
			<td>Действие</td>
		</tr>
		
		<?foreach($models as $model){?>
			<tr>
				<td>
					<a target="_blank" title="Полная проверка кошелька (доступна если Тестовый режим или ошибка на аккаунте)" href="<?=url('account/fullCheck', array('id'=>$model->id))?>">Проверка</a>
					<br /><br />
					<a target="_blank" href="<?=url('account/security', array('id'=>$model->id))?>">
						ID<?=$model->id?>
					</a>
					
					<?if($model->error){?>
						<br />
						<a target="_blank" title="Починить" href="<?=url('account/repair', array('id'=>$model->id))?>">чинить</a>
					<?}?>
				</td>
				<td><?=$model->client->name?></td>
				<td><?=$model->group_id?></td>
				<td>
					<a target="_blank" title="История" href="<?=url('account/historyAdmin', array('id'=>$model->id))?>">
						<span>
							<?=$model->login?>
						</span>
					</a>
				</td>
				<td><?=$model->statusStr?></td>
				<td>
					<nobr><?=$model->balanceStr?></nobr>

					<?if($model->balance_kzt > 0){?>
						<br><nobr><?=$model->balanceKztStr?> KZT</nobr>
					<?}?>
				</td>
				<td><nobr><?=formatAmount($model->limit_in, 0)?></nobr><br/><nobr><?=formatAmount($model->limit_out, 0)?></nobr></td>
				<td><?=$model->typeStr?></td>

				<td><?=$model->check_priority?></td>
				<td><?=$model->dateCheckStr?></td>
				<td><?=$model->dateLastRequestStr?></td>

				<td><font color="red"><?=$model->error?></font></td>
				<td><?=$model->userStr?></td>
				<td><?=$model->client->name?></td>

				<td><?=$model->datePickStr?></td>
				<td><?=$model->dateUsedStr?></td>

				<td><?=$model->currentProxy?></td>
				<td><?=$model->browser?></td>

				<td><?=$model->dateAddStr?></td>
				<td><?=$model->isEmailStr?></td>
				<td><?=$model->comment?></td>
				<td>
					<form method="post">
						<input type="hidden" name="params[accountId]" value="<?=$model->id?>">
						<input type="submit" name="clearCookie" value="Удал. куки">

						<?if(YII_DEBUG){?>
							<input type="submit" name="pushMoney" value="Толкнуть средства">
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


