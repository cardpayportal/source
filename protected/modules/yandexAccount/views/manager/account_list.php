<?
/**
 * @var YandexAccount[] $models
 * @var array           $params
 * @var array           $stats
 */
$this->title = 'Яндекс кошельки'

?>

<p><span class="error">Отправлять на ЯД от 5к+</span></p>

<p><span class="error">Не отправлять с QIWI</span></p>

<p><span class="error">Карта яндекс деньги это тоже самое что и ваш кошелек ЯД</span></p>


<form method="post">
    <p>
        <b>Количество</b>
        &nbsp;
        &nbsp;
        &nbsp;
        <input type="text" name="params[count]" value="<?= $params['count'] ?>"/>
        &nbsp;
        &nbsp;
        &nbsp;
        <input type="submit" name="pickAccounts" value="Получить кошельки">
    </p>
</form>

<hr>
<fieldset>
    <legend>Выберите даты отображаемых платежей</legend>
    <p>
    <form method="post" action="<?= url('yandexAccount/manager') ?>">
        с <input type="text" name="params[dateStart]" value="<?= $interval['dateStart'] ?>"/>
        до <input type="text" name="params[dateEnd]" value="<?= $interval['dateEnd'] ?>"/>
        <input type="submit" name="stats" value="Показать"/>
    </form>
    </p>

    <p>
    <form method="post" action="<?= url('yandexAccount/manager') ?>" style="display: inline">
        <input type="hidden" name="params[dateStart]" value="<?= date('d.m.Y H:i', Tools::startOfDay(time())) ?>"/>
        <input type="hidden" name="params[dateEnd]"
               value="<?= date('d.m.Y H:i', Tools::startOfDay(time() + 24 * 3600)) ?>"/>
        <input type="submit" name="stats" value="За сегодня"/>
    </form>

    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

    <form method="post" action="<?= url('yandexAccount/manager') ?>" style="display: inline">
        <input type="hidden" name="params[dateStart]"
               value="<?= date('d.m.Y H:i', Tools::startOfDay(time() - 24 * 3600)) ?>"/>
        <input type="hidden" name="params[dateEnd]" value="<?= date('d.m.Y H:i', Tools::startOfDay(time())) ?>"/>
        <input type="submit" name="stats" value="За вчера"/>
    </form>
    </p>
</fieldset>

<? if($models){ ?>

    <p>
        <b>Всего принято: <?= formatAmount($stats['amountIn'], 0) ?></b>
    </p>

    <table class="std padding">

        <thead>
        <tr>
            <th>Кошелек</th>
            <th><span class="withComment" title="Карта яндекс деньги это тоже самое что и ваш кошелек ЯД">Карта</span>
            </th>
            <th>Баланс</th>
            <th>Статус</th>
            <th>Суточный лимит</th>
            <th>Общий лимит</th>
            <th>Проверен</th>
        </tr>
        </thead>

        <tbody>
		<? foreach($models as $model){ ?>
			<?if($model->hidden == true) continue;?>
            <tr <? if(time() - $model->date_check > 3600){ ?> class='error' title="Давно не обновлялся"<? } ?>>
                <td><b><?= $model->wallet ?></b></td>
                <td><b><?= $model->cardNumberStr ?></b></td>
                <td><?= formatAmount($model->balance, 0) ?></td>
                <td>
					<? if($model->error){ ?>
                        <span class="error"><?= $model->error ?></span>
					<? }elseif(time() - $model->date_check > 3600){ ?>
                        <span class="error">ВНИМАНИЕ!!!<br> ОСТАНОВИТЕ ЗАЛИВ</span>
					<? }else{ ?>
                        <span class="success">активен</span>
					<? } ?>
                </td>
                <td>
					<?= $model->limitInDayStr ?>
                </td>
                <td>
					<?= $model->limitInMonthStr ?>
                </td>
                <td><?= $model->dateCheckStr ?></td>
            </tr>

			<? if($transactions = $model->transactionsManager){ ?>
                <tr>
                    <td colspan="7">
                        <table class="noBorder trHeight" style="margin-left: 10px; width: 100%;">
                            <tr>
                                <td><b>Сумма</b></td>
                                <td><b>Дата</b></td>
                                <td><b>Комментарий</b></td>
                            </tr>
							<? foreach($transactions as $key => $trans){ ?>
                                <tr class="success"
									<? if($key > 2){ ?>
                                        data-param="toggleRow"
                                        style="display: none;"
									<? } ?>
                                >
                                    <td width="100"><?= $trans->amount ?> руб</td>
                                    <td><?= date('d.m.Y H:i', $trans->date_add) ?></td>
                                    <td><?= $trans->comment ?></td>
                                </tr>
							<? } ?>
                        </table>
						<? if(isset($transactions) and count($transactions) > 2){ ?>
                            <button type="button" class="btn btn-info btn-mini showTransactions btn--icon">
                                <i class="fa fa-caret-down"></i>показать все
                            </button>

                            <button type="button" class="btn btn-info btn-mini hideTransactions btn--icon"
                                    style="display: none">
                                <i class="fa fa-caret-up"></i>скрыть
                            </button>
						<? } ?>
                    </td>
                </tr>
			<? } ?>
		<? } ?>
        </tbody>
    </table>


<? } ?>


<script>
    $(document).ready(function () {

		<?//показать все транзакции аккаунта?>
        $(document).on("click", ".showTransactions", function () {
            $(this).parent().find('tr[data-param=toggleRow]').show();
            $(this).text('Скрыть');
            $(this).removeClass('showTransactions');
            $(this).addClass('hideTransactions');
        });

		<?//скрыть старые транзакции?>
        $(document).on("click", ".hideTransactions", function () {
            $(this).parent().find('tr[data-param=toggleRow]').hide();
            $(this).text('Показать все');
            $(this).removeClass('hideTransactions');
            $(this).addClass('showTransactions');
        });

    });
</script>