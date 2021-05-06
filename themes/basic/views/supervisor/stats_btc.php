<?$this->title = 'Статистика btc-переводам'?>

<h2><?=$this->title?></h2>

<p>
    <form method="post">
        с <input type="text" name="params[date_from]" value="<?=$params['date_from']?>" />
        до <input type="text" name="params[date_to]" value="<?=$params['date_to']?>" />
        <input  type="submit" name="stats" value="Показать"/>
    </form>
</p>

<?if($stats){?>

    <p>
        Всего: <strong><?=formatAmount($stats['amount'], 8)?></strong> btc
    </p>
    <?foreach($stats['days'] as $date=>$arr){?>
        <strong><?=$date?></strong><br />
        <table class="std padding" style="width: 700px;">
            <tr>
                <td>ID</td>
                <td>Сумма</td>
                <td>Адрес</td>
                <td>Дата</td>
            </tr>
            <?foreach($arr['items'] as $model){?>
                <tr>
                    <td><?=$model->id?></td>
                    <td><?=$model->amountBtcStr?></td>
                    <td><?=$model->address?></td>
                    <td><?=$model->datePayStr?></td>
                </tr>
            <?}?>
            <tr>
                <td colspan="3">всего: <strong><?=formatAmount($arr['amount'], 8)?></strong> btc</td>
            </tr>
        </table>
    <?}?>

<?}?>