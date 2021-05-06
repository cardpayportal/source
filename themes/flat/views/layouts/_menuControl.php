<?$user = User::getUser()?>
<?/*
<span><a href="<?=url('control/manager')?>">Приход</a></span>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<span><a href="<?=url('user/list')?>">Пользователи</a></span>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<span><a href="<?=url('news/list')?>">Новости<?if($newsCount = News::newsCount($user->id)){?> (<?=$newsCount?>)<?}?></a></span>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<span><a href="<?=url('user/profile')?>">Профиль</a></span>
*/?>
<div id="navigation">
	<div class="container">
		<ul class='main-nav'>
			<li>
				<a href="<?=url('control/manager')?>">
					<span>Приход</span>
				</a>
			</li>
			<li><a href="<?=url('news/list')?>"><span>Новости<?if($newsCount = News::newsCount($user->id)){?> (<?=$newsCount?>)<?}?></span></a></li>

		</ul>
		<div class="user">
			<div class="dropdown">
				<a href="#" class='dropdown-toggle' data-toggle="dropdown"><?=mb_ucfirst(Yii::app()->user->name, 'utf-8')?>
					<img src="<?=Yii::app()->theme->baseUrl?>/img/icon_user.png" alt="">
				</a>
				<ul class="dropdown-menu pull-right">
					<li><a href="<?=url('user/profile')?>">Профиль</a></li>
					<li><a href="<?=url('site/exit')?>">Выход</a></li>
				</ul>
			</div>
		</div>
	</div>
</div>