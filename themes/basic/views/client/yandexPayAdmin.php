<?
/**
 * @var ClientController $this
 * @var YandexPay[] $models
 * @var WexAccount $wexAccount
 * @var array $stats
 * @var array $history
 */

$this->title = 'Yandex Money ';
?>

<h1><?=$this->title?></h1>

<br><hr><br>

<div class="layer">
	<?$this->renderPartial('yandexHistory', ['user'=>$user,
		'history' => $history,])?>
</div>

<br><hr><br>

<div>
	<form method="post" action="<?=url('client/yandexPayGlobalFin', ['userId'=>$wexAccount->user_id, 'wexAccountId'=>$wexAccount->id])?>">
		с <input type="text" name="params[dateStart]" value="<?=$interval['dateStart']?>" />
		до <input type="text" name="params[dateEnd]" value="<?=$interval['dateEnd']?>" />
		<input  type="submit" name="stats" value="Показать"/>
	</form>
</div>
<br>

<div>
	<form method="post" action="<?=url('client/yandexPayGlobalFin', ['userId'=>$wexAccount->user_id, 'wexAccountId'=>$wexAccount->id])?>" style="display: inline">
		<input type="hidden" name="params[dateStart]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()))?>" />
		<input type="hidden" name="params[dateEnd]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()+24*3600))?>" />
		<input  type="submit" name="stats" value="За сегодня"/>
	</form>

	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

	<form method="post" action="<?=url('client/yandexPayGlobalFin', ['userId'=>$wexAccount->user_id, 'wexAccountId'=>$wexAccount->id])?>" style="display: inline">
		<input type="hidden" name="params[dateStart]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()-24*3600))?>" />
		<input type="hidden" name="params[dateEnd]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()))?>" />
		<input  type="submit" name="stats" value="За вчера"/>
	</form>
</div>
<br>


<form method="post" action="<?=url('client/yandexPayGlobalFin', ['userId'=>$wexAccount->user_id, 'wexAccountId'=>$wexAccount->id])?>">
	<input type="hidden" name="params[accountId]" value="<?=$wexAccount->id?>">
	<p>
		Дата проверки: <?=($wexAccount->date_check) ? date('d.m.Y H:i') : ''?>
		<input type="submit" name="updateHistory" value="обновить платежи">
	</p>
</form>


<?if($models){?>

	<table class="std padding">

		<thead>
		<th>ID</th>
		<th>Сумма</th>
		<th>Статус</th>
		<th>Добавлен</th>
		<?if(!$this->isManager()){?>
			<th>Юзер</th>
		<?}?>
		<th>Ссылка</th>
		<th>WEX id</th>
		<th>Дата</th>
		<th>Действие</th>
		</thead>

		<?foreach($models as $model){?>
			<tr>
				<td><?=$model->id?></td>
				<td><b><?=$model->amountStr?></b></td>
				<td>
					<?if($model->status == YandexPay::STATUS_SUCCESS){?>
						<span class="success"><?=$model->statusStr?></span>
						<br><?=$model->datePayStr?>
					<?}elseif($model->status == YandexPay::STATUS_ERROR){?>
						<span class="error"><?=$model->statusStr?></span>
						<br>(<?=$model->error?>)
					<?}elseif($model->status == YandexPay::STATUS_WAIT){?>
						<span class="wait"><?=$model->statusStr?></span>
					<?}?>
				</td>
				<td><?=$model->dateAddStr?></td>
				<?if(!$this->isManager()){?>
					<th><?=$model->user->name?></th>
				<?}?>
				<td>
					<span class="shortContent"><?=$model->urlShort?></span>
					<input style="display: none" type="text" size="40" value="<?=$model->url?>" class="click2select fullContent">
				</td>
				<form method="post" action="" name="requestControl<?=$model->id?>">
				<td>
					<input type="hidden" name="params[id]" value="<?=$model->id?>">
					<input type="text" size="10" name="params[wexId]" value="<?=$model->wex_id?>" />
				</td>
				<td>
					<input type="text" size="15" name="params[datePay]" value="<?=$model->date_pay ? date('d.m.Y H:i',$model->date_pay) : date('d.m.Y H:i', Tools::startOfDay(time()))?>" />
				</td>
				<td>
					<?if($model->status == YandexPay::STATUS_SUCCESS){?>
							<input type="submit" name="cancel" value="отмена" class="green" >

					<?}else{?>
							<input type="submit" name="confirm" value="подтв.">
					<?}?>
				</td>
				</form>
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
