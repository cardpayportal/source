<?php
/**
 * @var array $clientsYadArr
 * @var array $walletStatsArr
 * @var float $totalAmountRu
 * @var float $totalAmountBtc
 * @var float $totalAmount
 * @var int $countFreeAccounts
 * @var string $newYandexPayWallet
 */

$this->title = 'Статистика Yandex'
?>

<br>
<form action="" method="post" name="">
	<p>
		<label>
			<strong>Текущий кошелек для New Yandex</strong><br/>
			<br>
			<input type="text" readonly="readonly" name="params[yandexWallet]" value="<?= $newYandexPayWallet ?>">
		</label>
	</p>
    <?if($walletStatsArr){?>
        <table class="std padding">
            <tr>
                <th>Кошелек</th>
                <th>Залив за<br> сегодня</th>
            </tr>
			<?foreach($walletStatsArr as $wallet=>$stat){?>
                <tr>
                    <td><?=$wallet?></td>
                    <td><?=$stat?></td>
                </tr>
            <?}?>
        </table>
    <?}?>
	<p>
		<label>
			<strong>Добавить номера яндекс, разделитель пробел ( раз в минуту проверяется на какой<br> кошелек залили меньше всего за сутки )</strong><br/>
			<br>

			<textarea style="width: 700px" rows="12" name="params[newYandexPayWalletStr]"><?=$newYandexPayWalletStr ?></textarea>
		</label>
	</p>

	<p>
		<input type="submit" name="addNewYandexPayWalletStr" value="Добавить"/>
	</p>

</form>

<br>
<fieldset>
	<legend class="legend">Управление кошельком Client11</legend>
		<form action="" method="post" name="">
			<p>
				<label>
					<strong>Текущий кошелек Client11</strong><br/>
					<input type="text" readonly="readonly" value="<?= config('personalYandexWalletCl11') ?>">
				</label>
			</p>
			<p>
				<label>
					<strong>Заменить кошелек яда у Client11</strong><br/>
					<br>
					<input type="text" name="personalYandexWalletCl11" value="<?= $personalYandexWalletCl11 ?>">
				</label>
			</p>

			<p>
				<input type="submit" name="addPersonalYandexWalletCl11" value="Заменить"/>
			</p>

		</form>
</fieldset>

<br>

<fieldset>
	<legend class="legend">Управление кошельком Client13</legend>
	<form action="" method="post" name="">
		<p>
			<label>
				<strong>Текущий кошелек Client13</strong><br/>
				<input type="text" readonly="readonly" value="<?= config('personalYandexWalletCl13') ?>">
			</label>
		</p>
		<p>
			<label>
				<strong>Заменить кошелек яда у Client13</strong><br/>
				<br>
				<input type="text" name="personalYandexWalletCl13" value="<?= $personalYandexWalletCl13 ?>">
			</label>
		</p>

		<p>
			<input type="submit" name="addPersonalYandexWalletCl13" value="Заменить"/>
		</p>

	</form>
</fieldset>

<br>

<fieldset>
	<legend class="legend">Управление кошельком Kr42</legend>
	<form action="" method="post" name="">
		<p>
			<label>
				<strong>Текущий кошелек Kr42</strong><br/>
				<input type="text" readonly="readonly" value="<?= config('personalYandexWalletKr42') ?>">
			</label>
		</p>
		<p>
			<label>
				<strong>Заменить кошелек яда у Kr42</strong><br/>
				<br>
				<input type="text" name="personalYandexWalletKr42" value="<?= $personalYandexWalletKr42 ?>">
			</label>
		</p>

		<p>
			<input type="submit" name="addPersonalYandexWalletKr42" value="Заменить"/>
		</p>

	</form>
</fieldset>

<br>

<fieldset>
	<legend class="legend">Управление кошельком Kr46</legend>
	<form action="" method="post" name="">
		<p>
			<label>
				<strong>Текущий кошелек Kr46</strong><br/>
				<input type="text" readonly="readonly" value="<?= config('personalYandexWalletKr46') ?>">
			</label>
		</p>
		<p>
			<label>
				<strong>Заменить кошелек яда у Kr46</strong><br/>
				<br>
				<input type="text" name="personalYandexWalletKr46" value="<?= $personalYandexWalletKr46 ?>">
			</label>
		</p>

		<p>
			<input type="submit" name="addPersonalYandexWalletKr46" value="Заменить"/>
		</p>

	</form>
</fieldset>
<!---->
<!--<br>-->
<!---->
<!--<fieldset>-->
<!--	<legend class="legend">Управление кошельком Клиент 19</legend>-->
<!--	<form action="" method="post" name="">-->
<!--		<p>-->
<!--			<label>-->
<!--				<strong>Текущий кошелек Клиент 19</strong><br/>-->
<!--				<input type="text" readonly="readonly" value="--><?//= config('personalYandexWalletCl19') ?><!--">-->
<!--			</label>-->
<!--		</p>-->
<!--		<p>-->
<!--			<label>-->
<!--				<strong>Заменить кошелек яда у Клиент 19</strong><br/>-->
<!--				<br>-->
<!--				<input type="text" name="personalYandexWalletCl19" value="--><?//= $personalYandexWalletCl19 ?><!--">-->
<!--			</label>-->
<!--		</p>-->
<!---->
<!--		<p>-->
<!--			<input type="submit" name="addPersonalYandexWalletCl19" value="Заменить"/>-->
<!--		</p>-->
<!---->
<!--	</form>-->
<!--</fieldset>-->

<br>


<br>
<form action="" method="post" name="">
    <p>
        <label>
            <strong>Текущий кошелек для infoProduct Client19</strong><br/>
            <br>
            <input type="text" readonly="readonly" name="params[newYandexPayWalletInfoProduct]" value="<?= $newYandexPayWalletInfoProduct ?>">
        </label>
    </p>
	<?if($walletStatsInfoProductArr){?>
        <table class="std padding">
            <tr>
                <th>Кошелек</th>
                <th>Залив за<br> сегодня</th>
            </tr>
			<?foreach($walletStatsInfoProductArr as $wallet=>$stat){?>
                <tr>
                    <td><?=$wallet?></td>
                    <td><?=$stat?></td>
                </tr>
			<?}?>
        </table>
	<?}?>
    <p>
        <label>
            <strong>Добавить номера яндекс, разделитель пробел ( раз в минуту проверяется на какой<br> кошелек залили меньше всего за сутки )</strong><br/>
            <br>

            <textarea style="width: 700px" rows="12" name="params[newYandexPayInfoProductWalletStr]"><?=$newYandexPayInfoProductWalletStr ?></textarea>
        </label>
    </p>

    <p>
        <input type="submit" name="addInfoProductWalletStr" value="Добавить"/>
    </p>

</form>

<br>

<p>
	<strong>Общий баланс клиентов: <? if($totalAmountRu > 100)
		{ ?>
			<font color="red">
				<?= formatAmount($totalAmountRu, 0) ?>
			</font>
		<? }
		else
		{ ?>
			<font color="green">
				<?= formatAmount($totalAmountRu, 0) ?>
			</font>
		<? } ?> руб

		<? if($totalAmount > 1)
		{ ?>
			<font color="red">
				<?= formatAmount($totalAmount, 2) ?>
			</font>
		<? }
		else
		{ ?>
			<font color="green">
				~<?= formatAmount($totalAmount, 2) ?>
			</font>
		<? } ?> USD
		&nbsp&nbsp&nbsp
		Запас аккаунтов: <?= $countFreeAccounts ?>
		<br><br>
		<form method="post" action="" name="seleniumControl">
			<input type="submit" name="rebootSelenium" value="Перезагрузить Selenium"/>
		</form>

	</strong>
</p>

<table class="std padding">

	<tr>
		<td>Clients</td>
		<td>Users</td>
		<td>Баланс Yad</td>
		<td>
			<nobr>Дата проверки</nobr>
		</td>
		<td>История</td>
		<td>Баланс</td>
		<td>Действие</td>
		<td>Заявки</td>
		<td>Прокси</td>
	</tr>
	<? foreach($clientsYadArr as $info)
	{ ?>
		<tr style="border: 2px solid black;">
			<td rowspan="<?= count($info['users']) + 1 ?>">
				<b><?= $info['client']->name ?></b>
				<br>(ClientId<?= $info['client']->id ?>)

				<? if($info['client']->description)
				{ ?>
					<br><i><?= $info['client']->descriptionStr ?></i>
				<? } ?>
				<br><br>
				<b>
					Всего:
					<br>
					<? if($info['clientAmountRu'] > 100)
					{ ?>
						<font color="red">
							<?= formatAmount($info['clientAmountRu'], 0) ?>
						</font>
					<? }
					else
					{ ?>
						<font color="green">
							<?= formatAmount($info['clientAmountRu'], 0) ?>
						</font>
					<? } ?>

					руб<br>

					<? if($info['clientAmount'] > 1)
					{ ?>
						<font color="red">
							~<?= formatAmount($info['clientAmount'], 2) ?>
						</font>
					<? }
					else
					{ ?>
						<font color="green">
							~<?= formatAmount($info['clientAmount'], 2) ?>
						</font>
					<? } ?>

					USD
				</b>
			</td>
		</tr>
		<? foreach($info['users'] as $user)
	{ ?>
		<tr>
			<? $wexAccount = $user->wexAccount ?>
			<td><?= $user->name ?></td>
			<td>
				<b>
					<nobr>
						<? if($wexAccount->balance_ru > 100)
						{ ?>
							<font color="red">
								<?= formatAmount($wexAccount->balance_ru, 0) ?>
							</font>
						<? }
						else
						{ ?>
							<font color="green">
								<?= formatAmount($wexAccount->balance_ru, 0) ?>
							</font>
						<? } ?>

						руб
					</nobr>
					<br>
					<nobr>

						<? if($wexAccount->balance_total > 1)
						{ ?>
							<font color="red">
								~<?= formatAmount($wexAccount->balance_total, 2) ?>
							</font>
						<? }
						else
						{ ?>
							<font color="green">
								~<?= formatAmount($wexAccount->balance_total, 2) ?>
							</font>
						<? } ?>

						USD
					</nobr>
				</b>
			</td>
			<td>
				<i><?= ($wexAccount->date_check) ? date('d.m.Y H:i', $wexAccount->date_check) : '' ?></i>
			</td>
			<td>
				<a href="<?= url('client/yandexHistoryAdmin', [
					'wexAccountId' => $wexAccount->id,
					'user' => $user->name,
				]) ?>" target="_blank">
					<button>Перейти</button>
				</a>
			</td>
			<td>
				<button type="button" class="updateWexHistory" value="<?= $wexAccount->id ?>">
					<nobr>обновить</nobr>
				</button>
			</td>
			<td>
				<a href="<?= url('client/editWexAccount', [
					'userId' => $user->id,
					'wexAccountId' => $wexAccount->id,
				]) ?>" target="_blank">
					<button type="button" title="Редактировать" value="<?= $wexAccount->id ?>">Edit</button>
				</a>
			</td>
			<td>
				<a href="<?= url('client/YandexPayGlobalFin', [
					'userId' => $user->id,
					'wexAccountId' => $wexAccount->id,
				]) ?>" target="_blank">
					<button type="button">Войти</button>
				</a>
			</td>
			<td>
				<form method="post" action="" name="proxyControl<?= $wexAccount->id ?>">
					<input type="hidden" name="params[wexAccountId]" value="<?= $wexAccount->id ?>">
					<input type="submit" name="replaceProxy" value="Заменить"/>
				</form>
			</td>
		</tr>
	<? } ?>
	<? } ?>
</table>

<br>

<form method="post" style="display: none" id="updateWexAccountForm">
	<input type="hidden" name="params[accountId]" value=""/>

	<p>
		<input type="submit" name="updateWexAccount" value="обновить аккаунт">
	</p>
</form>

<script>
	$(document).ready(function () {

		$('.updateWexHistory').click(function () {
			$('#updateWexAccountForm [name*=accountId]').val($(this).attr('value'));
			$('#updateWexAccountForm [type=submit]').click();
		})

	});
</script>