<?
/**
 * @var UserController $this
 * @var array $params
 * @var User $user
 */

$this->title = 'Профиль';

?>

<!-- Middle Content Start -->
		<div class="vd_content clearfix">
			<div class="vd_title-section clearfix">
				<div class="vd_panel-header">
					<h1><?=$this->title?> </h1>
				</div>
			</div>
			<div class="vd_content-section clearfix">
				<div class="panel widget light-widget">
					<div class="panel-heading no-title"> </div>
					<div class="panel-body">
						<form class="form-horizontal" method="post" role="form" id="jabber-form">
							<div class="alert alert-danger vd_hidden">
								<button type="button" class="close" data-dismiss="alert" aria-hidden="true"><i class="icon-cross"></i></button>
								<span class="vd_alert-icon"><i class="fa fa-exclamation-circle vd_red"></i></span><strong>Что пошло не так!</strong> Попробуйте изменить параметры </div>
							<div class="alert alert-success vd_hidden">
								<button type="button" class="close" data-dismiss="alert" aria-hidden="true"><i class="icon-cross"></i></button>
								<span class="vd_alert-icon"><i class="fa fa-check-circle vd_green"></i></span><strong>Well done!</strong>. </div>
							<div class="form-group">
								<div class="col-md-12">
									<label class="control-label  col-sm-3">Jabber для уведомлений</label>
									<div id="first-name-input-wrapper"  class="controls col-sm-6">
										<input type="text" placeholder="example@xmpp.jp" class="width-60" name="params[jabber]" id="jabber" value="<?=$params['jabber']?>">
										<div class="vd_checkbox  checkbox-success">
											<input type="checkbox" id="params[send_notifications]" value="1" name="params[send_notifications]"
												<?if($params['send_notifications']){?>
													checked="checked"
												<?}?>>
											<label for="params[send_notifications]"> Включить уведомления</label>
										</div>
									</div>
								</div>
							</div>
							<div class="form-group">
								<div class="col-md-12">
								<label class="control-label col-sm-3">Тема оформления сайта</label>
									<div class="col-sm-7 controls">
										<select class="width-40" name="params[theme]" id="textfield2">
											<?foreach(cfg('themeArr') as $key=>$name){?>
												<option value="<?=$key?>"
													<?= ($key == $params['theme']) ? 'selected="selected"' : ''?> >
													<?=$name?>
												</option>
											<?}?>
										</select>
									</div>
								</div>
							</div>
							<div class="form-group">
								<div class="col-md-12">
									<div class="col-sm-3"></div>
									<div class="col-md-7 mgbt-xs-10 mgtp-20">
										<div class="mgtp-10">
											<button class="btn vd_bg-green vd_white" type="submit" id="save" name="save" value="save">Сохранить</button>
										</div>
									</div>
								</div>
								<div class="col-md-12 mgbt-xs-5"> </div>
							</div>
						</form>

						<div class="form-group">
							<div class="col-md-12">
								<label class="control-label col-sm-3"></label>
								<div class="col-sm-7 controls"><h2>API</h2></div>
							</div>
						</div>

						<form class="form-horizontal" method="post" role="form" id="api-form">
							<div class="alert alert-danger vd_hidden">
								<button type="button" class="close" data-dismiss="alert" aria-hidden="true"><i class="icon-cross"></i></button>
								<span class="vd_alert-icon"><i class="fa fa-exclamation-circle vd_red"></i></span><strong>Что пошло не так!</strong> Попробуйте изменить параметры </div>
							<div class="alert alert-success vd_hidden">
								<button type="button" class="close" data-dismiss="alert" aria-hidden="true"><i class="icon-cross"></i></button>
								<span class="vd_alert-icon"><i class="fa fa-check-circle vd_green"></i></span><strong>Well done!</strong>. </div>


							<div class="form-group">
								<div class="col-md-12">
									<label class="control-label  col-sm-3">KEY</label>
									<div id="first-name-input-wrapper"  class="controls col-sm-6">
										<input type="text" placeholder="" class="width-60" value="<?=$params['apiKey']?>" readonly="readonly">
									</div>
								</div>
							</div>
							<div class="form-group">
								<div class="col-md-12">
									<label class="control-label  col-sm-3">SECRET</label>
									<div id="first-name-input-wrapper"  class="controls col-sm-6">
										<input type="text" placeholder="" class="width-60" value="<?=$params['apiSecret']?>" readonly="readonly">
									</div>
								</div>
							</div>


							<div class="form-group">
								<div class="col-md-12">
									<div class="col-sm-3"></div>
									<div class="col-md-7 mgbt-xs-10 mgtp-20">
										<div class="mgtp-10">
											<button class="btn vd_bg-green vd_white" type="submit" id="changeApi" value="changeApi" name="changeApi"><?=($params['apiKey']) ? 'Сменить ключи' : 'Получить ключи'?></button>
										</div>
									</div>
								</div>
								<div class="col-md-12 mgbt-xs-5"> </div>
							</div>
						</form>


						<div class="form-group">
							<div class="col-md-12">
								<label class="control-label col-sm-3"></label>
								<div class="col-sm-7 controls"><h2>Настройка уведомлений</h2></div>
							</div>
						</div>

						<form class="form-horizontal" method="post" role="form" id="api-form">
							<div class="alert alert-danger vd_hidden">
								<button type="button" class="close" data-dismiss="alert" aria-hidden="true"><i class="icon-cross"></i></button>
								<span class="vd_alert-icon"><i class="fa fa-exclamation-circle vd_red"></i></span><strong>Что пошло не так!</strong> Попробуйте изменить параметры </div>
							<div class="alert alert-success vd_hidden">
								<button type="button" class="close" data-dismiss="alert" aria-hidden="true"><i class="icon-cross"></i></button>
								<span class="vd_alert-icon"><i class="fa fa-check-circle vd_green"></i></span><strong>Well done!</strong>. </div>

							<div class="form-group">
								<div class="col-md-12">
									<label class="control-label  col-sm-3">Текущий Url для получения уведомлений об оплате </b><?=$user->url_result?></label>
									<div id="first-name-input-wrapper"  class="controls col-sm-6">
										<input type="text" placeholder="текущий URL" class="width-100" name="" value="<?=$params['urlResult']?>" disabled="disabled">
									</div>
								</div>
							</div>
							<div class="form-group">
								<div class="col-md-12">
									<label class="control-label  col-sm-3">Задать Url для получения уведомлений об оплате </b><?=$user->url_result?></label>
									<div id="first-name-input-wrapper"  class="controls col-sm-6">
										<input type="text" placeholder="новый URL" class="width-100" name="params[urlResult]" value="<?=$params['urlResult']?>">
									</div>
								</div>
							</div>

							<div class="form-group">
								<div class="col-md-12">
									<div class="col-sm-3"></div>
									<div class="col-md-7 mgbt-xs-10 mgtp-20">
										<button class="btn vd_btn vd_bg-green vd_white" type="submit" value="saveUrl" name="saveUrl"><i class="icon-ok"></i> Сохранить</button>
										<button class="btn vd_btn" type="submit" value="clearUrl" name="clearUrl">Очистить</button>
									</div>
								</div>
								<div class="col-md-12 mgbt-xs-5"> </div>
							</div>
						</form>
					</div>
				</div>

		<!-- .vd_content -->
		</div>
	</div>
<!-- Middle Content End -->