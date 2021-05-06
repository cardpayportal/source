<?
/**
 * @var PanelController $this
 */
$this->renderPartial('//layouts/_header');

$user = User::getUser();
?>

<body id="forms" class="full-layout  nav-right-hide nav-right-start-hide  nav-top-fixed      responsive    clearfix" data-active="forms "  data-smooth-scrolling="1">
<div class="vd_body">
	<!-- Header Start -->
	<header class="header-1" id="header">
		<div class="vd_top-menu-wrapper">
			<div class="container ">
				<div class="vd_top-nav vd_nav-width  ">
					<div class="vd_panel-header">
						<div class="logo">
							<a href="index.html"><img alt="logo" src="<?=Yii::app()->theme->baseUrl?>/img/logo.png"></a>
						</div>
						<!-- logo -->
						<div class="vd_panel-menu  hidden-sm hidden-xs" data-intro="<strong>Minimize Left Navigation</strong><br/>Toggle navigation size to medium or small size. You can set both button or one button only. See full option at documentation." data-step=1>
            		                	<span class="nav-medium-button menu" data-toggle="tooltip" data-placement="bottom" data-original-title="Medium Nav Toggle" data-action="nav-left-medium">
	                    <i class="fa fa-bars"></i>
                    </span>

                	<span class="nav-small-button menu" data-toggle="tooltip" data-placement="bottom" data-original-title="Small Nav Toggle" data-action="nav-left-small">
	                    <i class="fa fa-ellipsis-v"></i>
                    </span>

						</div>
						<div class="vd_panel-menu left-pos visible-sm visible-xs">

                        <span class="menu" data-action="toggle-navbar-left">
                            <i class="fa fa-ellipsis-v"></i>
                        </span>


						</div>
						<div class="vd_panel-menu visible-sm visible-xs">
                	<span class="menu visible-xs" data-action="submenu">
	                    <i class="fa fa-bars"></i>
                    </span>

                        <span class="menu visible-sm visible-xs" data-action="toggle-navbar-right">
                            <i class="fa fa-comments"></i>
                        </span>

						</div>
						<!-- vd_panel-menu -->
					</div>
					<!-- vd_panel-header -->

				</div>
				<div class="vd_container">
					<div class="row">
						<div class="col-sm-5 col-xs-12">

						</div>
						<div class="col-sm-7 col-xs-12">
							<div class="vd_mega-menu-wrapper">
								<div class="vd_mega-menu pull-right">
									<ul class="mega-ul">
										<li id="top-menu-profile" class="profile mega-li">
											<a href="#" class="mega-link"  data-action="click-trigger">
            <span  class="mega-image">
                <img src="<?=Yii::app()->theme->baseUrl?>/img/avatar/avatar.jpg" alt="example image" />
            </span>
            <span class="mega-name">
                <?=Yii::app()->user->name?> <i class="fa fa-caret-down fa-fw"></i>
            </span>
											</a>
											<div class="vd_mega-menu-content  width-xs-2  left-xs left-sm" data-action="click-target">
												<div class="child-menu">
													<div class="content-list content-menu">
														<ul class="list-wrapper pd-lr-10">
															<li> <a href="<?=url('user/profile')?>"> <div class="menu-icon"><i class=" fa fa-cogs"></i></div> <div class="menu-text">Профиль</div> </a> </li>
															<li> <a href="<?=url('site/exit')?>"> <div class="menu-icon"><i class=" fa fa-sign-out"></i></div>  <div class="menu-text">Выход</div> </a> </li>
														</ul>
													</div>
												</div>
											</div>

										</li>
									</ul>
									<!-- Head menu search form ends -->
								</div>
							</div>
						</div>

					</div>
				</div>
			</div>
			<!-- container -->
		</div>
		<!-- vd_primary-menu-wrapper -->

	</header>
	<!-- Header Ends -->
	<div class="content">
		<div class="container">
			<div class="vd_navbar vd_nav-width vd_navbar-tabs-menu vd_navbar-left  ">
				<div class="navbar-menu clearfix">
					<div class="vd_panel-menu hidden-xs">
            		<span data-original-title="Expand All" data-toggle="tooltip" data-placement="bottom" data-action="expand-all" class="menu" data-intro="<strong>Expand Button</strong><br/>To expand all menu on left navigation menu." data-step=4 >
                		<i class="fa fa-sort-amount-asc"></i>
            		</span>
					</div>
					<h3 class="menu-title hide-nav-medium hide-nav-small"></h3>
					<div class="vd_menu">
						<?$this->renderPartial('//layouts/_menu')?>
						<!-- Head menu search form ends -->
						</div>
				</div>
				<div class="navbar-spacing clearfix">
				</div>
				<div class="vd_menu vd_navbar-bottom-widget">
					<ul>
						<li>
							<a href="pages-logout.html">
								<span class="menu-icon"><i class="fa fa-sign-out"></i></span>
								<span class="menu-text">Logout</span>
							</a>

						</li>
					</ul>
				</div>
			</div>
			<div class="vd_navbar vd_nav-width vd_navbar-chat vd_bg-black-80 vd_navbar-right   ">
				<div class="navbar-tabs-menu clearfix">
			<span class="expand-menu" data-action="expand-navbar-tabs-menu">
            	<span class="menu-icon menu-icon-left">
            		<i class="fa fa-ellipsis-h"></i>
                    <span class="badge vd_bg-red">
                        20
                    </span>
                </span>
            	<span class="menu-icon menu-icon-right">
            		<i class="fa fa-ellipsis-h"></i>
                    <span class="badge vd_bg-red">
                        20
                    </span>
                </span>
            </span>
					<div class="menu-container">
						<div class="navbar-search-wrapper">
							<div class="navbar-search vd_bg-black-30">
								<span class="append-icon"><i class="fa fa-search"></i></span>
								<input type="text" placeholder="Search" class="vd_menu-search-text no-bg no-bd vd_white width-70" name="search">
								<div class="pull-right search-config">
									<a  data-toggle="dropdown" href="javascript:void(0);" class="dropdown-toggle" ><span class="prepend-icon vd_grey"><i class="fa fa-cog"></i></span></a>
									<ul role="menu" class="dropdown-menu">
										<li><a href="#">Action</a></li>
										<li><a href="#">Another action</a></li>
										<li><a href="#">Something else here</a></li>
										<li class="divider"></li>
										<li><a href="#">Separated link</a></li>
									</ul>
								</div>
							</div>
						</div>
					</div>

				</div>
				<div class="navbar-menu clearfix">
					<div class="content-list content-image content-chat">
						<ul class="list-wrapper no-bd-btm pd-lr-10">
							<li class="group-heading vd_bg-black-20">FAVORITE</li>
							<li>
								<a href="#">
									<div class="menu-icon"><img src="<?=Yii::app()->theme->baseUrl?>/img/avatar/avatar.jpg" alt="example image"></div>
									<div class="menu-text">Jessylin
										<div class="menu-info">
											<span class="menu-date">Administrator </span>
										</div>
									</div>
									<div class="menu-badge"><span class="badge status vd_bg-green">&nbsp;</span></div>
								</a>
							</li>
							<li>
								<a href="#">
									<div class="menu-icon"><img src="<?=Yii::app()->theme->baseUrl?>/img/avatar/avatar-2.jpg" alt="example image"></div>
									<div class="menu-text">Rodney Mc.Cardo
										<div class="menu-info">
											<span class="menu-date">Designer </span>
										</div>
									</div>
									<div class="menu-badge"><span class="badge status vd_bg-grey">&nbsp;</span></div>
								</a>
							</li>
							<li>
								<a href="#">
									<div class="menu-icon"><img src="<?=Yii::app()->theme->baseUrl?>/img/avatar/avatar-3.jpg" alt="example image"></div>
									<div class="menu-text">Theresia Minoque
										<div class="menu-info">
											<span class="menu-date">Engineering </span>
										</div>
									</div>
									<div class="menu-badge"><span class="badge status vd_bg-green">&nbsp;</span></div>
								</a>
							</li>
							<li class="group-heading vd_bg-black-20">FRIENDS</li>
							<li>
								<a href="#">
									<div class="menu-icon"><img src="<?=Yii::app()->theme->baseUrl?>/img/avatar/avatar-4.jpg" alt="example image"></div>
									<div class="menu-text">Greg Grog
										<div class="menu-info">
											<span class="menu-date">Developer </span>
										</div>
									</div>
									<div class="menu-badge"><span class="badge status vd_bg-grey">&nbsp;</span></div>
								</a>
							</li>
							<li>
								<a href="#">
									<div class="menu-icon"><img src="<?=Yii::app()->theme->baseUrl?>/img/avatar/avatar-5.jpg" alt="example image"></div>
									<div class="menu-text">Stefanie Imburgh
										<div class="menu-info">
											<span class="menu-date">Dancer</span>
										</div>
									</div>
									<div class="menu-badge"><span class="vd_grey font-sm"><i class="fa fa-mobile"></i></span></div>
								</a>
							</li>
							<li>
								<a href="#">
									<div class="menu-icon"><img src="<?=Yii::app()->theme->baseUrl?>/img/avatar/avatar-6.jpg" alt="example image"></div>
									<div class="menu-text">Matt Demon
										<div class="menu-info">
											<span class="menu-date">Musician </span>
										</div>
									</div>
									<div class="menu-badge"><span class="vd_grey font-sm"><i class="fa fa-mobile"></i></span></div>
								</a>
							</li>
							<li>
								<a href="#">
									<div class="menu-icon"><img src="<?=Yii::app()->theme->baseUrl?>/img/avatar/avatar-7.jpg" alt="example image"></div>
									<div class="menu-text">Jeniffer Anastasia
										<div class="menu-info">
											<span class="menu-date">Senior Developer </span>
										</div>
									</div>
									<div class="menu-badge"><span class="badge status vd_bg-green">&nbsp;</span></div>
								</a>
							</li>
							<li>
								<a href="#">
									<div class="menu-icon"><img src="<?=Yii::app()->theme->baseUrl?>/img/avatar/avatar-8.jpg" alt="example image"></div>
									<div class="menu-text">Daniel Dreamon
										<div class="menu-info">
											<span class="menu-date">Sales Executive </span>
										</div>
									</div>
									<div class="menu-badge"><span class="badge status vd_bg-green">&nbsp;</span></div>
								</a>
							</li>

						</ul>
					</div>
				</div>
				<div class="navbar-spacing clearfix">
				</div>
			</div>
			<!-- Middle Content Start -->

			<div class="vd_content-wrapper">
				<div class="vd_container">
					<div class="vd_content clearfix">
						<?=$content?>
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

	<!-- Footer Start -->
	<footer class="footer-1"  id="footer">
		<div class="vd_bottom ">
			<div class="container">
				<div class="row">
					<div class=" col-xs-12">
						<div class="copyright">
							Copyright &copy;2014 Venmond Inc. All Rights Reserved
						</div>
					</div>
				</div><!-- row -->
			</div><!-- container -->
		</div>
	</footer>
	<!-- Footer END -->

</div>

<!-- .vd_body END  -->
<a id="back-top" href="#" data-action="backtop" class="vd_back-top visible"> <i class="fa  fa-angle-up"> </i> </a>

<!--
<a class="back-top" href="#" id="back-top"> <i class="icon-chevron-up icon-white"> </i> </a> -->
<!-- Javascript =============================================== -->
<!-- Placed at the end of the document so the pages load faster -->


<script src="<?=Yii::app()->theme->baseUrl?>/js/caroufredsel.js"></script>
<script src="<?=Yii::app()->theme->baseUrl?>/js/plugins.js"></script>

<script src="<?=Yii::app()->theme->baseUrl?>/plugins/breakpoints/breakpoints.js"></script>
<script src="<?=Yii::app()->theme->baseUrl?>/plugins/dataTables/jquery.dataTables.min.js"></script>
<script src="<?=Yii::app()->theme->baseUrl?>/plugins/prettyPhoto-plugin/js/jquery.prettyPhoto.js"></script>

<script src="<?=Yii::app()->theme->baseUrl?>/plugins/mCustomScrollbar/jquery.mCustomScrollbar.concat.min.js"></script>
<script src="<?=Yii::app()->theme->baseUrl?>/plugins/tagsInput/jquery.tagsinput.min.js"></script>
<script src="<?=Yii::app()->theme->baseUrl?>/plugins/bootstrap-switch/bootstrap-switch.min.js"></script>
<script src="<?=Yii::app()->theme->baseUrl?>/plugins/blockUI/jquery.blockUI.js"></script>
<script src="<?=Yii::app()->theme->baseUrl?>/plugins/pnotify/js/jquery.pnotify.min.js"></script>

<script src="<?=Yii::app()->theme->baseUrl?>/plugins/bootstrap-timepicker/bootstrap-timepicker.min.js"></script>

<script src="<?=Yii::app()->theme->baseUrl?>/js/theme.js"></script>
<script src="<?=Yii::app()->theme->baseUrl?>/custom/custom.js"></script>

<!-- Specific Page Scripts Put Here -->

<!-- Specific Page Scripts END -->


</body>

<?$this->renderPartial('//layouts/_footer');?>