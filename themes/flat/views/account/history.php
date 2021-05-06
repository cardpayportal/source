<?$this->title = 'История платежей: '.$account->login?>

<h2><?=$this->title?></h2>

<p><a href="<?=url(cfg('index_page'))?>">Назад</a></p>

<p>
	<?if($models){?>
		<table class="std padding">
			<tr>
				<td>ID</td>
				<td>Тип</td>
				<td>Сумма</td>
				<td>Кошелек</td>
				<td>Дата</td>
				<td>Коммент</td>
			</tr>
			
			<?foreach($models as $model){?>
			<tr
				<?if($model->status==Transaction::STATUS_ERROR){?> 
					class="error" title="<?=$model->error?>"
				<?}elseif($model->status==Transaction::STATUS_WAIT){?>
					class="wait" title="Не подтвержден"
				<?}else{?>
					class="<?=$trans->status?>"
				<?}?>
			>
					<td><?=$model->id?></td>
					<td><?=$model->typeStr?></td>
					<td><?=$model->amount?></td>
					<td><?=$model->wallet?></td>
					<td><?=$model->dateAddStr?></td>
					<td><?=$model->commentSTr?></td>
				</tr>
			<?}?>
		</table>
	<?}else{?>
		нет платежей
	<?}?>
</p>
