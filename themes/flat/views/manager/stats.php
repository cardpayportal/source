<?
/**
 * @var ManagerController $this
 * @var array $params
 * @var float $allAmount
 * @var array $stats
 */

$this->title = 'Статистика';
?>

<?$this->renderPartial('//manager/_statsForm', ['params'=>$params, 'returnUrl'=>'manager/orderList'])?>

<div class="box box-bordered">
	<?if($stats){?>
		<div class="box-title">
			<form method="post">
				<h3>
					<i class="fa fa-bars"></i>
					<strong>всего принято:</strong> <?=formatAmount($allAmount, 0)?> руб
				</h3>
			</form>
		</div>
		<div class="box-content">
			<table class="table table-nomargin">
				<thead>
				<tr>
					<th>Кошелек</th>
					<th>Сумма</th>
				</tr>
				</thead>
				<tbody>
					<?foreach($stats as $wallet=>$amount){?>
						<tr>
							<td><?=$wallet?></td>
							<td><?=$amount?></td>
						</tr>
					<?}?>
				</tbody>
			</table>
		</div>
	<?}else{?>
		<br><br><b>не найдено переводов за выбранный период</b>
	<?}?>
</div>
