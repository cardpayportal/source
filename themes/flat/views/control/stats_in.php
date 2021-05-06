<?
/**
 * @var ControlController $this
 * @var array $params
 * @var array $result
 * @var float $allAmount
 * @var float $ratAmount
 * @var float $allAmountWithRat
 * @var array $ratTransactions
 */
$this->title = 'Приход';
?>

<?$this->renderPartial('//manager/_statsForm', ['params'=>$params, 'returnUrl'=>'news/list'])?>

<div class="box box-bordered">
	<?if($result){?>
		<div class="box-title">
			<form method="post">
				<h3>
					<i class="fa fa-bars"></i>
					<strong>всего принято:</strong>
					<?if($ratAmount){?>
						<?=formatAmount($allAmount, 0)?> руб
						<span class="ratTransaction" title="Минусы"> - <?=formatAmount($ratAmount)?></span><i class="fa fa-question" title="Минусы"></i>
						=
					<?}?>
					<span class="withComment green" title="Итог"><strong><?=formatAmount($allAmountWithRat, 0)?></strong></span>
				</h3>
			</form>
		</div>
		<div class="box-content">
			<table class="table table-nomargin">
				<thead>
				<tr>
					<th>Пользователь</th>
					<th>Сумма за период</th>
					<th>Кошельки</th>
				</tr>
				</thead>
				<tbody>
					<?foreach($result as $id=>$arr1){?>
						<tr>
							<td><strong><?=$arr1['name']?></strong></td>
							<td><b style="color: green"><?=formatAmount($arr1['amount'], 0)?></b></td>
							<td>
								<?if($arr1['children']){?>
									<table class="table">
										<?foreach($arr1['children'] as $child1){?>
											<tr>
												<td><strong><?=$child1['name']?></strong></td>
												<td><b style="color: green"><?=formatAmount($child1['amount'], 0)?></b></td>

												<td style="text-align: left;">
													<?if($child1['children']){?>
														<table class="std padding">
															<?foreach($child1['children'] as $child2){?>
																<tr>

																	<?
																	$childAmount = 0;

																	foreach($child2['stats'] as $wallet=>$amount)
																		$childAmount += $amount;
																	?>

																	<td>
																		<strong><?=$child2['name']?></strong>
																		<br/>
																		<b style="color: green"><?=formatAmount($childAmount, 0)?></b>
																	</td>
																	<td style="text-align: left">
																		<table class="noBorder left">
																		<?foreach($child2['stats'] as $wallet=>$amount){?>
																			<tr>
																				<td><?=$wallet?></td>
																				<td><b style="color: green"><?=formatAmount($amount, 0)?></b></td>
																			</tr>
																		<?}?>
																		</table>
																	</td>
																</tr>
															<?}?>
														</table>
													<?}else{?>
														<table class="noBorder left">
															<?foreach($child1['stats'] as $wallet=>$amount){?>
																<tr>
																	<td><?=$wallet?></td>
																	<td><b style="color: green"><?=formatAmount($amount, 0)?></b></td>
																</tr>
															<?}?>
														</table>
													<?}?>
												</td>
											</tr>
										<?}?>
									</table>
								<?}elseif($arr1['wallets']){?>
									<span style="text-align: left">
										<table class="noBorder left">
											<?foreach($arr1['wallets'] as $wallet=>$amount){?>
												<tr>
													<td><?=$wallet?></td>
													<td><b style="color: green"><?=formatAmount($amount, 0)?></b></td>
												</tr>
											<?}?>
										</table>
									</span>
								<?}?>
							</td>
						</tr>
					<?}?>
				</tbody>
			</table>
		</div>
	<?}else{?>
		<br><br><b>не найдено статистики за выбранный период</b>
	<?}?>
</div>


<?if($ratTransactions){?>
	<div class="box box-bordered">
		<div class="box-title">
			<form method="post">
				<h3>
					<i class="fa fa-bars"></i>
					Минусы (<?=count($ratTransactions)?>)
					<span class="ratTransaction">-<?=formatAmount($ratAmount, 0)?> руб</span>
				</h3>
			</form>
		</div>
		<div class="box-content">
			<table class="table table-nomargin">
				<tbody>
					<?foreach($ratTransactions as $num=>$trans){?>
						<?=$this->renderPartial('//manager/_transaction', array(
							'num'=>$num,
							'trans'=>$trans,
							'showLogin'=>true,
						))?>
					<?}?>
				</tbody>
			</table>

			<?if($num > 2){?>
				<br /><button class="showTransactions">Показать все</button>
			<?}?>

		</div>
	</div>
<?}?>
