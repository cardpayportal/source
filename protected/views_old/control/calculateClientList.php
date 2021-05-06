<?
/**
 * @var Client $client
 * @var ClientCalc[] $models
 *
 */

$this->title = "Список расчетов";

if($client)
	$this->title .= " ({$client->name})";
?>


<h1><?=$this->title?></h1>

<p>
	<?if($client){?>
		<a href="<?=url('control/CalculateClientList')?>">все клиенты</a>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<a href="<?=url('control/CalculateClient', array('clientId'=>$client->id))?>">рассчитать <?=$client->name?></a>
	<?}else{?>
		<?foreach(Client::getActiveClients() as $clientModel){?>
			<a href="<?=url('control/CalculateClientList', array('clientId'=>$clientModel->id))?>"><?=$clientModel->name?></a>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<?}?>
	<?}?>
</p>


<?if($models){?>
	<?$this->renderPartial('_calcList', array('models'=>$models))?>
<?}else{?>
	расчетов не найдено
<?}?>
