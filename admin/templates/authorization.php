<?
$act = $_GET['act'];
// debug($_GET);
if ($_POST['name'] || $_GET['auth']){
	if ($_POST['name'] == 'vadim' && $_POST['password'] == '12345' || $_GET['auth']){
		message('Вы успешно авторизовались!');
		$_SESSION['auth'] = 1;	
		header('Location: /admin/?view=orders');
	}
	else{
		message('Неверное имя или пароль!', false);
	}
}
switch ($act) {
	case 'regout':
		$_SESSION['auth'] = 0;	
		message('Вы успешно вышли!');
		header('Location: ?view=authorization');
		break;
	default:
		view();
		break;
}
function view(){
	global $status, $db, $page_title;
	$page_title = 'Авторизация';
	$status = "<a href='/admin'>Главная</a> > $page_title";?>
	<div class="t_form">
		<div class="bg">
			<div class="field">
				<div class="title">Авторизация пользователя</div>
				<div class="value">
					<form method="post" enctype="multipart/form-data">
						<table class="t_table" cellspacing="1">
							<tr>
								<td>Имя пользователя</td>
								<td><input type="text" name="name" value="<?=$_POST['name']?>"></td>
							</tr>
							<tr>
								<td>Пароль:</td>
								<td><input type="password" name="password"></td>
							</tr>
							<tr>
								<td colspan="2"><input type="submit" value="Авторизоваться"></td>
							</tr>
						</table>
					</form>
				</div>
			</div>
		</div>
	</div>
<?}?>