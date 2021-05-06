<?php
/**
 * @var MerchantWallet[] $accounts
 * @var string $userName
 */
$this->title = 'Список кошей пользователя '.$userName;
?>

<h1><?=$this->title?></h1>

<?if($accounts){?>
<table class="std padding" style="width: 100%">
	<tr>
		<td>кошелек</td>
		<td>карта</td>
		<td>принято</td>
		<td>дата добавления</td>
		<td>дата обновления</td>
		<td>сообщение</td>
		<td>баланс</td>
		<td>суточный<br>лимит</td>
		<td>общий<br>лимит</td>
		<td>действие</td>
	</tr>
	<?foreach($accounts as $wallet){?>
		<tr>

			<?
			$class = '';
			$title = '';

			if($wallet->error == Account::ERROR_BAN)
				$class = 'error ';

			if($wallet->hidden and $wallet->user_id == $user->id)
			{
				$class .= 'hidden';
				$hiddenCount++;
			}
			?>

		<tr
			class="<?=$class?>"
			title="<?=$title?>"
		>
			<td>

							<span class="<?=($wallet->status == Account::STATUS_FULL) ? 'statusFull' : ''?>">

								<?='+'.$wallet->login?>

							</span>

			</td>
			<td>
				<strong><?=$wallet->card_number?></strong>
			</td>
			<td>
				<strong><?=$wallet->amountStr?></strong>
			</td>
			<td>
				<b><?=date('H:i', $wallet->date_add)?></b>
				<br />
				<?=date('d.m', $wallet->date_add)?>
			</td>
			<td>
				<b><?=date('H:i', $wallet->date_check)?></b>
				<br />
				<?=date('d.m', $wallet->date_check)?>
			</td>

			<td><?=$wallet->orderMsg?></td>
			<td>
				<nobr><strong><?=$wallet->balanceStr?></strong></nobr>
			</td>
			<td class="noWrap"><?=$wallet->dayLimitStr?></td>
			<td class="noWrap"><strong><?=$wallet->managerLimitStr?></strong></td>
			<td>
				<form method="post">
					<input type="hidden" name="id" value="<?=$wallet->id?>">
					<input type="submit" class="orange" name="markOld" value="Отправить в отстойник" title="Старый кошелек убираем">
				</form>
			</td>
		</tr>
	<?}?>
</table>
<?}else{?>
	не получено кошельков
<?}?>



