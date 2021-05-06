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

<div class="row" id="bootstrap-date">
	<div class="col-md-12">
		<div class="panel widget">
			<div class="panel-heading vd_bg-grey">
				<h3 class="panel-title">
					<span class="menu-icon">
						<i class="fa fa-calendar"></i>
					</span>Выберите даты отображаемых платежей
				</h3>
			</div>
			<div class="panel-body">
				<form method="post" action="<?=url($action)?>" class="form-horizontal">


					<div class="form-group">
						<label class="col-sm-1 control-label">С:</label>
						<div class='col-md-4 controls'>
							<div class='input-group date' id='datetimepicker6'>
								<input type='text' name="params[dateStart]" value="<?=$interval['dateStart']?>" class="form-control" />
								<span class="input-group-addon">
									<span class="glyphicon glyphicon-calendar"></span>
								</span>
							</div>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-1 control-label">По:</label>
						<div class='col-md-4 controls'>
							<div class='input-group date' id='datetimepicker7'>
								<input type='text' name="params[dateEnd]" value="<?=$interval['dateEnd']?>" class="form-control" />
								<span class="input-group-addon">
									<span class="glyphicon glyphicon-calendar"></span>
								</span>
							</div>
						</div>

						<div class="btn-group btn-group-md mgbt-xs-5">
							<button type="submit" name="stats" class="btn btn-default">По датам</button>
							<button type="button" class="btn btn-default" name="stats"
									valueFrom="<?= date('d.m.Y H:i', Tools::startOfDay(time())) ?>"
									valueTo="<?= date('d.m.Y H:i', Tools::startOfDay(time() + 24 * 3600)) ?>"
							>За сегодня</button>
							<button type="button" class="btn btn-default"name="stats"
									valueFrom="<?= date('d.m.Y H:i', Tools::startOfDay(time() - 24 * 3600)) ?>"
									valueTo="<?= date('d.m.Y H:i', Tools::startOfDay(time())) ?>"
							>За вчера</button>
						</div>
					</div>

				</form>

			</div>
		</div>
		<!-- Panel Widget -->
	</div>
	<!-- col-md-12 -->
</div>


<!-- row -->

<div class="row">
	<div class="col-md-12">
		<div class="panel widget">
			<div class="panel-heading vd_bg-grey">
				<h3 class="panel-title"> <span class="menu-icon"> <i class="fa fa-dot-circle-o"></i> </span> <?=$this->title?> </h3>
			</div>
<?if($models){?>

		<div class="panel-body  table-responsive">
			<table class="table table-bordered">
				<?if(User::getUser()->client->checkRule('pagination')){?>
					<ul class="pagination">
						<?$this->widget('CLinkPager', array(
							'pages' => $pages,
							//'internalPageCssClass' => '',
							'selectedPageCssClass' => 'active',
							'hiddenPageCssClass' => 'disabled',
							'nextPageLabel' => '&raquo;',         // »
							'header' => '',         // »
							'prevPageLabel' => '&laquo;',         // «
							'lastPageLabel' => '&raquo;&raquo;',  // »»
							'firstPageLabel' => '&laquo;&laquo;', // ««
							'htmlOptions' => array('class' => 'pagination'),
						))?>
					</ul>
				<?}?>
		<thead>
			<th>ID</th>
			<th>Сумма</th>
			<th>OrderId</th>
			<th>Статус</th>
			<th>Добавлен</th>
			<th>Коммент</th>
			<th>Ссылка</th>
		</thead>

		<tbody>
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
		</tbody>
		</table>
			<ul class="pagination">
				<?$this->widget('CLinkPager', array(
					'pages' => $pages,
					//'internalPageCssClass' => '',
					'selectedPageCssClass' => 'active',
					'hiddenPageCssClass' => 'disabled',
					'nextPageLabel' => '&raquo;',         // »
					'header' => '',         // »
					'prevPageLabel' => '&laquo;',         // «
					'lastPageLabel' => '&raquo;&raquo;',  // »»
					'firstPageLabel' => '&laquo;&laquo;', // ««
					'htmlOptions' => array('class' => 'pagination'),
				))?>
			</ul>
		</div>
		<?}else{?>
			<div class="col-md-12">
				<label>
					записей не найдено
				</label>
			</div>

		<?}?>
		</div>
	</div>
	<!-- Panel Widget -->
</div>
<!-- col-md-12 -->

<script>
	$(document).ready(function(){
		$('.shortContent').click(function(){
			$(this).hide();
			$(this).parent().find('.fullContent').show().select();
		});

		$(document).mouseup(function (e){
			var div = $(".fullContent");
			if (!div.is(e.target)
				&& div.has(e.target).length === 0) {
				div.hide(); // скрываем его
				div.parent().find('.shortContent').show();
			}
		});

		$(function () {
			$('#datetimepicker6').datetimepicker();
			$('#datetimepicker7').datetimepicker({
				useCurrent: false //Important! See issue #1075
			});
			$("#datetimepicker6").on("dp.change", function (e) {
				$('#datetimepicker7').data("DateTimePicker").minDate(e.date);
			});
			$("#datetimepicker7").on("dp.change", function (e) {
				$('#datetimepicker6').data("DateTimePicker").maxDate(e.date);
			});
		});
	});


</script>

