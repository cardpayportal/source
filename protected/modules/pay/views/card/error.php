<?php
$this->title = 'Ошибка платежа №'.$orderId;
?>

<div class="wrap">
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-md-4">
				<div class="block text-center">
					<h1 class="errors--text">Платеж №<?=$orderId?> не прошел</h1>
					<p class="errors--text"><?=$error?></p>
					<div class="circle circle--errors">
						<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="Layer_1" x="0px" y="0px" viewBox="0 0 30 30" style="enable-background:new 0 0 30 30;" xml:space="preserve" width="30px" height="30px">
<path d="M25.707,6.293c-0.195-0.195-1.805-1.805-2-2c-0.391-0.391-1.024-0.391-1.414,0c-0.195,0.195-17.805,17.805-18,18  c-0.391,0.391-0.391,1.024,0,1.414c0.279,0.279,1.721,1.721,2,2c0.391,0.391,1.024,0.391,1.414,0c0.195-0.195,17.805-17.805,18-18  C26.098,7.317,26.098,6.683,25.707,6.293z"/>
							<path d="M23.707,25.707c0.195-0.195,1.805-1.805,2-2c0.391-0.391,0.391-1.024,0-1.414c-0.195-0.195-17.805-17.805-18-18  c-0.391-0.391-1.024-0.391-1.414,0c-0.279,0.279-1.721,1.721-2,2c-0.391,0.391-0.391,1.024,0,1.414c0.195,0.195,17.805,17.805,18,18  C22.683,26.098,23.317,26.098,23.707,25.707z"/>
</svg>

					</div>
					<div class="row">
						<div class="col-md-12">
							<div class="row justify-content-center">
								<div class="col-md-7">
									<?/*<p>Вы будете перенаправлены через <span id="num">15</span></p>
									<button type="submit" class="btn btn-success btn--errors btn-block mt-1" value="Отправить">Вернуться на сайт</button>*/?>
								</div>
							</div>
							<div class="money-logos">
								<img src="img/SVG_pci_dss.svg" alt="">
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
