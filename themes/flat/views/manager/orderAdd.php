<?
/**
 * @var ManagerController $this
 * @var array $params
 * @var array $orderConfig	для заявочной системы
 * @var bool $showForm
 */

$this->title = 'Добавить заявку'
?>

<?if($showForm){?>
<div class="box box-bordered">
	<div class="box-title">
		<h3><i class="fa fa-bars"></i>Новая заявка</h3>
	</div>
	<div class="box-content nopadding">
		<form method="post" class="form-vertical form-bordered">
			<div class="form-group">
				<label for="textfield" class="control-label">Сумма (руб)</label>
				<input type="text" name="params[amount]" value="<?=($params['amount']) ? $params['amount'] : ''?>" id="textfield" class="form-control"/>
				<span class="help-block">
					максимум: <?=formatAmount($orderConfig['order_amount_max'], 0)?> руб
				</span>
			</div>
			<div class="form-actions">
				<button type="submit" class="btn btn-primary" name="add" value="Создать заявку">Добавить</button>
				<a href="<?=url('manager/orderList')?>"><button type="button" class="btn">Отмена</button></a>
			</div>
		</form>
	</div>
</div>
<?}else{?>
	завершите
	<a href="<?=url('manager/orderList')?>">
		<button type="button" class="btn">активные заявки</button>
	</a>
	перед добавлением новых
<?}?>