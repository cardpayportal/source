<?
/**
 * @var ControlController $this
 */
?>

<a href="<?=url('control/storeApi')?>">Главная</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<a href="<?=url('control/storeApiTransactions')?>">Поступления</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<a href="<?=url('control/storeApiWithdraw')?>">Выводы</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<a href="<?=url('control/storeApiList')?>">Магазины</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

<?if($this->isAdmin()){?>
	<a href="<?=url('control/storeApiRequest')?>">Запросы (Админ)</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<a href="<?=url('control/storeApiLog')?>">Логи (Админ)</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<?}?>

