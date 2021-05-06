<?
/**
 * @var MerchantWallet[] $wallets
 * @var User $user
 */

$this->title = 'Яндекс кошельки'
?>

<h1><?=$this->title?></h1>


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

