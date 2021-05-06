<?php
$this->title = 'Ввод смс';
?>

<div class="wrap">
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-md-4">
				<div class="block">
					<p>На ваш номер был выслан одноразовый код. Введите его в поле ниже.</p>
					<div class="row">
						<div class="col-md-12">
							<form method="post" action="">
								<div class="form-group">
									<input type="text" name="code" class="field" placeholder="***" required="">
									<p class="help-block help-block-error"></p>
								</div>
								<div class="row justify-content-center">
									<div class="col-md-5">
										<input type="submit" name="send" class="btn btn-success btn-block mt-1" value="Отправить">
									</div>
								</div>
							</form>
						</div>
					</div>
				</div>
				<div class="money-logos">
					<img src="style/card/img/SVG_visa_logo.svg" alt="">
					<img src="style/card/img/SVG_mastercard.svg" alt="">
					<img src="style/card/img/SVG_Verified_by_visa_logo.svg" alt="">
					<img src="style/card/img/SVG_mastercard_secure.svg" alt="">
					<img src="style/card/img/SVG_pci_dss.svg" alt="">
				</div>
			</div>
		</div>
	</div>
</div>
