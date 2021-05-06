<?
/**
 * @var ManagerController $this
 * @var array $params
 * @var string $payUrl
 * @var NewYandexPay[] $models
 * @var array $statsYandex
 * @var array $statsWex
 * @var int $userId
 */

$this->title = 'Карты';
?>

<h1><?=$this->title?></h1>

<?if($payUrl){?>

	<a href="<?=url('manager/newYandexPay')?>">назад</a>


	<br>скопировать ссылку на оплату:
	<input type="text" size="90" value="<?=$payUrl?>" id="payUrl" class="click2select" title="">


	<button onclick="myFunction()" onmouseout="outFunc()">
		<span id="tooltip">Копировать</span>
	</button>

<?}else{?>
	<form method="post" action="<?=url('manager/newYandexPay')?>">

		<p>
			<b>Сумма:</b><br>
			<input type="text" name="params[amount]" value="<?=$params['amount']?>"> руб
		</p>

		<p>
			<input type="submit" name="pay" value="Перейти к оплате"/>
		</p>

	</form>
<?}?>


<br><hr>


<form method="post">
	<strong>поиск по комменту:</strong>
	<p>
		<input type="text" name="searchStr" placeholder="введите комментарий или его часть"/>&nbsp
		<input type="submit" name="search" value="Поиск">
	</p>
</form>
<br><hr>

<br>

<div>
	<form method="post" action="<?=url('manager/newYandexPay')?>">
		с <input type="text" name="params[dateStart]" value="<?=$interval['dateStart']?>" />
		до <input type="text" name="params[dateEnd]" value="<?=$interval['dateEnd']?>" />
		<input  type="submit" name="stats" value="Показать"/>
	</form>
</div>
<br>

<div>
	<form method="post" action="<?=url('manager/newYandexPay')?>" style="display: inline">
		<input type="hidden" name="params[dateStart]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()))?>" />
		<input type="hidden" name="params[dateEnd]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()+24*3600))?>" />
		<input  type="submit" name="stats" value="За сегодня"/>
	</form>

	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

	<form method="post" action="<?=url('manager/newYandexPay')?>" style="display: inline">
		<input type="hidden" name="params[dateStart]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()-24*3600))?>" />
		<input type="hidden" name="params[dateEnd]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()))?>" />
		<input  type="submit" name="stats" value="За вчера"/>
	</form>
</div>
<br>

<p>
	<b>Всего:</b> <?=formatAmount($statsYandex['count'], 0)?> платежей на сумму <?=formatAmount($statsYandex['allAmount'], 0)?>
	(<span class="success">оплачено:</span> <b><?=formatAmount($statsYandex['countSuccess'], 0).' платежей на сумму '?><?=formatAmount($statsYandex['amount'], 0)?></b>)
</p>



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
		<?if($userId == 850){?>
        <th>Прогресс<th>
        <?}?>
		<th>Добавлен</th>
		<?if(!$this->isManager()){?>
			<th>Юзер</th>
		<?}?>
		<th>Коммент</th>
		<th>Ссылка</th>
		<th>Действие</th>
		</thead>

		<?foreach($models as $model){?>
			<tr>
				<td><?=$model->id?></td>
				<td><b><?=$model->amountStr?></b></td>
				<td><b><?=$model->order_id?></b></td>
				<td>
					<?if($model->status == NewYandexPay::STATUS_SUCCESS){?>
						<span class="success"><?=$model->statusStr?></span>
						<br><?=$model->datePayStr?>
						<br><nobr><?=$model->payment_type?></nobr>
					<?}elseif($model->status == NewYandexPay::STATUS_ERROR){?>
						<span class="error"><?=$model->statusStr?></span>
						<br>(<?=$model->error?>)
					<?}elseif($model->status == NewYandexPay::STATUS_WORKING){?>
						<span class="accountTransit"><?=$model->statusStr?></span>
					<?}elseif($model->status == NewYandexPay::STATUS_WAIT){?>
						<span class="wait"><?=$model->statusStr?></span>
					<?}?>
				</td>
				<?if($model->user_id == 850){?>
                <td>
                    <?=$model->progress?>
                </td>
				<?}?>
				<td>
					<?=$model->dateAddStr?>

					<?if($model->created_by_api){?>
						<br>
						(<span class="success" title="получен через API">API</span>)
					<?}?>
				</td>
				<?if(!$this->isManager()){?>
					<th><?=$model->user->name?></th>
				<?}?>
				<td>
					<?=$model->comment?><br>
					<?=$model->unique_id?>
				</td>
				<td>
					<span class="shortContent"><?=$model->urlShort?></span>
					<input style="display: none" type="text" size="40" value="<?=$model->url?>" class="click2select fullContent">
				</td>
				<td>
					<?if($model->mark == NewYandexPay::MARK_CHECKED){?>
						<form method="post" action="">
							<input type="hidden" name="params[id]" value="<?=$model->id?>">
							<input type="submit" name="cancel" value="выдано" class="green" >
						</form>
					<?}else{?>
						<form method="post" action="">
							<input type="hidden" name="params[id]" value="<?=$model->id?>">
							<input type="submit" name="check" value="отдать">
						</form>
					<?}?>
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

	function myFunction() {
		var copyText = document.getElementById("payUrl");
		copyText.select();
		document.execCommand("copy");
		var tooltip = document.getElementById("tooltip");
		tooltip.innerHTML = "Скопировано";
	}
</script>
