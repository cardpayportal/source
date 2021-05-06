<?php
$this->title = "Payment"
?>

<div class="wrap">
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-md-7">
				<form method="post" action="">
					<br>
					<p><b style="color:green">Введите реквизиты</b> платежной карты </p>
					<div class="block">
						<p><b>Счет №</b> <span style="color:green"><?=$orderId?></span></p>
						<p ><b>Сумма</b>:  <span style="color:green"><?=$amount?> Rub</span></p>
					</div>
					<div id="cards" class="panel panel-default">
						<div class="panel-heading">
							<div id="front">
								<a href="#" id="bank-link"></a>
								<img src="" id="brand-logo" alt="brand-logo">
								<div id="front-fields">
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<input type="hidden" name="params[id]" value="<?=$id?>"/>
												<input type="hidden" name="params[amount]" value="<?=$amount?>"/>
												<label class="label">Номер карты:</label>
												<input class="field" id="number" name="params[card_no]" type="text" placeholder="0000 0000 0000 0000" maxlength="16" required="" autocomplete="off">
												<p class="help-block help-block-error"></p>
											</div>
										</div>
									</div>
									<label class="label">Срок действия:</label>
									<div class="row">

										<div class="col-sm-3">
											<div class="form-group">
												<select id="yy" class="field expired" name="params[card_month]">
													<option value="">MM</option>
													<option value="01">01</option>
													<option value="02">02</option>
													<option value="03">03</option>
													<option value="04">04</option>
													<option value="05">05</option>
													<option value="06">06</option>
													<option value="07">07</option>
													<option value="08">08</option>
													<option value="09">09</option>
													<option value="10">10</option>
													<option value="11">11</option>
													<option value="12">12</option>
												</select>
												<p class="help-block help-block-error"></p>
											</div>
										</div>
										<div class="col-sm-3">
											<div class="form-group">
												<select class="field expired" id="yy" name="params[card_year]" style="max-width:none;">
													<option value="">YY</option>
													<option value="18"> 2018</option>
													<option value="19"> 2019</option>
													<option value="20"> 2020</option>
													<option value="21"> 2021</option>
													<option value="22"> 2022</option>
													<option value="23"> 2023</option>
													<option value="24"> 2024</option>
													<option value="25"> 2025</option>
													<option value="26"> 2026</option>
													<option value="27"> 2027</option>
													<option value="28"> 2028</option>
													<option value="29"> 2029</option>
												</select>
												<p class="help-block help-block-error"></p>
											</div>
										</div>
									</div>
								</div>
							</div>
							<div id="back">
								<div class="line"></div>
								<input class="field" id="code" name="params[cvv]" type="password" placeholder="***" maxlength="3" required="">
								<label id="code-label" class="label">Код с обратной стороны</label>
							</div>
						</div>

					</div>
					<p style="font-size: 14px; margin:1rem 0">Нажимая на кнопку, вы <span style="color:green">соглашаетесь с использованием сервиса</span></p>
					<div class="row justify-content-center">
						<div class="col-md-3">
							<input type="submit" class="btn btn-success btn-block" name="send" value="Оплатить">
						</div>
					</div>
					<div class="money-logos">
						<img src="style/card/img/SVG_visa_logo.svg" alt="">
						<img src="style/card/img/SVG_mastercard.svg" alt="">
						<img src="style/card/img/SVG_Verified_by_visa_logo.svg" alt="">
						<img src="style/card/img/SVG_mastercard_secure.svg" alt="">
						<img src="style/card/img/SVG_pci_dss.svg" alt="">
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
