<?
/**
 * @var ManagerController $this
 * @var array $params
 * @var array $orderConfig	для заявочной системы
 * @var bool $showForm
 */

$this->title = 'Добавить заявку'
?>

<h2><?=$this->title?></h2>

<?if($showForm){?>
	<form method="post">
		<p>
			<label>
				<b>Сумма заявки</b> (максимум: <?=formatAmount($orderConfig['order_amount_max'], 0)?>)<br>
				<input size="10" type="text" name="params[amount]" value="<?=($params['amount']) ? $params['amount'] : ''?>" /> руб
			</label>
			<br>
		</p>

		<p>
			(<i>сумма на кошелек: <?=formatAmount($orderConfig['wallet_amount_min'], 0)?> руб</i>)
		</p>

		<p>
			<input type="submit" name="add" value="Создать заявку" style="" />
		</p>
	</form>
<?}else{?>
	завершите <a href="<?=url('manager/orderList')?>">активные заявки</a> перед добавлением новых
<?}?>
