<?php 
ini_set('error_reporting', E_PARSE | E_ERROR);
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);

require_once('class/database_class.php');
require_once('core/functions.php');
require_once('admin/vendor/autoload.php');

// $db = new mysqli(
// 	's21.hostia.name',
// 	'skrtoha_main',
// 	'Imgr6AtE',
// 	'skrtoha_main'
// );
// debug($db); exit();

// $db = new mysqli(
// 	'tahos.ru',
// 	'tahos',
// 	'',
// 	'tahos'
// );
// debug($db); exit();

$db = new DataBase();
session_start();
$connection = new core\Connection($db);
if ($connection->denyAccess) die('Доступ к данной странице с Вашего ip запрещен');
$db->connection_id = $connection->connection_id;
$settings = $db->select('settings', '*', '`id`=1'); $settings = $settings[0];
$db->setProfiling();

$detect = new \Mobile_Detect;
$device = 'desktop';
if ($detect->isTablet()) $device = 'tablet';
if ($detect->isMobile() && !$detect->isTablet()) $device = 'mobile';
// echo "$device<br>";


$view = $_GET['view'] ? $_GET['view'] : 'index';
if ($view == 'exit'){
	session_destroy();
	header("Location: ".$_SERVER['HTTP_REFERER']);
}
if($_GET['act'] == 'unbind'){
	$db->delete(
		'users_socials', 
		"`user_id`={$_SESSION['user']} AND `social_id`={$_GET['id']}"
	);
	message('Социальная сеть успешно отвязана!');
	header('Location: /settings');
}
$path = "templates/$view.php";

$user = core\User::get();
$basket = get_basket();
if (file_exists($path)){
	ob_start();
	require_once($path);
	$content = ob_get_contents();
	ob_clean();
}
// debug($user);
require_once('templates/main.php');
?>
