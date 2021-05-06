<?
/**
 * @var ControlController $this
 * @var BanChecker[] $models
 * @var array $params
 * @var array $stats
 * @var Account $activeAccounts
 * @var array $clientWallets
 */

$this->title = 'Ban checker';
?>


<h1><?=$this->title?></h1>

<p>
	<a href="<?=url('control/antiban')?>">AntiBan</a>
</p>

<br><hr><br>

<h2>Выборка кошельков</h2>

<?if($activeAccounts){?>
	<b>Наши кошельки:(<?=count($activeAccounts)?>)</b> <br>
	<?foreach($activeAccounts as $account){?>
		<?=$account->login?> <?=$account->type?><br>
	<?}?>
<?}elseif($clientWallets){?>
	<b>Клиентские кошельки:(<?=count($clientWallets)?>)</b> <br>
	<?foreach($clientWallets as $clientWallet){?>
		<?=$clientWallet?><br>
	<?}?>
<?}?>

<form method="post">
	<p>
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

		<input type="submit" name="selectActiveAccounts" value="Выбрать активные кошельки для проверки">
		&nbsp;&nbsp;
		<input type="submit" name="selectClientWallets" value="Выбрать кошельки клиента">
		<br>
		<b>Активные</b> - Кошельки без ошибки, с лимитом > 0, с лимитом < 190000, date_used=0 , is_old=0<br>
		<b>Кошельки клиента</b> - С которых приходили платежи на наши за последние 30 дней(в порядке убывания даты). Не показывает те, с которых были огры(клиенту можно сообщить о бане заранее)
	</p>
</form>

<br><hr><br>

<h2>Поиск связей</h2>

<form method="post">
	<p>
		<i>
			Ищет только по успешным переводам. Выдает только актуальные кошельки, на которые сливалось с указанного.
			Только прямые связи.
		</i>
	</p>

	<label>
		Кошелек<br>
		<input type="text" name="params[wallet]" value="<?=$params['wallet']?>">
	</label>

	<input type="submit" name="linkSearch" value="Поиск">
</form>

<br><hr><br>

<h2>Добавить на проверку</h2>

<form method="post">
	<p>
		<b>Логины +7|3... (каждый с новой строки)</b><br>
		<i>добавлять кошельки повторно можно через <?=(BanChecker::ADD_DUPLICATE_INTERVAL/60)?> мин</i><br>
		<i>Блокирует найденные в панели кошельки ("AntiBan: отключен админом")</i><br>
		<textarea name="params[loginContent]" rows="10" cols="25"><?=$params['loginContent']?></textarea>
	</p>

	<p>
		<input type="submit" name="add" value="Добавить на проверку">
	</p>
</form>

<br><hr><br>

<?if($models){?>

	<p>
		<b>Всего: <?=$stats['allCount']?></b>
		,&nbsp;&nbsp;&nbsp;<b>BAN: <?=$stats['banCount']?> из <?=$stats['errorCount']?>(ошибок)</b>
		, &nbsp;&nbsp;&nbsp;<b>OK: <?=$stats['goodCount']?></b>
		, &nbsp;&nbsp;&nbsp;<b>Не проверено: <?=$stats['notCheckCount']?></b>
		, &nbsp;&nbsp;&nbsp;<b><span class="dotted" title="Кол-во банов в группе кошельков, добавленной последней">Последних банов:</span>  <?=$stats['lastBanCount']?> из <?=$stats['lastCount']?></b>
	</p>

	<form method="post">
		<p>
			<input type="submit" name="clearOld" value="Очистить старые">
			<i>удаляет проверенные более <?=(BanChecker::OLD_INTERVAL/60)?> мин назад кошельки (не забаненые)</i>
		</p>
	</form>

	<table class="std padding">
		<tr>
			<td>Логин</td>
			<td>Ошибка</td>
			<td>Проверка</td>
			<td>Добавлен</td>
			<td>Примечание</td>
		</tr>

		<?foreach($models as $model){?>
			<tr>
				<td><?=$model->login?></td>
				<td><?=$model->errorStr?></td>
				<td><?=$model->dateCheckStr?></td>
				<td><?=$model->dateAddStr?></td>
				<td><?=$model->messageStr?></td>
			</tr>
		<?}?>
	</table>
<?}else{?>
	кошельков не найдено
<?}?>
