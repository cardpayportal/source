<?
/**
 * @var ManagerController $this
 * @var array $params
 */

$this->title = 'ЯД';
?>

<form method="post" action="">

	<p>
		<b>Сумма:</b><br>
		<input type="text" name="params[amount]" value="<?=$params['amount']?>"> руб
	</p>

	<p>
		<input type="submit" name="pay" value="Перейти к оплате"/>
	</p>

</form>
