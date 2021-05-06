<?php
error_reporting(0);
ini_set('display_errors', 'Off');
ini_set('display_startup_errors', 'Off');

require_once(__DIR__.'/protected/components/Tools.php');


session_start();
echo "\n headers: \n";
print_r(getallheaders());
echo "\n IP: ".Tools::getClientIp();

?>
<?/*<html>
<body>
	<script>
		document.write(navigator.webdriver);
	</script>
</body>
</html>
*/?>