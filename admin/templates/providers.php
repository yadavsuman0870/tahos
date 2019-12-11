<?php
$act = $_GET['act'];
$id = $_GET['id'];
if ($_POST['store_id']) items_submit();
switch ($act) {
	case 'provider': provider(); break;
	case 'stores': stores(); break;
	case 'orders': orders(); break;
	case 'provider_delete':
		$res = $db->delete('providers', "`id`=".$_GET['id']);
		if ($res === true){
			message('Поставщик успешно удален!');
			header('Location: ?view=providers');
		}
		else{
			message($res, false);
			header("Location: ?view=providers&act=provider&id={$_GET['id']}");
		}
		break;
	default:
		view();
}
function view(){
	global $status, $db, $page_title;
	require_once('templates/pagination.php');
	$search = $_POST['search'] ? $_POST['search'] : $_GET['search'];
	if ($search){
		$where = "`title` LIKE '%$search%' OR `cipher` LIKE '%$search%'";
		$page_title = 'Поиск по названию или шифру';
		$status = "<a href='/admin'>Главная</a> > <a href='?view=providers'>Поставщики</a> > $page_title";
	}
	else{
		$page_title = "Поставщики";
		$status = "<a href='/admin'>Главная</a> > $page_title";
	}
	$all = $db->getCount('providers', $where);
	$perPage = 30;
	$linkLimit = 10;
	$page = $_GET['page'] ? $_GET['page'] : 1;
	$chank = getChank($all, $perPage, $linkLimit, $page);
	$start = $chank[$page] ? $chank[$page] : 0;
	$providers = $db->select('providers', 'id,title,legal_region', $where, 'title', true, "$start,$perPage", true);?>
	<div id="total" style="margin-top: 10px;">Всего: <?=$all?></div>
	<div class="actions">
		<form style="margin-top: -3px;float: left;margin-bottom: 10px;" action="?view=providers&act=search" method="post">
			<input style="width: 264px;" required type="text" name="search" value="<?=$search?>" placeholder="Поиск по поставщикам">
			<input type="submit" value="Искать">
		</form>
		<a style="position: relative;left: 14px;top: 5px;" href="?view=providers&act=provider">Добавить</a>
	</div>
	<table class="t_table" cellspacing="1">
		<tr class="head">
			<td>Название</td>
			<td>Регион</td>
			<td></td>
		</tr>
		<?if (count($providers)){
			foreach($providers as $id => $provider){?>
				<tr provider_id="<?=$id?>" class="providers_box" href="?view=providers&id=<?=$id?>&act=stores">
					<td><?=$provider['title']?></td>
					<td><?=$provider['legal_region']?></td>
					<td><a  class="provider_change" href="?view=providers&act=provider&id=<?=$id?>">Изменить</a></td>
				</tr>
			<?}
		}
		else{?>
			<tr><td colspan="3">Поставщиков не найдено</td></tr>
		<?}?>
	</table>
	<?pagination($chank, $page, ceil($all / $perPage), $href = "?view=providers&search=$search&page=");
}
function stores(){
	global $db, $page_title, $status;
	$provider = $db->select_one('providers', '*', "`id`={$_GET['id']}");
	$page_title = "Склады поставщика {$provider['title']}";
	$status = "<a href='/admin'>Главная</a> > <a href='?view=providers'>Поставщики</a> > $page_title";
	$res_stores = $db->query("
		SELECT
			s.id,
			s.title,
			s.cipher
		FROM
			#provider_stores s
		WHERE
			s.provider_id={$_GET['id']}
	", '');?>
	<div id="total">Всего: <?=$res_stores->num_rows?></div>
	<div class="actions">
		<a href="#" id="store_add">Добавить</a>
	</div>
	<input type="hidden" name="provider_id" value="<?=$_GET['id']?>">
	<input type="hidden" name="store_id" value="<?=isset($_GET['id']) ? $_GET['id'] : ''?>">
	<table class="t_table" cellspacing="1">
		<tr class="head">
			<td>Название</td>
			<td>Шифр</td>
		</tr>
		<?if ($res_stores->num_rows){
			while($row = $res_stores->fetch_assoc()){?>
				<tr class="store" store_id="<?=$row['id']?>">
					<td><?=$row['title']?></td>
					<td><?=$row['cipher']?></td>
				</tr>
			<?}
		}
		else{?>
			<tr class="removable store"><td colspan="3">Складов не найдено</td></tr>
		<?}?>
	</table>
<?}
function provider(){
	global $status, $db, $page_title;
	if (!empty($_POST)) provider_save();
	if ($_GET['id']) $array = $db->select_one('providers', '*', "`id`={$_GET['id']}");
	else{
		$array = $_POST;
		$page_title = 'Добавление поставщика';
	} 
	$page_title = $array['title'];
	$status = "<a href='/admin'>Главная</a> > <a href='?view=providers'>Поставщики</a> > $page_title";
	if ($_GET['id']){?>
		<a href="?view=providers&id=<?=$_GET['id']?>&act=provider_delete" class="delete_item">Удалить</a>
		<div style="width: 100%; height: 10px"></div>
		<?if (!empty($stores)){?>
			<form method="post" enctype="multipart/form-data">
				<input type="hidden" name="items_submit" value="1"> 
				<input type="file" name="items">
				<input type="radio" id="parse_1" name="parse" value="full" checked>
				<label for="parse_1">Полностью</label>
				<input type="radio" id="parse_2" name="parse" value="particulary">
				<label for="parse_2">Частично</label>
				<input disabled type="submit" value="Загрузить">
			</form>
			<div style="width: 100%; height: 10px"></div>
		<?}?>
	<?}?>
	<div class="t_form">
		<div class="bg">
			<form method="post" enctype="multipart/form-data">
				<div class="field">
					<div class="title">Название</div>
					<div class="value"><input type=text name="title" value="<?=$array['title']?>"></div>
				</div>
				<div class="field">
					<div class="title">E-mail</div>
					<div class="value"><input type=text name="email" value="<?=$array['email']?>"></div>
				</div>
				<div class="field">
					<div class="title">Телефон</div>
					<div class="value"><input type=text name="telephone" value="<?=$array['telephone']?>"></div>
				</div>
				<div class="field">
					<div class="title">Дополнительный телефон</div>
					<div class="value"><input type=text name="telephone_extra" value="<?=$array['telephone_extra']?>"></div>
				</div>
				<div class="field">
					<div class="title">ОГРН</div>
					<div class="value"><input type=text name="ogrn" value="<?=$array['ogrn']?>"></div>
				</div>
				<div class="field">
					<div class="title">ОКПО</div>
					<div class="value"><input type=text name="okpo" value="<?=$array['okpo']?>"></div>
				</div>
				<div class="field">
					<div class="title">ИНН</div>
					<div class="value"><input type=text name="inn" value="<?=$array['inn']?>"></div>
				</div>
				<div class="field">
					<div class="title">Юридический адрес</div>
					<div class="value">
						<span>Индекс:</span>
						<input type=text name="legal_index" value="<?=$array['legal_index']?>">
						<span style="display: block; margin-top: 10px">Регион:</span>
						<input type=text name="legal_region" value="<?=$array['legal_region']?>">
						<span style="display: block; margin-top: 10px">Адрес:</span>
						<input type=text name="legal_adres" value="<?=$array['legal_adres']?>">
						<input checked style="margin-top: 10px" type="checkbox" name="is_legal" value="1" id="is_legal">
						<label for="is_legal">Совпадает с фактическим</label>
					</div>
				</div>
				<div class="field">
					<div class="title">Фактический адрес</div>
					<div class="value">
						<span>Индекс:</span>
						<input <?=$disabled?> type=text name="fact_index" value="<?=$array['fact_index']?>">
						<span style="display: block; margin-top: 10px">Регион:</span>
						<input type=text <?=$disabled?> name="fact_region" value="<?=$array['fact_region']?>">
						<span style="display: block; margin-top: 10px">Адрес:</span>
						<input type=text <?=$disabled?> name="fact_adres" value="<?=$array['fact_adres']?>">
					</div>
				</div>
				<div class="field">
					<div class="title"></div>
					<div class="value"><input type="submit" class="button" value="Сохранить"></div>
				</div>
			</form>
		</div>
	</div>
<?}
function orders(){
	global $status, $db, $page_title;
	$id = $_GET['id'];
	$db->update('orders', array('is_new' => 0), "`id`=$id");
	$order_values = $db->select('orders_values', '*', '`provider_id`='.$id);
	$page_title = "Заказы поставщика '".$db->getFieldOnID('providers', $id, 'cipher')."'";
	$status = "<a href='/admin'>Главная</a> > <a href='?view=providers'>Поставщики</a> > $page_title";?>
	<table class="t_table" cellspacing="1">
		<tr class="head">
			<td>Бренд</td>
			<td>Артикул</td>
			<td>Наименование</td>
			<td>Закуп</td>
			<td>Розница</td>
			<td>Кол-во</td>
			<td>Сумма (З)</td>
			<td>Сумма (Р)</td>
			<td>Разница</td>
			<td>Статус</td>
		</tr>
		<?if (!count($order_values)){?>
			<td colspan="12">Заказов поставщика не найдено</td>
		<?}
		else{
			foreach ($order_values as $order_value){
				$provider_item = $db->select('providers_items', 'provider_id,item_id,price,provider_id', '`id`='.$order_value['provider_item_id']);
				$provider_item = $provider_item[0];
				$provider_markup = $db->getFieldOnID('providers', $order_value['provider_id'], 'percent') / 100;
				$brend_id = $db->getFieldOnID('items', $provider_item['item_id'], 'brend_id')?>
				<tr class="status_<?=$order_value['status']?>">
					<td><?=$db->getFieldOnID('brends', $brend_id, 'title')?></td>
					<td><?=$order_value['article']?></td>
					<td><?=$order_value['title']?></td>
					<?$price = get_price($provider_item);
					$price_markup = round($price - $price * $provider_markup);?>
					<td class="price_format"><?=$price_markup?></td>
					<td class="price_format"><?=$price?></td>
					<td><?=$order_value['quan']?></td>
					<?$summ = $order_value['quan'] * $price;
					$summ_markup = $price_markup * $order_value['quan'];
					$total += $summ_markup ?>
					<td class="price_format"><?=$summ_markup?></td>
					<td class="price_format"><?=$summ?></td>
					<td class="price_format"><?=$summ - $summ_markup?></td>
					<td class="change_status">
							<b><?=$db->getFieldOnID('orders_statuses', $order_value['status'], 'title')?></b>
					</td>
				</tr>
			<?}
		}?>
		<tr>
			<td style="text-align: right" colspan="10">Итого: <b><span class="price_format"><?=$total?></span></b> руб.</td>
		</tr>
	</table>
<?}
?>