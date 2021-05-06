<?
/**
 * @var TestPublicController Controller $this
 * @var array $params
 * @var array $redirParams
 */
$this->title = 'Тест оплаты картой';
?>


<div>
	<h1><?=$this->title?></h1>
	<?if($redirParams){?>

		<?if($redirParams['method']=='post'){?>
			<form id="redirForm" method="post" action="<?=$redirParams['url']?>">
				<?foreach($redirParams['postArr'] as $key=>$val){?>
					<input type="hidden" name="<?=$key?>" value="<?=$val?>">
				<?}?>

				<p>сейчас вы будете перенаправлены на оплату..., если нет то
					<input type="submit" value="перейти">
				</p>

			</form>


			<script>
				$(document).ready(function(){
					$('#paramsForm').hide();
					setTimeout(function(){
						$('#redirForm').submit().hide();
						$('#paramsForm').show();
					}, 3000);
				});
			</script>
		<?}else{?>
			<input type="text" size="100" value="<?=$redirParams['url']?>">
		<?}?>
	<?}?>

	<form method="post" id="paramsForm">

		<p>
			<strong>Приемная Карта (не обязательно)</strong><br>
			<input type="text" name="params[phone]" value="<?=$params['phone']?>"
				   placeholder="5469520022829523"/>
		</p>

		<p>
			<strong>сумма (руб)</strong><br>
			<input type="text" name="params[amount]" value="<?=$params['amount']?>" placeholder="30" size="5"/>
		</p>

		<div>
			<strong>Номер карты отправителя</strong><br>
			<input type="text" name="params[cardNumber]" value="<?=$params['cardNumber']?>"
				   placeholder="4890494701893547"/>

			<div>
				<strong>Месяц</strong>
				<input type="text" name="params[cardM]" value="<?=$params['cardM']?>"
					   placeholder="06" size="2">
				&nbsp;&nbsp;&nbsp;
				<strong>Год</strong>
				<input type="text" name="params[cardY]" value="<?=$params['cardY']?>"
					   placeholder="21" size="2">
				&nbsp;&nbsp;&nbsp;
				<strong>CVV</strong>
				<input type="text" name="params[cardCvv]" value="<?=$params['cardCvv']?>"
					   placeholder="357" size="3">
			</div>
		</div>

		<p>
			<strong>Прокси</strong><br>
			<input type="text" name="params[proxy]" value="<?=$params['proxy']?>"
				   placeholder="" size="100"/>
		</p>

		<p>
			<strong>Браузер</strong><br>
			<input type="text" name="params[browser]" value="<?=$params['browser']?>"
				   placeholder="" size="100"/>
		</p>

		<p>
			<strong>Success Url</strong><br>
			<input type="text" name="params[successUrl]" value="<?=$params['successUrl']?>"
				   placeholder="https://mysite.me/?success" size="40"/>
		</p>

		<p>
			<strong>Fail Url</strong><br>
			<input type="text" name="params[failUrl]" value="<?=$params['failUrl']?>"
				   placeholder="https://mysite.me/?fail" size="40"/>
		</p>

		<p>
			<input type="submit" name="submit" value="Оплатить">
		</p>

	</form>

</div>