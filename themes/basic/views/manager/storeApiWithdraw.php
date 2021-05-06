<?
$this->title = 'Выводы';

/**
 * @var ManagerController $this
 * @var StoreApiWithdraw[] $models
 * @var array $filter
 * @var array $stats
 * @var array $params
 * @var User user
 *
 */
?>

<h1><?=$this->title?></h1>

<form method="post">
	<p>
		<b>Адрес для вывода BTC:</b><br>
		<input type="text" size="40" name="params[btc_address]" value="<?=$user->store->withdraw_wallet?>"/>
	</p>

	<p>
		<b>Result Url (уведомление о платеже):</b><br>
		<input type="text" size="40" name="params[url_result]" value="<?=$user->store->url_result?>"/>
	</p>

	<p>
		<b>Return Url (переадресация клиента):</b><br>
		<input type="text" size="40" name="params[url_return]" value="<?=$user->store->url_return?>"/>
	</p>
	<p>
		<input type="submit" name="setBtcAddress" value="Установить">
	</p>
</form>
<hr><br>


<p>
	<?=$this->renderPartial('//layouts/_filterFormStoreApi', array('filter'=>$filter))?>
</p>

<p>
	Всего: <b><?=$stats['count']?></b> выводов &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	на сумму:
	<b style="color: #33CC00"><?=formatAmount($stats['amountRub'], 0)?> руб</b>,
	<b style="color: brown"><?=formatAmount($stats['amountBtc'], 5)?> btc</b>,
	<b style="color: #333399"><?=formatAmount($stats['amountUsd'], 0)?> usd</b>
</p>

<?if($models){?>
	<?$this->renderPartial('_withdrawList', ['models'=>$models])?>
<?}else{?>
	выводы не найдены
<?}?>

