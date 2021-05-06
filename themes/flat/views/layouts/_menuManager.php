<?$user = User::getUser()?>
<?$client = $user->client?>

<div id="navigation">
	<div class="container">
		<ul class='main-nav'>
			<?$this->renderPartial('//layouts/_incomeMenu')?>
			<?$cfg = cfg('storeApi');
			if($user->client_id != $cfg['clientId']){?>
				<?if($client->checkRule('news')){?>
					<li>
						<a href="<?=url('news/list')?>">
							<span>Новости<?if($newsCount = News::newsCount($user->id)){?> (<?=$newsCount?>)<?}?></span>
						</a>
					</li>
				<?}?>
			<?}?>
		</ul>
		<div class="user">
			<div class="dropdown">
				<a href="#" class='dropdown-toggle' data-toggle="dropdown"><?=mb_ucfirst(Yii::app()->user->name, 'utf-8')?>
					<img src="<?=Yii::app()->theme->baseUrl?>/img/icon_user.png" alt="">
				</a>
				<ul class="dropdown-menu pull-right">
					<?if($client->checkRule('profile')){?>
						<li><a href="<?=url('user/profile')?>">Профиль</a></li>
					<?}?>
					<li><a href="<?=url('site/exit')?>">Выход</a></li>
				</ul>
			</div>
		</div>
	</div>
</div>