<?
/**
 * @var ControlController $this
 * @var array $params
 * @var Account[] $oldAccounts
 *
 */

$this->title = 'AntiBan';

?>

<h1><?=$this->title?></h1>

<p>
	<a href="<?=url('control/banChecker')?>">Ban Checker</a>
</p>

<h2>Блокировка цепочки</h2>

<form method="post">
	<p>
		<i>
			Заблокировать все кошельки клиента в определенной цепочке.
			Ставит error=ban, comment=отключен админом.
			Банит только юзаные кошельки, на которые переводили ср-ва.
		</i>
	</p>

	<label>
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
	</label>

	<label>
		GroupId
		<input type="text" name="params[groupId]" value="<?=$params['groupId']?>">
	</label>

	<label>
		<input type="checkbox" name="params[withCleanWallets]" value="true">
		вместе с чистыми
	</label>

	<input type="submit" name="banGroup" value="Забанить группу">

</form>

<br><hr><br>

	<h2>Блокировка отдельных кошельков</h2>

	<form method="post">
		<p>
			<i>
				Заблокировать выбранные кошельки
			</i>
		</p>

		<label>
			Кошельки: (по 1 на строку), +72636373636...<br>
			<textarea name="params[walletsStr]" cols="15" rows="5"><?=$params['walletsStr']?></textarea>
		</label>

		<br>
		<input type="submit" name="banMany" value="Забанить выбранные">

	</form>

<br><hr><br>

<h2>Перевод с проблемных (<?=Account::ERROR_BAN?>, <?=Account::ERROR_LIMIT_OUT?>)</h2>

<?=$msg?>

<form method="post">
	<b>Откуда</b><br>
	<textarea name="params[from]" rows="10" cols="30"><?=$params['from']?></textarea><br><br>

	<b>Куда</b><br>
	<input type="text" name="params[to]" value="<?=$params['to']?>"><br>
	<input type="submit" name="trans" value="Перевод">
</form>

<br><hr><br>

<h2>Группа риска</h2>

<p><i>старые кошельки, могут слететь пароли</i></p>

<?if($oldAccounts){?>

	<p><b>Всего: <?=count($oldAccounts)?></b></p>

	<table class="std padding">
		<thead>
			<tr>
				<td>Кошелек</td>
				<td>Дата добавления</td>
				<td>ClientId</td>
				<td>Статус</td>
				<td>Лимит</td>
				<td>Дата проверки</td>
			</tr>
		</thead>
		<tbody>
			<?foreach($oldAccounts as $account){?>
				<tr>
					<td><a target="_blank" href="<?=url('account/list', array('login'=>trim($account->login, '+')))?>"><?=$account->login?></a></td>
					<td style="background-color: #999999"><?=$account->dateAddStr?></td>
					<td><?=$account->client->name?></td>
					<td><?=$account->statusStr?></td>
					<td><?=formatAmount($account->limit_in, 0)?></td>
					<td><?=$account->dateCheckStr?></td>
				</tr>
			<?}?>
		</tbody>
	</table>

<?}else{?>
	нет кошельков
<?}?>