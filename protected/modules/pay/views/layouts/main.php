<? $baseUrl = Yii::app()->baseUrl;

$cs = Yii::app()->getClientScript();
$cs->registerCoreScript('jquery');
$cs->registerCssFile($baseUrl.'/style/card/css/bootstrap-grid.css');
$cs->registerCssFile($baseUrl.'/style/card/css/main.css');
$cs->registerScriptFile($baseUrl.'/style/card/js/card-info.min.js');
$cs->registerScriptFile($baseUrl.'/style/card/js/jquery-3.2.1.min.js');
$cs->registerScriptFile($baseUrl.'/style/card/js/jquery.mask.min.js');
$cs->registerScriptFile($baseUrl.'/style/card/js/main.js');
?>

<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?=$this->title?></title>
</head>
<body>
	<?=$content;?>
</body>

</html>