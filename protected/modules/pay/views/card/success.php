<?php
$this->title = 'Платеж выполнен';
?>

<div class="wrap">
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-md-4">
				<div class="block text-center">
					<h1 class="success-text">Спасибо</h1>
					<p class="success-text">Ваш платеж №<?=$orderId?> успешно выполнен</p>
					<div class="circle circle--success">
						<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 26 26" version="1.1" width="50px" height="50px">
							<g id="surface1">
								<path style=" " d="M 22.566406 4.730469 L 20.773438 3.511719 C 20.277344 3.175781 19.597656 3.304688 19.265625 3.796875 L 10.476563 16.757813 L 6.4375 12.71875 C 6.015625 12.296875 5.328125 12.296875 4.90625 12.71875 L 3.371094 14.253906 C 2.949219 14.675781 2.949219 15.363281 3.371094 15.789063 L 9.582031 22 C 9.929688 22.347656 10.476563 22.613281 10.96875 22.613281 C 11.460938 22.613281 11.957031 22.304688 12.277344 21.839844 L 22.855469 6.234375 C 23.191406 5.742188 23.0625 5.066406 22.566406 4.730469 Z "/>
							</g>
						</svg>
					</div>
					<div class="row">
						<!--<?/*<div class="col-md-12">

							<div class="row justify-content-center">
								<div class="col-md-7">
									<p>Вы будете перенаправлены через <span id="num">15</span></p>
									<button type="submit" class="btn btn-success btn-block mt-1" value="Отправить">Вернуться на сайт</button>
								</div>
							</div>
						</div>*/?>-->
						<div class="money-logos">
							<img src="img/SVG_pci_dss.svg" alt="">
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
