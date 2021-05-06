<?
/**
 * @var ControlController $this
 * @var array $params
 * @var array $stats
 * @var Account[] $currentAccounts
 * @var AccountLimitOut $stats['limitOutAccounts']
 *
 *
 */

?>

<?$this->title = 'Глобальная статистика'?>

<h1><?=$this->title?></h1>

<p>
	<form method="post">
	<i>максимальный интервал - 2 дня</i>
	<br>
		<select name="params[clientId]">
			<option value="">Все</option>
				<?foreach(Client::getArr() as $id=>$name){?>
					<option value="<?=$id?>"
						<?if($params['clientId']==$id){?>
							selected="selected"
						<?}?>
					><?=$name?></option>
				<?}?>
		</select>

		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		с <input type="text" name="params[date_from]" value="<?=$params['date_from']?>" />
		до <input type="text" name="params[date_to]" value="<?=$params['date_to']?>" />
		<input  type="submit" name="stats" value="Показать"/>
	</form>
</p>


<p>
	<form method="post" style="display: inline">
		<input type="hidden" name="params[date_from]" value="<?=date('d.m.Y')?>" />
		<input type="hidden" name="params[date_to]" value="<?=date('d.m.Y', time()+24*3600)?>" />
		<input type="hidden" name="params[clientId]" value="<?=$params['clientId']?>" />
		<input  type="submit" name="stats" value="За сегодня"/>
	</form>

	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

	<form method="post" style="display: inline">
		<input type="hidden" name="params[date_from]" value="<?=date('d.m.Y', time()-24*3600)?>" />
		<input type="hidden" name="params[date_to]" value="<?=date('d.m.Y')?>" />
		<input type="hidden" name="params[clientId]" value="<?=$params['clientId']?>" />
		<input  type="submit" name="stats" value="За вчера"/>
	</form>
</p>

<br>
<br>

<h2>Приход/Расход</h2>

<table class="std padding">
	<tr>
		<td>Клиент</td>
		<td><span class="dotted" title="Текущий баланс всех кошельков клиента">Баланс</span></td>
		<td title="Сливается ли в данный момент с исходящих кошельков клиента">Сливается</td>
		<td title="приход на Qiwi">Qiwi</td>
		<td title="приход на Qiwi Merchant Adgroup">Qiwi2</td>
		<td title="приход на Yandex Merchant Adgroup">Yandex Merchant</td>
		<td title="приход на WalleteSvoe">WalletS</td>
		<td title="Сколько пришло Yandex Money">ЯД</td>
		<td title="Сколько пришло Yandex кошельки">Yandex Wallet</td>
		<td title="Сколько пришло Sim-кошельки">Sim</td>
		<td title="Сколько пришло по картам">Карты</td>
		<td title="Сколько пришло по обменнику яндекс деньги->bit">Обмен яндекс->bit</td>
		<td title="приход на QiwiNew">QiwiNew</td>
		<td title="">Комиссия</td>
		<td title="Заблокировано средств за период">Бан</td>
		<td>Расчет</td>
		<td>Действие</td>
	</tr>

	<?
		$calcTotal = 0;
	?>

	<?foreach($stats['clients'] as $clientId=>$arr){?>
		<tr>
			<td><b><?=$arr['model']->name?></b> (id=<?=$arr['model']->id?>)<br><?=$arr['model']->description?></td>
			<td><?=formatAmount($arr['balance'], 0)?></td>
			<td>
				<?if($arr['finOrderInProcess']){?>
					<font color="green">да</font>
				<?}else{?>
					<font color="red">нет</font>
				<?}?>
			</td>
			<td><?=formatAmount($arr['inAmount'], 0)?></td>
			<td><?=formatAmount($arr['qiwiMerchAmount'], 0)?></td>
			<td><?=formatAmount($arr['yadMerchAmount'], 0)?></td>
			<td><?=formatAmount($arr['walletSAmount'], 0)?></td>
			<td><?=formatAmount($arr['yandexAmount'], 0)?></td>
			<td><?=formatAmount($arr['yandexAccountAmount'], 0)?></td>
			<td><?=formatAmount($arr['simAmount'], 0)?></td>
			<td><?=formatAmount($arr['newYandexAmount'], 0)?></td>
			<td><?=formatAmount($arr['exchangeYadBitAmount'], 0)?></td>
			<td><?=formatAmount($arr['qiwiNewAmount'], 0)?></td>
			<td><?=formatAmount($arr['commissionAmount'], 0)?> (<?=$arr['commissionPercent']?>%)</td>
			<td><?=formatAmount($arr['banAmount'], 0)?></td>
			<td>
				<nobr><?=formatAmount($arr['calcAmount'], 0)?></nobr>

				<?if($arr['bonus']['ym_card_bonus'] != 0){?>
					<br><nobr>(карты <?=formatAmount($arr['bonus']['ym_card_bonus'])?>%)</nobr>
					<?/*= <?=formatAmount($arr['newYandexAmount']*(1+$arr['bonus']['ym_card_bonus']/100), 0)?> руб)<?*/?>
				<?}?>

			</td>
			<td>
				<?if($arr['model']->calc_enabled){?>
					<a href="<?=url('control/CalculateClient', array('clientId'=>$arr['model']->id))?>">рассчитать</a>
				<?}else{?>
					расчеты отключены
				<?}?>
			</td>
		</tr>
	<?}?>

	<tr>
		<td><strong>Всего</strong></td>
		<td><?=formatAmount($stats['allAmount']['balance'], 0)?></td>
		<td></td>
		<td><?=formatAmount($stats['allAmount']['inAmount'], 0)?></td>
		<td><?=formatAmount($stats['allAmount']['qiwiMerchAmount'], 0)?></td>
		<td><?=formatAmount($stats['allAmount']['yadMerchAmount'], 0)?></td>
		<td><?=formatAmount($stats['allAmount']['walletSAmount'], 0)?></td>
		<td><?=formatAmount($stats['allAmount']['yandexAmount'], 0)?></td>
		<td><?=formatAmount($stats['allAmount']['yandexAccountAmount'], 0)?></td>
		<td><?=formatAmount($stats['allAmount']['SimAmount'], 0)?></td>
		<td><?=formatAmount($stats['allAmount']['newYandexAmount'], 0)?></td>
		<td><?=formatAmount($stats['allAmount']['exchangeYadBitAmount'], 0)?></td>
		<td><?=formatAmount($stats['allAmount']['qiwiNewAmount'], 0)?></td>
		<td><?=formatAmount($stats['allAmount']['commissionAmount'], 0)?> (<?=$stats['allAmount']['commissionPercent']?>%)</td>
		<td><?=formatAmount($stats['allAmount']['banAmount'], 0)?></td>
		<td><?=formatAmount($stats['allAmount']['calcAmount'], 0)?></td>
		<td></td>
	</tr>

</table>

<h2 title="Кошельки с ошибками за период(по дате проверки)">Проблемные кошельки</h2>

<?if($stats['errorAccounts']){?>
	<table class="std padding">
		<tr>
			<td>ID</td>
			<td>Логин</td>
			<td>Последний баланс</td>
			<td>Тип</td>
			<td>Проверен</td>
			<td>Ошибка</td>
			<td>Комментарий</td>
			<td>Мыло</td>
			<td>Клиент</td>
		</tr>

		<?foreach($stats['errorAccounts'] as $account){?>
			<tr>
				<td><?=$account->id?></td>
				<td>
					<?if($this->isAdmin()){?>
						<a href="<?=url('account/list', ['login'=>trim($account->login, '+')])?>"><?=$account->login?></a>
					<?}else{?>
						<?=$account->login?>
					<?}?>

				</td>
				<td><?=formatAmount($account->balance, 0)?></td>
				<td>
					<strong><?=$account->type?></strong>

					<?if($account->type == Account::TYPE_IN AND $account->user){?>
						<br/><font color="#a52a2a"><?=$account->user->name?> (<?=$account->user->client->name?>)</font>
					<?}?>
				</td>
				<td><?=$account->dateCheckStr?></td>
				<td><?=$account->error?></td>
				<td><?=$account->comment?></td>
				<td><?=$account->isEmailStr?></td>
				<td><?=$account->client->name?></td>
			</tr>
		<?}?>

	</table>
<?}else{?>
	нет
<?}?>

<?/*
<h2 title="Кошельки на которые менеджеры в данный момент принимают">Текущие кошельки</h2>
(не действует фильтр по времени)

<?if($currentAccounts['slowCheckCount']){?>
	Давно не проверялись: <?=$currentAccounts['slowCheckCount']?> кошельков
<?}?>


<table class="std padding">
	<tr>
		<td>Клиент</td>
		<td>Кошельки</td>
	</tr>

	<?foreach($currentAccounts['clients'] as $clientId=>$arr){?>
		<tr>
			<td><?=$arr['model']->name?></td>
			<td style="padding: 0">
				<table class="padding">
					<tr>

						<td>Логин</td>
						<td>Баланс</td>
						<td>Проверен</td>
						<td>Принято</td>
						<td>Последний платеж</td>
						<td title="Возможная причина долгой проверки кошелька">Сообщение</td>
						<td>Комментарий</td>
					</tr>

					<?foreach($arr['accounts'] as $account){?>
						<tr>
							<td
								<?if($account->error){?>
									style="color: red"
									title="Ошибка: <?=$account->error?>"
								<?}?>
							>
								<a href="<?=url('account/list', array('login'=>trim($account->login, '+')))?>" target="_blank"><?=$account->login?></a>
							</td>
							<td><?=$account->balanceStr?></td>
							<td
								<?if($account->isSlowCheck){?>
									style="color: red"
								<?}?>
							>
								<?=$account->dateCheckStr?>
							</td>
							<td><?=formatAmount($account->getInAmount(), 0)?>  <br>из<br> <?=formatAmount(config('account_in_limit'), 0)?></td>
							<td><?=$account->dateLastTransactionInStr?></td>
							<td></td>
							<td></td>
						</tr>
					<?}?>
				</table>
			</td>
		</tr>
	<?}?>

</table>
*/?>

<br><br/>
<h2>Информация о кошельке</h2>
<form method="post">
	<p>
		<input type="text" name="params['login']" value="<?=$params['login']?>"/>
	</p>

	<p>
		<input type="submit" name="accountInfo" value="получить">
	</p>

</form>