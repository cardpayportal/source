<?
/**
 * @var TransactionWalletSController $this
 * @var array $params
 * @var string $payUrl
 * @var WalletSTransaction[] $models
 * @var array $stats
 * @var array $filter
 */

$this->title = 'WalletS заявки и платежи';
$action = 'walletS/TransactionWalletS/list';
?>

<h1><?=$this->title?></h1>

<fieldset>
	<legend>Выберите даты отображаемых платежей</legend>
	<p>
	<form method="post" action="<?= url($action) ?>">
		с <input type="text" name="params[dateStart]" value="<?= $interval['dateStart'] ?>"/>
		до <input type="text" name="params[dateEnd]" value="<?= $interval['dateEnd'] ?>"/>
		<input type="submit" name="stats" value="Показать"/>
	</form>
	</p>

	<p>
	<form method="post" action="<?= url($action) ?>" style="display: inline">
		<input type="hidden" name="params[dateStart]" value="<?= date('d.m.Y H:i', Tools::startOfDay(time())) ?>"/>
		<input type="hidden" name="params[dateEnd]"
			   value="<?= date('d.m.Y H:i', Tools::startOfDay(time() + 24 * 3600)) ?>"/>
		<input type="submit" name="stats" value="За сегодня"/>
	</form>

	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

	<form method="post" action="<?= url($action) ?>" style="display: inline">
		<input type="hidden" name="params[dateStart]"
			   value="<?= date('d.m.Y H:i', Tools::startOfDay(time() - 24 * 3600)) ?>"/>
		<input type="hidden" name="params[dateEnd]" value="<?= date('d.m.Y H:i', Tools::startOfDay(time())) ?>"/>
		<input type="submit" name="stats" value="За вчера"/>
	</form>
	</p>
</fieldset>

<br>

<?if($models){?>
	<?if(User::getUser()->client->checkRule('pagination')){?>
		<div class="pagination">
			<?$this->widget('CLinkPager', array(
				'pages' => $pages,
			))?>
		</div>
	<?}?>
	<table class="std padding">

		<thead>
			<th>ID</th>
			<th>Сумма</th>
			<th>OrderId</th>
			<th>Статус</th>
			<th>Добавлен</th>
			<th>Коммент</th>
			<th>Ссылка</th>
		</thead>

		<?foreach($models as $model){?>
			<tr>
				<td><?=$model->id?></td>
				<td><b><?=$model->amountStr?></b></td>
				<td><b><?=$model->order_id?></b></td>
				<td>
					<?if($model->status == WalletSTransaction::STATUS_SUCCESS){?>
						<span class="success"><?=$model->statusStr?></span>
						<br><?=$model->datePayStr?>
					<?}elseif($model->status == WalletSTransaction::STATUS_ERROR){?>
						<span class="error"><?=$model->statusStr?></span>
						<br>(<?=$model->error?>)
					<?}elseif($model->status == WalletSTransaction::STATUS_PROCESSING){?>
						<span class="processing"><?=$model->statusStr?></span>
					<?}elseif($model->status == WalletSTransaction::STATUS_WAIT){?>
						<span class="wait"><?=$model->statusStr?></span>
					<?}?>
				</td>
				<td>
					<?=$model->dateAddStr?>
				</td>
				<td>
					<?=$model->comment?><br>
					<?=$model->client_order_id?>
				</td>
				<td>
					<span class="shortContent"><?=$model->urlShort?></span>
					<input style="display: none" type="text" size="40" value="<?=$model->pay_url?>" class="click2select fullContent">
				</td>
			</tr>
		<?}?>
	</table>
<?}else{?>
	записей не найдено
<?}?>

<script>
	$(document).ready(function(){
		$('.shortContent').click(function(){
			$(this).hide();
			$(this).parent().find('.fullContent').show().select();
		});
	});

	$(document).mouseup(function (e){
		var div = $(".fullContent");
		if (!div.is(e.target)
			&& div.has(e.target).length === 0) {
			div.hide(); // скрываем его
			div.parent().find('.shortContent').show();
		}
	});
</script>
