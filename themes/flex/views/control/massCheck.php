<?
/**
 * @var ControlController $this
 * @var int $clientId
 * @var Account[] $notCheckedAccounts
 * @var array $params
 *
 */
$this->title = 'Mass check';
?>

<h1><?=$this->title?></h1>

<p><i>Обновляет платежи на входящих клиента</i></p>
<p><i>Вбить id активного клиента и дату, с которой надо обновить платежи</i></p>
<p><i>Есть ограничение по минимальной дате и по кол-ву проверяемых кошельков (может вызвать тормоза на активных кошельках у клиента)</i></p>

<form method="post">

	<p>
		<b>Client ID</b><br>
		<input type="text" name="params[clientId]" value="<?=($params['clientId']) ? $params['clientId'] : $clientId?>">
	</p>

	<p>
		<b>Date</b><br>
		<input type="text" name="params[date]" value="<?=($params['date']) ? $params['date'] : date('d.m.Y 00:00', time())?>">
	</p>

	<p>
		<input type="submit" name="submit" value="Отправить">
	</p>

</form>


<?if($notCheckedAccounts){?>
	<h2>Кошельки на проверке (Client<?=$clientId?>)</h2>

	<p>Всего: <?=count($notCheckedAccounts)?></p>

	<table class="std padding">
		<tr>
			<td>Кошелек</td>
			<td>Дата последней проверки</td>
		</tr>

		<?foreach($notCheckedAccounts as $account){?>
			<tr>
				<td><?=$account->login?></td>
				<td><?=$account->dateCheckStr?></td>
			</tr>
		<?}?>

	</table>
<?}?>
