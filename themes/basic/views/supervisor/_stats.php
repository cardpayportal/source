<h2>
	<?if($user){?>
		Статистика по:  <font style="font-style: italic;" ><?=$user->name?></font>
	<?}else{?>
		<?=$this->title?>
	<?}?>
</h2>

<?if($users){?>
	<div id="users">
		
		<div>
			<?if($user->id){?>
				<a href="<?=url('supervisor/stats')?>">Все</a>
			<?}else{?>
				<strong>Все</strong>
			<?}?>
		</div>
		
		<?foreach($users as $model){?>
			<div>
				<?if($user->id==$model->id){?>
					<strong><?=$model->name?></strong>
				<?}else{?>
					<a href="<?=url('supervisor/stats', array('user_id'=>$model->id))?>"><?=$model->name?></a>
				<?}?>
			</div>
		<?}?>
	</div>
<?}?>

<p>
	<form method="post">
		с <input type="text" name="params[date_from]" value="<?=$params['date_from']?>" /> 
		до <input type="text" name="params[date_to]" value="<?=$params['date_to']?>" /> 
		<input  type="submit" name="stats" value="Показать"/>
	</form>
</p>

<?if($stats){?>
	
	<p>
		Всего: <strong><?=$stats['amount']?></strong> руб.
	</p>
	<?foreach($stats['days'] as $date=>$arr){?>
		<strong><?=$date?></strong><br />
		<table class="std padding" style="width: 700px;">
			<tr>
				<td>ID</td>
				<?if($stats_type=='finansist'){?>
					<td>Куда</td>
				<?}else{?>
					<td>Менеджер</td>
				<?}?>
				<td>Сумма</td>
				<td>Дата перевода</td>
			</tr>
			<?foreach($arr['items'] as $model){?>
				<tr>
					<td><?=$model->id?></td>
					<td>
						<?if($stats_type=='finansist'){?>
							<?=$model->wallet?>
						<?}else{?>
							<?=$model->userStr?>
						<?}?>
					</td>
					<td><?=$model->amountStr?></td>
					<td><?=$model->dateAddStr?></td>
				</tr>
			<?}?>
			<tr>
				<td colspan="3">всего: <strong><?=$arr['amount']?></strong> руб.</td>
			</tr>
		</table>
	<?}?>
	
<?}?>