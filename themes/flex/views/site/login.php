<!DOCTYPE html>
<!--[if IE 8]>			<html class="ie ie8"> <![endif]-->
<!--[if IE 9]>			<html class="ie ie9"> <![endif]-->
<!--[if gt IE 9]><!-->	<html><!--<![endif]-->

<!-- Specific Page Data -->

<!-- End of Data -->

<head>
	<meta charset="utf-8" />
	<title>Вход</title>
	<!-- Set the viewport width to device width for mobile -->
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body id="pages" class="full-layout no-nav-left no-nav-right  nav-top-fixed background-login     responsive remove-navbar login-layout   clearfix" data-active="pages "  data-smooth-scrolling="1">
<div class="vd_body">
	<!-- Header Start -->

	<!-- Header Ends -->
	<div class="content">
		<div class="container">

			<!-- Middle Content Start -->

			<div class="vd_content-wrapper">
				<div class="vd_container">
					<div class="vd_content clearfix">
						<div class="vd_content-section clearfix">
							<div class="vd_login-page">
								<div class="heading clearfix">

									<h4 class="text-center font-semibold vd_grey">ВОЙТИ В АККАУНТ</h4>
								</div>
								<div class="panel widget">
									<div class="panel-body">
										<div class="login-icon entypo-icon"> <i class="icon-key"></i> </div>

										<form class="form-horizontal" method="post" id="login-form" action="<?=Yii::app()->createUrl('site/login')?>" role="form">
											<div class="alert alert-danger vd_hidden">
												<button type="button" class="close" data-dismiss="alert" aria-hidden="true"><i class="icon-cross"></i></button>
												<span class="vd_alert-icon"><i class="fa fa-exclamation-circle vd_red"></i></span><strong>Oh snap!</strong> Change a few things up and try submitting again. </div>
											<div class="alert alert-success vd_hidden">
												<button type="button" class="close" data-dismiss="alert" aria-hidden="true"><i class="icon-cross"></i></button>
												<span class="vd_alert-icon"><i class="fa fa-check-circle vd_green"></i></span><strong>Well done!</strong>. </div>
											<div class="form-group  mgbt-xs-20">
												<div class="col-md-12">
													<div class="label-wrapper sr-only">
														<label class="control-label" for="login">Логин</label>
													</div>
													<div class="vd_input-wrapper" id="login-input-wrapper"> <span class="menu-icon"> <i class="fa fa-envelope"></i> </span>
														<input type="text" placeholder="Логин" id="login" name="params[login]" value="<?=$params['login']?>" tabindex="1" class="required" required>
													</div>
													<div class="label-wrapper">
														<label class="control-label sr-only" for="password">Пароль</label>
													</div>
													<div class="vd_input-wrapper" id="password-input-wrapper" > <span class="menu-icon"> <i class="fa fa-lock"></i> </span>
														<input type="password" placeholder="Пароль" id="password" name="params[pass]" value="" tabindex="2" class="required" required>
													</div>
												</div>
											</div>
											<div id="vd_login-error" class="alert alert-danger hidden"><i class="fa fa-exclamation-circle fa-fw"></i> Пожалуйста, заполните это поле </div>
											<div class="form-group">
												<div class="col-md-12 text-center mgbt-xs-5">
													<button class="btn vd_bg-green vd_white width-100" name="sign_in" value="Login" type="submit">Войти</button>
												</div>
											</div>
										</form>
									</div>
								</div>
								<!-- Panel Widget -->
							</div>
							<!-- vd_login-page -->

						</div>
						<!-- .vd_content-section -->

					</div>
					<!-- .vd_content -->
				</div>
				<!-- .vd_container -->
			</div>
			<!-- .vd_content-wrapper -->

			<!-- Middle Content End -->

		</div>
		<!-- .container -->
	</div>
	<!-- .content -->


</div>

<!-- .vd_body END  -->
<a id="back-top" href="#" data-action="backtop" class="vd_back-top visible"> <i class="fa  fa-angle-up"> </i> </a>
<!--
<a class="back-top" href="#" id="back-top"> <i class="icon-chevron-up icon-white"> </i> </a> -->

<!-- Specific Page Scripts Put Here -->
<script type="text/javascript">
	$(document).ready(function() {

		"use strict";

		var form_register_2 = $('#login-form');
		var error_register_2 = $('.alert-danger', form_register_2);
		var success_register_2 = $('.alert-success', form_register_2);

		form_register_2.validate({
			errorElement: 'div', //default input error message container
			errorClass: 'vd_red', // default input error message class
			focusInvalid: false, // do not focus the last invalid input
			ignore: "",
			rules: {

				login: {
					required: true,
				},
				password: {
					required: true,
					minlength: 6
				},

			},

			errorPlacement: function(error, element) {
				if (element.parent().hasClass("vd_checkbox") || element.parent().hasClass("vd_radio")){
					element.parent().append(error);
				} else if (element.parent().hasClass("vd_input-wrapper")){
					error.insertAfter(element.parent());
				}else {
					error.insertAfter(element);
				}
			},

			invalidHandler: function (event, validator) { //display error alert on form submit
				success_register_2.hide();
				error_register_2.show();


			},

			highlight: function (element) { // hightlight error inputs

				$(element).addClass('vd_bd-red');
				$(element).parent().siblings('.help-inline').removeClass('help-inline hidden');
				if ($(element).parent().hasClass("vd_checkbox") || $(element).parent().hasClass("vd_radio")) {
					$(element).siblings('.help-inline').removeClass('help-inline hidden');
				}

			},

			unhighlight: function (element) { // revert the change dony by hightlight
				$(element)
					.closest('.control-group').removeClass('error'); // set error class to the control group
			},

			success: function (label, element) {
				label
					.addClass('valid').addClass('help-inline hidden') // mark the current input as valid and display OK icon
					.closest('.control-group').removeClass('error').addClass('success'); // set success class to the control group
				$(element).removeClass('vd_bd-red');


			},

			submitHandler: function (form) {
				$(form).find('#login-submit').prepend('<i class="fa fa-spinner fa-spin mgr-10"></i>')/*.addClass('disabled').attr('disabled')*/;
				success_register_2.show();
				error_register_2.hide();
				setTimeout(function(){window.location.href = "index.php"},2000)	 ;
			}
		});


	});
</script>

<!-- Specific Page Scripts END -->

</body>
</html>

