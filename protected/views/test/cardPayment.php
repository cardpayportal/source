<?
/**
 * @var TestPublicController Controller $this
 * @var array $params
 * @var string $payUrl
 */
?>


<div>
	<h1>Тест оплаты картой</h1>

	<?if($payUrl){?>
		<input type="text" class="click2select" value="<?=$payUrl?>"/>
	<?}?>

	<form method="post">

		<p>
			<strong>номер телефона (существующий)</strong><br>
			<input type="text" name="params[phoneNumber]" value="<?=$params['phoneNumber']?>" placeholder="9013508737"/>
		</p>

		<p>
			<strong>сумма (руб)</strong><br>
			<input type="text" name="params[amount]" value="<?=$params['amount']?>" placeholder="30"/>
		</p>

		<p>
			<strong>Success Url</strong><br>
			<input type="text" name="params[successUrl]" value="<?=$params['successUrl']?>"
				   placeholder="https://mysite.me/?success"/>
		</p>

		<p>
			<strong>Fail Url</strong><br>
			<input type="text" name="params[failUrl]" value="<?=$params['failUrl']?>"
				   placeholder="https://mysite.me/?fail"/>
		</p>

		<div>
			<strong>Card Number</strong><br>
			<input type="text" name="params[cardNumber]" value="<?=$params['cardNumber']?>"
				   placeholder="4890494701893547"/>

			<div>
				<strong>Expire</strong><br>
				<input type="text" name="params[cardExpire]" value="<?=$params['cardExpire']?>"
					   placeholder="202106" size="6">

				<strong>CSV</strong><br>
				<input type="text" name="params[cardCsv]" value="<?=$params['cardCsv']?>"
						 placeholder="357" size="3">
			</div>
		</div>

		<p>
			<input type="submit" name="submit" value="Оплатить">
		</p>

	</form>
</div>