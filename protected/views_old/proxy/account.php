<?
/**
 * @var AccountProxy[] $models
 */
$this->title = 'Привязка прокси'

?>

<h1><?=$this->title?></h1>

<p><a href="<?=url('proxy/list')?>">Список прокси</a> </p>

<?if($models){?>
 <form method="post">

	 <p>
		 <input type="submit" name="save" value="Сохранить">
	 </p>

	<table class="std padding">
		<tr>
			<td>Client ID</td>
			<td>Group ID</td>
			<td>Stability</td>
			<td><span class="withComment" title="Прокси на аккаунте сменится только во время следущей проверки">Account count</span></td>
			<td>ProxyID</td>
		</tr>

		<?foreach($models as $model){?>
			<tr>
				<td><?=$model->client_id?></td>
				<td><?=$model->group_id?></td>
				<td><?=$model->proxy->ratingStr?></td>
				<td><?=$model->proxy->accountCount?></td>
				<td><input type="text" name="accountProxy[<?=$model->id?>]" value="<?=$model->proxy_id?>" size="3"></td>
			</tr>
		<?}?>

	</table>


 </form>
<?}?>
