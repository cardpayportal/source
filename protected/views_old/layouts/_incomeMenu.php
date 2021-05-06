<?
/**
 * @var int $currentAccountCount
 */

$user = User::getUser();

$incomeMode = $user->client->income_mode;
?>

<?if($incomeMode == Client::INCOME_WALLET){?>
	<span><a href="<?=url('manager/accountList')?>">Кошельки (<?=$currentAccountCount?>)</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('manager/accountAdd')?>">Получить кошельки</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<a href="<?=url('manager/accountUsed')?>">Использованные кошельки</a>
<?}elseif($incomeMode == Client::INCOME_ORDER){?>
	<span><a href="<?=url('manager/orderList')?>">Активные заявки</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('manager/orderAdd')?>">Добавить заявку</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<a href="<?=url('manager/orderUsed')?>">Старые заявки</a>
<?}?>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<a href="<?=url('manager/stats')?>">Статистика</a>
