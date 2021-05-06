
<?$this->title = 'Подтверждение платежа'?>

<h2>Обнаружены схожие платежи</h2>

<table class="std padding">
    <tr>
        <td>Кошелек</td>
        <td>Отправлено</td>
        <td>Комментарий</td>
        <td>Статус</td>
        <td>Дата</td>
    </tr>

    <?foreach($payments as $model){?>
        <tr>
            <td><?=$model->to?></td>
            <td><?=$model->amountSendStr?></td>
            <td><?=$model->commentStr?></td>
            <td><?=$model->statusStr?></td>
            <td><?=$model->dateAddStr?></td>
        </tr>
    <?}?>
</table>
<br><br>
<form action="" method="post">
    <input type="submit" name="confirm" value="Все равно продолжить"/>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    <a href="<?=url('finansist/orderAdd')?>">Отмена</a>
</form>