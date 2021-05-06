<meta http-equiv="Refresh" content="30" />
<?
/**
 * @var RisexTransaction[] $transactions
 * @var array      $params
 * @var array      $stats
 * @var int        $pages
 */
$this->title = 'Статистика p2pService'

?>

<fieldset>
	<legend>Выберите даты отображаемых платежей</legend>
	<p>
		<form method="post" action="<?= url('p2pService/fin/statistic') ?>">

			<b>От</b> <input type="text" name="params[dateStart]" value="<?=$interval['dateStart']?>">
			<b>До</b> <input type="text" name="params[dateEnd]" value="<?=$interval['dateEnd']?>">

			<input type="submit" name="statsByDate" value="Показать"/>
		</form>
	</p>
</fieldset>
<br>
<br>


<? if($stats){ ?>

	<?foreach($stats as $userName=>$userInfo){?>
			<p>
				<b>
					<span class="warning"><?= $userName ?></span>&nbsp;&nbsp;&nbsp;
					<b>Принято за выбранный период: <span class="success"><?= formatAmount($userInfo['stats']['amount'], 0) ?></span></b>
				</b>
			</p>

		<table class="std padding" width="100%">

			<thead>
			<tr cols="4">
				<th><span class="withComment" title="">Карта</span></th>
				<th>Сумма</th>
				<th>Статус</th>
				<th>Дата</th>
			</tr>
			</thead>

			<tbody>
			<? foreach($userInfo['transactions'] as $key=>$model){ ?>
				<tr cols="4" <? if($key > 2){ ?>
					data-param="toggleRow"
					style="display: none;"
				<? } ?>>
					<td><b><?= $model->requisites ? $model->requisitesStr : 'Подбор карты ...' ?></b></td>
					<td><?= $model->fiat_amount.' '.$model->currencyStr ?></td>
					<td> <? if(in_array($model->status, [RisexTransaction::STATUS_ERROR, RisexTransaction::STATUS_AUTOCANCELED, RisexTransaction::STATUS_CANCELED])){ ?>
							<span class="error"><b><?= $model->statusStr ?></b></span>
						<? }elseif(in_array($model->status, [RisexTransaction::STATUS_SELLER_REQUISITE, RisexTransaction::STATUS_VERIFICATION])){ ?>
							<span class="processing"><b><?= $model->statusStr ?></b></span>
							<br><?= $model->dateCancelationStr ?>
						<? }elseif(in_array($model->status, [RisexTransaction::STATUS_FINISHED])){ ?>
							<span class="success"><b><?= $model->statusStr ?></b></span>
						<? }elseif(in_array($model->status, [RisexTransaction::STATUS_PAID])) { ?>
							<span class="warning withComment" title="Идет проверка платежа"><b><?= $model->statusStr ?></b></span>
						<? }else{ ?>
							<span class="dotted"><b><?= $model->statusStr ?></b></span>
						<?}?>
					</td>
					<td><?= $model->createdAtStr ?></td>
				</tr>
			<? } ?>
			</tbody>
		</table>

		<? if(isset($userInfo['transactions']) and count($userInfo['transactions']) > 2){ ?>
			<button type="button" class="btn btn-info btn-mini showTransactions btn--icon"
					style="margin-left: 47%">
				<i class="fa fa-caret-down"></i>показать все
			</button>

			<button type="button" class="btn btn-info btn-mini hideTransactions btn--icon"
					style="display: none; margin-left: 47%">
				<i class="fa fa-caret-up"></i>скрыть
			</button>
		<? } ?>

		<? } ?>
<? } ?>


<script>
	$(document).ready(function () {

		<?//показать все транзакции аккаунта?>
		$(document).on("click", ".showTransactions", function () {
			$(this).parent().find('tr[data-param=toggleRow]').show();
			$(this).text('Скрыть');
			$(this).removeClass('showTransactions');
			$(this).addClass('hideTransactions');
		});

		<?//скрыть старые транзакции?>
		$(document).on("click", ".hideTransactions", function () {
			$(this).parent().find('tr[data-param=toggleRow]').hide();
			$(this).text('Показать все');
			$(this).removeClass('hideTransactions');
			$(this).addClass('showTransactions');
		});

	});
</script>