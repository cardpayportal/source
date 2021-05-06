<?
/**
 * @var ManagerController $this
 * @var array $params
 * @var string $payUrl
 * @var TransactionWex[] $models
 * @var WexAccount $wexAccount
 * @var array $stats
 */

$this->title = 'История поступлений';
?>

	<h1><?=$this->title?></h1>


	<p>
		<b>Всего:</b> <?=formatAmount($stats['count'], 0)?> платежей на сумму <?=formatAmount($stats['amount'], 0)?>
	</p>

	<form method="post" action="<?=url('manager/yandexPay')?>">
		<input type="hidden" name="params[accountId]" value="<?=$wexAccount->id?>">
		<p>
			Дата проверки: <?=($wexAccount->date_check) ? date('d.m.Y H:i', $wexAccount->date_check) : ''?>
			<input type="submit" name="updateHistory" value="обновить платежи">
		</p>
	</form>

	<p><a href="<?=url('manager/yandexPay')?>">История по ссылкам</a></p>

	<div>
		<form method="post" action="">
			с <input type="text" name="params[dateStart]" value="<?=$interval['dateStart']?>" />
			до <input type="text" name="params[dateEnd]" value="<?=$interval['dateEnd']?>" />
			<input  type="submit" name="stats" value="Показать"/>
		</form>
	</div>
	<br>

	<div>
		<form method="post" action="" style="display: inline">
			<input type="hidden" name="params[dateStart]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()))?>" />
			<input type="hidden" name="params[dateEnd]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()+24*3600))?>" />
			<input  type="submit" name="stats" value="За сегодня"/>
		</form>

		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

		<form method="post" action="" style="display: inline">
			<input type="hidden" name="params[dateStart]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()-24*3600))?>" />
			<input type="hidden" name="params[dateEnd]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()))?>" />
			<input  type="submit" name="stats" value="За вчера"/>
		</form>
	</div>
	<br>

<?if($models){?>
	<table class="std padding">

		<thead>
		<th>Сумма</th>
		<th>Статус</th>
		</thead>

		<?foreach($models as $model){?>
			<tr>
				<td><b><?=$model->originalAmount?> <?=$model->currency?></b></td>
				<td>
					<?if($model->status == TransactionWex::STATUS_SUCCESS){?>
						<span class="success">оплачен</span>
					<?}elseif($model->status == TransactionWex::STATUS_ERROR){?>
						<span class="error">ошибка</span>
					<?}?>
					<br>
					<?=date('d.m.Y H:i', $model->date_add)?>
				</td>
			</tr>
		<?}?>
	</table>
<?}else{?>
	записей не найдено
<?}?>