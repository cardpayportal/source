<?php
$this->title = "Оплата №".$orderId;
?>

<div class="wrap">
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-md-7">
				<div class="block">
					<h1>Оплата №</h1>
					<p><?=$orderId?></p>
					<div class="block-blue">
						<p>Пожалуйста подождите проверяем данные по карте</p>
						<div id="fountainG">
							<div id="fountainG_1" class="fountainG"></div>
							<div id="fountainG_2" class="fountainG"></div>
							<div id="fountainG_3" class="fountainG"></div>
							<div id="fountainG_4" class="fountainG"></div>
							<div id="fountainG_5" class="fountainG"></div>
							<div id="fountainG_6" class="fountainG"></div>
							<div id="fountainG_7" class="fountainG"></div>
							<div id="fountainG_8" class="fountainG"></div>
						</div>
					</div>
				</div>
				<div class="money-logos">
					<img src="style/card/img/SVG_pci_dss.svg" alt="">
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	function redir(data, d){if(data)window.location.replace(data)}
	setInterval(function(){
	$.ajax({
				url: "index.php?r=pay/card/wait&id=<?=$id?>",
				type: "POST",
				data: ({startUpdate: "refresh"}),
				dataType: "html",
				success: redir
			});
	},10000)
</script>