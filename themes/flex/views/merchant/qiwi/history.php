<?
/**
 * @var MerchantWallet[] $wallets
 * @var User $user
 */

$this->title = 'Текущие кошельки'
?>

<div class="row">
	<div class="col-md-12">
		<div class="panel widget">
			<div class="panel-heading vd_bg-grey">
				<h3 class="panel-title"> <span class="menu-icon"> <i class="fa fa-dot-circle-o"></i> </span> <?=$this->title?> </h3>
			</div>
		</div>
	</div>
</div>

<div class="col-md-12">
	<label style="color:red">
		<strong>* Нельзя слать с карт Рокетбанк и Точка Банк!</strong>
	</label>
</div>
<div class="col-md-12">
	<label style="color:blue">
		<strong>* Разрешено слать только с банковских карт</strong>
	</label>
</div>
<div class="col-md-12">
	<label style="color:blue">
		<strong>* Минимальный платеж от 20к рублей</strong>
	</label>
</div>

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

		<?$this->renderPartial('_stats', array(
			'stats'=>$stats,
			'statsType'=>$statsType,
		))?>
<?}else{?>
	<div class="col-md-12">
		<label>
			<strong>не получено кошельков</strong>
		</label>
	</div>

<?}?>
