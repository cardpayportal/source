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
$this->title = 'Глобальная статистика';

$dateTo = time()+24*3600;
$now = time();
$periods = [
	['dateFrom'=>date('d.m.Y', $now), 'dateTo'=>date('d.m.Y', $dateTo), 'name'=>'за сегодня'],
	['dateFrom'=>date('d.m.Y', $now - 3600*24), 'dateTo'=>date('d.m.Y'), 'name'=>'за вчера'],
];
?>
<div class="box box-bordered">
	<div class="box-title">
		<h3><i class="fa fa-filter"></i>выберите интервал</h3>
	</div>
	<div class="box-content">
		<form method="post" class="form-vertical">
			<div class="row">
				<div class="col-sm-3">
					<label for="textfield0" class="control-label">Клиент</label>
					<select name="params[clientId]" class="form-control" id="textfield0">
						<option value="">Все</option>
						<?foreach(Client::getArr() as $id=>$name){?>
							<option value="<?=$id?>"
								<?if($params['clientId']==$id){?>
									selected="selected"
								<?}?>
							><?=$name?></option>
						<?}?>
					</select>
				</div>

				<div class="col-sm-3">
					<label for="textfield1" class="control-label">С</label>
					<input type="text" name="params[date_from]" value="<?=$params['date_from']?>" id="textfield1" class="form-control"/>
				</div>

				<div class="col-sm-3">
					<label for="textfield2" class="control-label">По</label>
					<input type="text" name="params[date_to]" value="<?=$params['date_to']?>" id="textfield2" class="form-control"/>
				</div>

				<div class="col-sm-3">
					<br>&nbsp;
					<br>&nbsp;
					<button type="submit" class="btn btn-primary" name="stats" value="Показать">Показать</button>
				</div>
			</div>
			<br>
			<div class="row">
				&nbsp;&nbsp;&nbsp; <i>максимальный интервал - 2 дня</i>
			</div>
		</form>
	</div>

	<?foreach($periods as $period){?>
		<form method="post" style="display: inline" >
			<input type="hidden" name="params[date_from]" value="<?=$period['dateFrom']?>" />
			<input type="hidden" name="params[date_to]" value="<?=$period['dateTo']?>" />
			<input type="hidden" name="params[clientId]" value="<?=$params['clientId']?>" />
			<button type="submit" class="btn" name="stats" value="Показать"><?=$period['name']?></button>
		</form>
	<?}?>
</div>

<div class="box box-bordered">
	<div class="box-title">
		<h3><i class="fa fa-bars"></i>Приход/Расход</h3>
	</div>
	<div class="box-content">
		<table class="table table-bordered table-colored-header">
			<thead>
			<td>Клиент</td>
			<td><span class="dotted" title="Текущий баланс всех кошельков клиента">Баланс</span></td>
			<td title="Сливается ли в данный момент с исходящих кошельков клиента">Сливается</td>
			<td title="приход на Qiwi">Qiwi</td>
			<td title="приход на Qiwi Merchant Adgroup">Qiwi2</td>
			<td title="приход на Yandex Merchant Adgroup">Yandex Merchant</td>
			<td title="приход на WalleteSvoe">WalletS</td>
			<td title="Сколько пришло WEX-кодами">WEX</td>
			<td title="Сколько пришло Yandex Money">ЯД</td>
			<td title="Сколько пришло Yandex кошельки">Yandex Wallet</td>
			<td title="Сколько пришло Sim-кошельки">Sim</td>
			<td title="Сколько пришло по картам">Карты</td>
			<td title="приход на QiwiNew">QiwiNew</td>
			<td title="Сколько ушло с исходящих кошельков клиента по заявкам финансиста за период">Ушло</td>
			<td title="">Комиссия</td>
			<td title="Заблокировано средств за период">Бан</td>
			<td>Расчет</td>
			<td>Действие</td>
			</thead>
			<tbody>
				<?$calcTotal = 0?>
				<?foreach($stats['clients'] as $clientId=>$arr){?>
					<tr>
						<td><b><?=$arr['model']->name?></b> (id=<?=$arr['model']->id?>)</td>
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
						<td><?=formatAmount($arr['couponAmount'], 0)?></td>
						<td><?=formatAmount($arr['yandexAmount'], 0)?></td>
						<td><?=formatAmount($arr['yandexAccountAmount'], 0)?></td>
						<td><?=formatAmount($arr['simAmount'], 0)?></td>
						<td><?=formatAmount($arr['newYandexAmount'], 0)?></td>
						<td><?=formatAmount($arr['qiwiNewAmount'], 0)?></td>
						<td><?=formatAmount($arr['outAmount'], 0)?></td>
						<td><?=formatAmount($arr['commissionAmount'], 0)?> (<?=$arr['commissionPercent']?>%)</td>
						<td><?=formatAmount($arr['banAmount'], 0)?></td>
						<td><?=formatAmount($arr['calcAmount'], 0)?></td>
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
					<td><?=formatAmount($stats['allAmount']['couponAmount'], 0)?></td>
					<td><?=formatAmount($stats['allAmount']['yandexAmount'], 0)?></td>
					<td><?=formatAmount($stats['allAmount']['yandexAccountAmount'], 0)?></td>
					<td><?=formatAmount($stats['allAmount']['SimAmount'], 0)?></td>
					<td><?=formatAmount($stats['allAmount']['newYandexAmount'], 0)?></td>
					<td><?=formatAmount($stats['allAmount']['qiwiNewAmount'], 0)?></td>
					<td><?=formatAmount($stats['allAmount']['outAmount'], 0)?></td>
					<td><?=formatAmount($stats['allAmount']['commissionAmount'], 0)?> (<?=$stats['allAmount']['commissionPercent']?>%)</td>
					<td><?=formatAmount($stats['allAmount']['banAmount'], 0)?></td>
					<td><?=formatAmount($stats['allAmount']['calcAmount'], 0)?></td>
					<td></td>
				</tr>
			</tbody>
		</table>
	</div>
</div>


<div class="box box-bordered">
	<div class="box-title">
		<h3 title="Кошельки с ошибками за период(по дате проверки)"><i class="fa fa-bars"></i>Проблемные кошельки</h3>
	</div>
	<div class="box-content">
		<?if($stats['errorAccounts']){?>
			<table class="table table-bordered table-colored-header">
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
	</div>
</div>

<div class="box box-bordered">
		<div class="box-title">
			<h3><i class="fa fa-info"></i>Информация о кошельке</h3>
		</div>
		<div class="box-content">
			<form method="post" class="form-vertical form-bordered">
				<div class="form-group">
					<label for="textfield0" class="control-label">Номен кошелька</label>
					<input type="text" name="params['login']" value="<?=$params['login']?>" class="form-control"/>
				</div>

				<div class="form-actions">
					<button type="submit" class="btn btn-primary" name="accountInfo" value="получить">получить</button>
				</div>
			</form>
		</div>
</div>