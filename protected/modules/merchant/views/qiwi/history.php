<?
/**
 * @var MerchantWallet[] $wallets
 * @var User $user
 */

$this->title = 'Текущие кошельки'
?>

<h1><?=$this->title?></h1>

<?/*
<div>
	<form method="post" action="<?=url('qiwi/main/history')?>">
		с <input type="text" name="params[dateStart]" value="<?=$interval['dateStart']?>" />
		до <input type="text" name="params[dateEnd]" value="<?=$interval['dateEnd']?>" />
		<input  type="submit" name="stats" value="Показать"/>
	</form>
</div>
<br>

<div>
	<form method="post" action="<?=url('qiwi/main/history')?>" style="display: inline">
		<input type="hidden" name="params[dateStart]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()))?>" />
		<input type="hidden" name="params[dateEnd]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()+24*3600))?>" />
		<input  type="submit" name="stats" value="За сегодня"/>
	</form>

	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

	<form method="post" action="<?=url('qiwi/main/history')?>" style="display: inline">
		<input type="hidden" name="params[dateStart]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()-24*3600))?>" />
		<input type="hidden" name="params[dateEnd]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()))?>" />
		<input  type="submit" name="stats" value="За вчера"/>
	</form>
</div>
<br>
*/?>
<p style="color:red">
	<strong>* Нельзя слать с карт Рокетбанк и Точка Банк!</strong>
</p>
<p style="color:blue">
	<strong>* Разрешено слать только с банковских карт</strong>
</p>
<p style="color:blue">
	<strong>* Минимальный платеж от 20к рублей</strong>
</p>

<p>
	<?/*<b>Всего:</b> <?=formatAmount($statsQiwi['count'], 0)?> платежей на сумму <?=formatAmount($statsQiwi['allAmount'], 0)?>
	(<span class="success">оплачено:</span> <b><?=formatAmount($statsQiwi['countSuccess'], 0).' платежей на сумму '?><?=formatAmount($statsQiwi['amount'], 0)?></b>)
	*/?>
</p>
<?if($wallets){?>
	<?if(count($wallets)>1){?>
		<?foreach($wallets as $userName=>$models){?>
			<h2><?=$userName?></h2>
			<?$this->renderPartial('_accounts', array('models'=>$models, 'user'=>$user))?>
		<?}?>
	<?}else{?>
		<?$this->renderPartial('_accounts', array('models'=>array_shift($wallets), 'user'=>$user))?>
	<?}?>

		<?$this->renderPartial('//manager/_stats', array(
			'stats'=>$stats,
			'statsType'=>$statsType,
		))?>
<?}else{?>
	не получено кошельков
<?}?>

<br>
<?/*if($this->isManager() and $user->id != 711 and $user->id != 445){?>
	<form method="post" action="">
		<input type="hidden" value="<?=$user->merchantUser->id?>" name="id">
		<p>
			<input type="submit" name="assingWallet" value="Взять новый кошелек">
		</p>
	</form>
<?}*/?>