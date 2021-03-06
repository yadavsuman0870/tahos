<?php
set_time_limit(0);
error_reporting(E_PARSE | E_ERROR);
$startProcess = time();
echo "<br>Начало: <b>".date("d.m.Y H:i:s")."</b>";
switch($_GET['act']){
	case 'bonuses':
		require_once ('../vendor/autoload.php');
		$bonus_name = 'bonus_'.date('d.m.Y_H-i-s').'.txt';
		$log = new Katzgrau\KLogger\Logger('../logs', Psr\Log\LogLevel::WARNING, array(
			'filename' => $bonus_name,
			'dateFormat' => 'G:i:s'
		));

		$res = $db->query("
			SELECT
				*
			FROM
				#funds
			WHERE
				transfered = 0 AND
				type_operation = 3 AND
				TO_DAYS(CURRENT_DATE) - TO_DAYS(`created`) >= {$settings['days_for_return']}
		", '');
		if (!$res->num_rows){
			$log->warning('Записей для зачисления бонусов не найдено');
			die();
		}
		while($row = $res->fetch_assoc()){
			$db->query("
				UPDATE
					#users
				SET
					`bonus_count`=`bonus_count`+{$row['sum']}
				WHERE
					`id`={$row['user_id']}
			", '');
			$db->query("
				UPDATE
					#funds
				SET
					`transfered`=1
				WHERE
					`id`={$row['id']}
			", '');
			$str = stripslashes($row['comment']);
			$str = strip_tags($str);
			$str = preg_replace('/\s+/', ' ', $str);
			$log->alert("$str для пользователя id={$row['user_id']} в размере {$row['sum']} руб.");
		}
		break;
	case 'orderArmtek':
		echo "<h2>Отправка заказа в Армтек</h2>";
		$armtek = new core\Armtek($db);
		$armtek->sendOrder();
		echo "<br>Обработка завершена.";
		break;
	case 'orderRossko':
		// debug($_GET); exit();
		echo "<h2>Отправка заказа в Росско</h2>";
		$rossko = new core\Rossko($db);
		$rossko->sendOrder($_GET['store_id'] ? $_GET['store_id'] : NULL);
		if ($_GET['order_id']){
			header("Location: ?view=orders&act=change&id={$_GET['order_id']}");
			exit();
		} 
		echo "<br>Обработка завершена.";
		break;
	case 'orderVoshodAvto':
		$voshodAvto = new core\OrderAbcp($db, 6);
		$res = $voshodAvto->basketOrder();
		break;
	case 'orderMparts':
		$mparts = new core\OrderAbcp($db, 13);
		$res = $mparts->basketOrder();
		break;
	case 'toOrderFavoriteParts':
		echo "<h2>Отправка заказа Фаворит</h2>";
		$res = core\FavoriteParts::toOrder();
		if ($res === false) echo "<p>Нет товаров для отправки</p>";
		else echo "<p>$res</p>";
		break;
	case 'getItemsVoshod':
		$abcp = new core\Abcp(NULL, $db);
		$countTransaction = 50;
		$seconds = 21600;
		// $seconds = 20;
	case 'BERG_MSK':
	case 'BERG_Yar':
		echo "<h2>Прайс {$_GET['act']}</h2>";
		$price = new core\Price($db, $_GET['act']);

		$imap = new core\Imap('{imap.mail.ru:993/imap/ssl}INBOX/Newsletters');
		$filename = $imap->getLastMailFrom(['from' => 'noreply@berg.ru', 'name' => $_GET['act']]);
		if (!$filename) die("<br>Не удалось получить файл из почты.");
		$handle = fopen($filename, 'r');

		if ($_GET['act'] == 'BERG_Yar') $store_id = 276;
		if ($_GET['act'] == 'BERG_MSK') $store_id = 275;
		$db->delete('store_items', "`store_id`=$store_id");
		$i = 0;
		while ($data = fgetcsv($handle, 1000, "\n")) {
			$row = iconv('windows-1251', 'utf-8', $data[0]);
			$row = explode(';', str_replace('"', '', $row));
			$i++;
			if ($row[0] == 'Артикул') continue;
			// if ($i > 200) break;
			// debug($row); continue;
			if (!$row[0] || !$row[2]){
				$price->log->error("В строке $i произошла ошибка.");
				continue;
			}
			$brend_id = $price->getBrendId($row[2]);
			if (!$brend_id) continue;
			$item_id = $price->getItemId([
				'brend_id' => $brend_id,
				'brend' => $row[2],
				'article' => $row[0],
				'title' => $row[1],
				'row' => $i
			]);
			if (!$item_id) continue;
			$price->insertStoreItem([
				'store_id' => $store_id,
				'item_id' => $item_id,
				'price' => $row[5],
				'in_stock' => $row[4],
				'packaging' => $row[7],
				'row' => $i
			]);
		}
		
		$price->log->alert("Обработано $i строк");
		$price->log->alert("Добавлено в прайс: $price->insertedStoreItems записей");
		$price->log->alert("Вставлено: $price->insertedBrends брендов");
		$price->log->alert("Вставлено: $price->insertedItems номенклатуры");

		echo "<br>Обработано <b>$i</b> строк";
		echo "<br>Добавлено в прайс: <b>$price->insertedStoreItems</b> записей";
		echo "<br>Вставлено: <b>$price->insertedBrends</b> брендов";
		echo "<br>Вставлено: <b>$price->insertedItems</b> номенклатуры";
		echo "<br><a target='_blank' href='/admin/logs/$price->nameFileLog'>Лог</a>";
		break;
	case 'priceRossko':
		echo "<h2>Прайс Росско</h2>";
		$fileNames = [
			'77769_91489D6DA76B9D7A99061B9F7B18F3CE.csv' => 24,
			'77769_D27310FF6AA0D63D3D6B4B25EACB6C46.csv' => 25
		];
		$ciphers = [
			24 => 'ROSV',
			25 => 'ROSM'
		];
		$rossko = new core\Rossko($db);
		$imap = new core\Imap('{imap.mail.ru:993/imap/ssl}INBOX/Newsletters');
		$filename = $imap->getLastMailFrom(['from' => 'price@rossko.ru', 'name' => 'rossko_price.zip']);
		if (!$filename) die("<br>Не удалось получить файл из почты.");
		
		$zipArchive = new ZipArchive();
		// $zipArchive->open("{$_SERVER['DOCUMENT_ROOT']}/tmp/rossko_price.zip");
		$res = $zipArchive->open($filename);

		$numFiles = $zipArchive->numFiles;
		if (!$numFiles){
			echo ("<br>Ошибка скачивания файла с почты");
			break;
		} 
		$db->query("
			DELETE si FROM
				#store_items si
			LEFT JOIN
				#provider_stores ps ON ps.id=si.store_id
			WHERE 
				ps.provider_id = $rossko->provider_id
		", '');
		for ($num = 0; $num < $numFiles; $num++){
			$zipFile = $zipArchive->statIndex($num);
			$store_id = $fileNames[$zipFile['name']];
			if (!$store_id) {
				echo "<br>Неизвестное имя файла {$zipFile['name']}";
				continue;
			}
			$price = new core\Price($db, 'price_'.$ciphers[$store_id]);
			// $price->isInsertBrend = true;
			// $price->isInsertItem = true;
			$file = $zipArchive->getStream($zipFile['name']);
			$i = 0;
			while ($data = fgetcsv($file, 1000, "\n")) {
				$row = iconv('windows-1251', 'utf-8', $data[0]);
				$row = explode(';', str_replace('"', '', $row));
				$i++;
				if (substr($row[0], 0, 3) != 'NSI') continue;

				// debug($row);
				// if ($i > 100) break;
				// continue;

				if (!$row[1] || !$row[2]){
					$price->log->error("В строке $i произошла ошибка.");
					continue;
				}
				$brend_id = $price->getBrendId($row[1]);
				if (!$brend_id) continue;
				$item_id = $price->getItemId([
					'brend_id' => $brend_id,
					'brend' => $row[1],
					'article' => $row[10] ? $row[10] : $row[2],
					'title' => $row[3],
					'row' => $i
				]);
				if (!$item_id) continue;
				$price->insertStoreItem([
					'store_id' => $store_id,
					'item_id' => $item_id,
					'price' => $row[6],
					'in_stock' => $row[8],
					'packaging' => $row[5],
					'row' => $i
				]);
			}

			$price->log->alert("Обработано $i строк");
			$price->log->alert("Добавлено в прайс: $price->insertedStoreItems записей");
			$price->log->alert("Вставлено: $price->insertedBrends брендов");
			$price->log->alert("Вставлено: $price->insertedItems номенклатуры");
			
			echo "<br><b>{$ciphers[$store_id]}:</b>";
			echo "<br>Обработано <b>$i</b> строк";
			echo "<br>Добавлено в прайс: <b>$price->insertedStoreItems</b> записей";
			echo "<br>Вставлено: <b>$price->insertedBrends</b> брендов";
			echo "<br>Вставлено: <b>$price->insertedItems</b> номенклатуры";
			echo "<br><a target='_blank' href='/admin/logs/$price->nameFileLog'>Лог</a>";
		}
		$db->query("UPDATE #provider_stores SET `price_updated` = CURRENT_TIMESTAMP WHERE `provider_id`={$rossko->provider_id}", '');
		break;
	case 'priceVoshod':
		echo "<h2>Прайс Восход</h2>";
		$price = new core\Price($db, 'priceVoshod');

		$imap = new core\Imap('{imap.mail.ru:993/imap/ssl}INBOX/Newsletters');
		$filename = $imap->getLastMailFrom(['from' => 'price@voshod-avto.ru', 'name' => 'Voshod.zip']);
		if (!$filename) die("<br>Не удалось получить файл из почты.");
		
		$zipArchive = new ZipArchive();
		// $res = $zipArchive->open("{$_SERVER['DOCUMENT_ROOT']}/tmp/Voshod.zip");
		$res = $zipArchive->open($filename);
		$file = $zipArchive->getStream('Voshod.csv');

		$db->delete('store_items', "`store_id`=8");
		$i = 0;
		while ($data = fgetcsv($file, 1000, "\n")) {
			$row = iconv('windows-1251', 'utf-8', $data[0]);
			$row = explode(';', str_replace('"', '', $row));
			$i++;
			if ($row[0] == 'НаименованиеПолное') continue;
			// if ($i > 200) break;
			if (!$row[1] || !$row[2]){
				$price->log->error("В строке $i произошла ошибка.");
				continue;
			}
			$brend_id = $price->getBrendId($row[2]);
			if (!$brend_id) continue;
			$item_id = $price->getItemId([
				'brend_id' => $brend_id,
				'brend' => $row[2],
				'article' => $row[1],
				'title' => $row[0],
				'row' => $i
			]);
			if (!$item_id) continue;
			$price->insertStoreItem([
				'store_id' => 8,
				'item_id' => $item_id,
				'price' => $row[7],
				'in_stock' => $row[5],
				'packaging' => $row[6],
				'row' => $i
			]);
		}

		$price->log->alert("Обработано $i строк");
		$price->log->alert("Добавлено в прайс: $price->insertedStoreItems записей");
		$price->log->alert("Вставлено: $price->insertedBrends брендов");
		$price->log->alert("Вставлено: $price->insertedItems номенклатуры");
		
		echo "<br>Обработано <b>$i</b> строк";
		echo "<br>Добавлено в прайс: <b>$price->insertedStoreItems</b> записей";
		echo "<br>Вставлено: <b>$price->insertedBrends</b> брендов";
		echo "<br>Вставлено: <b>$price->insertedItems</b> номенклатуры";
		echo "<br><a target='_blank' href='/admin/logs/$price->nameFileLog'>Лог</a>";
		$db->query("UPDATE #provider_stores SET `price_updated` = CURRENT_TIMESTAMP WHERE `id`=8", '');
		break;
	case 'priceMikado':
		$mikado = new core\Mikado($db);
		$files = [
			'MikadoStock' => 1,
			'MikadoStockReg' => 35
		];
		foreach($files as $zipName => $value){
			$price = new core\Price($db, $zipName);
			$url = "http://www.mikado-parts.ru/OFFICE/GetFile.asp?File={$zipName}.zip&CLID={$mikado->ClientID}&PSW={$mikado->Password}";
			$resDownload = (
				file_put_contents(
					"{$_SERVER['DOCUMENT_ROOT']}/tmp/{$zipName}.zip", 
					file_get_contents($url)
				)
			);
			if (!$resDownload){
				echo "<br> Не удалось скачать $zipName";
				continue;
			}
			
			$zipArchive = new ZipArchive();
			$res = $zipArchive->open("{$_SERVER['DOCUMENT_ROOT']}/tmp/{$zipName}.zip");
			$file = $zipArchive->getStream("mikado_price_{$value}_{$mikado->ClientID}.csv");

			$db->delete('store_items', "`store_id`={$mikado->stocks[$value]}");
			$i = 0;
			while ($data = fgetcsv($file, 1000, "\n")) {
				$row = iconv('windows-1251', 'utf-8', $data[0]);
				$row = explode(';', str_replace('"', '', $row));
				$i++;
				if (!$row[1] || !$row[2]){
					$price->log->error("В строке $i произошла ошибка.");
					continue;
				}
				if (preg_match('/УЦЕНКА/ui', $row[3])) continue;
				$brend_id = $price->getBrendId($row[2]);
				if (!$brend_id) continue;
				$item_id = $price->getItemId([
					'brend_id' => $brend_id,
					'brend' => $row[2],
					'article' => $row[1],
					'title' => $row[3],
					'row' => $i
				]);
				if (!$item_id) continue;
				$db->insert('mikado_zakazcode', ['item_id' => $item_id, 'ZakazCode' => $row[0]], ['print_query' => false]);
				$price->insertStoreItem([
					'store_id' => $mikado->stocks[$value],
					'item_id' => $item_id,
					'price' => $row[4],
					'in_stock' => $row[5],
					'packaging' => 1,
					'row' => $i
				]);
			}

			$price->log->alert("Обработано $i строк");
			$price->log->alert("Добавлено в прайс: $price->insertedStoreItems записей");
			$price->log->alert("Вставлено: $price->insertedBrends брендов");
			$price->log->alert("Вставлено: $price->insertedItems номенклатуры");
			
			echo "<br><b>$zipName</b>:";
			echo "<br>Обработано <b>$i</b> строк";
			echo "<br>Добавлено в прайс: <b>$price->insertedStoreItems</b> записей";
			echo "<br>Вставлено: <b>$price->insertedBrends</b> брендов";
			echo "<br>Вставлено: <b>$price->insertedItems</b> номенклатуры";
			echo "<br><a target='_blank' href='/admin/logs/$price->nameFileLog'>Лог</a>";

			$db->query("UPDATE #provider_stores SET `price_updated` = CURRENT_TIMESTAMP WHERE `id`={$mikado->stocks[$value]}", '');
		}
		break;
	case 'priceSportAvto':
		echo "<h2>Прайс Спорт-Авто</h2>";
		$price = new core\Price($db, 'priceSportAvto');
		// $price->isInsertBrend = true;
		// $price->isInsertItem = true;

		$imap = new core\Imap('{imap.mail.ru:993/imap/ssl}INBOX/Newsletters');
		$filename = $imap->getLastMailFrom(['from' => 'zakaz@sportavto.com', 'name' => 'price.zip']);
		if (!$filename) die("<br>Не удалось получить файл из почты.");
		
		$zipArchive = new ZipArchive();
		$res = $zipArchive->open($filename);
		$file = $zipArchive->getStream('price.csv');

		$db->delete('store_items', "`store_id`=7");
		$i = 0;
		while ($data = fgetcsv($file, 1000, "\n")) {
			$row = iconv('windows-1251', 'utf-8', $data[0]);
			$row = explode(';', str_replace('"', '', $row));
			$i++;
			if ($row[0] == 'Код') continue;
			// if ($i > 200) break;
			// debug($row); continue;
			if (!$row[0] || !$row[1]){
				$price->log->error("В строке $i произошла ошибка.");
				continue;
			}
			$brend_id = $price->getBrendId($row[1]);
			if (!$brend_id) continue;
			$item_id = $price->getItemId([
				'brend_id' => $brend_id,
				'brend' => $row[1],
				'article' => $row[0],
				'title' => $row[2],
				'row' => $i
			]);
			if (!$item_id) continue;
			$price->insertStoreItem([
				'store_id' => 7,
				'item_id' => $item_id,
				'price' => $row[3],
				'in_stock' => $row[4],
				'packaging' => 1,
				'row' => $i
			]);
		}

		$db->query("UPDATE #provider_stores SET `price_updated` = CURRENT_TIMESTAMP WHERE `id`=7", '');		

		$price->log->alert("Обработано $i строк");
		$price->log->alert("Добавлено в прайс: $price->insertedStoreItems записей");
		$price->log->alert("Вставлено: $price->insertedBrends брендов");
		$price->log->alert("Вставлено: $price->insertedItems номенклатуры");
		
		echo "<br>Обработано <b>$i</b> строк";
		echo "<br>Добавлено в прайс: <b>$price->insertedStoreItems</b> записей";
		echo "<br>Вставлено: <b>$price->insertedBrends</b> брендов";
		echo "<br>Вставлено: <b>$price->insertedItems</b> номенклатуры";
		echo "<br><a target='_blank' href='/admin/logs/$price->nameFileLog'>Лог</a>";
		break;
	case 'priceArmtek':
		require_once ('../class/PHPExcel/IOFactory.php');
		echo "<h2>Прайс Армтек</h2>";
		$fileNames = [
			'Armtek_msk_40068974' => 3
			// ,'Armtek_CRS_40068974' => 4
		];
		$ciphers = [
			3 => 'ARMC'
			// ,4 => 'ARMK'
		];
		$armtek = new core\Armtek($db);
		$imap = new core\Imap('{imap.mail.ru:993/imap/ssl}INBOX/Newsletters');
		$zipArchive = new ZipArchive();

		$db->query("
			DELETE si FROM
				#store_items si
			LEFT JOIN
				#provider_stores ps ON ps.id=si.store_id
			WHERE 
				ps.provider_id = $armtek->provider_id
		", '');

		foreach($fileNames as $fileName => $store_id){
			$price = new core\Price($db, 'price_'.$ciphers[$store_id]);
			$fileImap = $imap->getLastMailFrom(['from' => 'price@armtek.ru', 'name' => $fileName]);
			if (!$fileImap){
				echo ("<br>Не удалось получить $fileName из почты.");
				continue;
			} 
			
			// $res = $zipArchive->open("{$_SERVER['DOCUMENT_ROOT']}/tmp/{$fileName}.zip");
			$res = $zipArchive->open($fileImap);
			if (!$res){
				echo "<br>Ошибка чтения файла $fileName.zip";
				continue;
			};
			$res = $zipArchive->extractTo("{$_SERVER['DOCUMENT_ROOT']}/tmp/", ["{$fileName}.xlsx"]);
			if (!$res){
				echo "<br>Ошибка извлечения файла $fileName.xlsx";
				continue;
			};

			$xls = PHPExcel_IOFactory::load("{$_SERVER['DOCUMENT_ROOT']}/tmp/$fileName.xlsx");
			$xls->setActiveSheetIndex(0);
			$sheet = $xls->getActiveSheet();
			$rowIterator = $sheet->getRowIterator();
			$i = 0;
			foreach ($rowIterator as $row) {
				$cellIterator = $row->getCellIterator();
				$row = array();
				foreach($cellIterator as $cell){
					$row[] = $cell->getCalculatedValue();
				} 
				$i++;

				// debug($row);
				// if ($i > 100) break;
				// continue;

				if ($row[0] == 'Бренд') continue;
				if (!$row[0] || !$row[1]){
					$price->log->error("В строке $i произошла ошибка.");
					continue;
				}
				$brend_id = $price->getBrendId($row[0]);
				if (!$brend_id)continue;
				$item_id = $price->getItemId([
					'brend_id' => $brend_id,
					'brend' => $row[0],
					'article' => $row[4] ? $row[4] : $row[1],
					'title' => $row[2],
					'row' => $i
				]);
				if (!$item_id) continue;
				$price->insertStoreItem([
					'store_id' => $store_id,
					'item_id' => $item_id,
					'price' => $row[6],
					'in_stock' => $row[5],
					'packaging' => 1,
					'row' => $i
				]);
			}

			$db->query("UPDATE #provider_stores SET `price_updated` = CURRENT_TIMESTAMP WHERE `id`= $store_id", '');

			$price->log->alert("Обработано $i строк");
			$price->log->alert("Добавлено в прайс: $price->insertedStoreItems записей");
			$price->log->alert("Вставлено: $price->insertedBrends брендов");
			$price->log->alert("Вставлено: $price->insertedItems номенклатуры");
			
			echo "<br><b>{$ciphers[$store_id]}:</b>";
			echo "<br>Обработано <b>$i</b> строк";
			echo "<br>Добавлено в прайс: <b>$price->insertedStoreItems</b> записей";
			echo "<br>Вставлено: <b>$price->insertedBrends</b> брендов";
			echo "<br>Вставлено: <b>$price->insertedItems</b> номенклатуры";
			echo "<br><a target='_blank' href='/admin/logs/$price->nameFileLog'>Лог</a>";
		}
		break;
	case 'priceMparts':
		require_once ('../class/PHPExcel/IOFactory.php');
		echo "<h2>Прайс МПартс</h2>";
		$imap = new core\Imap('{imap.mail.ru:993/imap/ssl}INBOX/Newsletters');

		$db->query("
			DELETE si FROM
				#store_items si
			LEFT JOIN
				#provider_stores ps ON ps.id=si.store_id
			WHERE 
				ps.provider_id = 13
		", '');

		$price = new core\Price($db, 'priceMparts');
		// $price->isInsertBrend = true;
		// $price->isInsertItem = true;

		$fileImap = $imap->getLastMailFrom(['from' => 'price@v01.ru', 'name' => 'MPartsPrice.XLSX']);
		if (!$fileImap){
			echo ("<br>Не удалось получить MPartsPrice.xlsx из почты.");
			break;
		} 

		$xls = PHPExcel_IOFactory::load($fileImap);
		$xls->setActiveSheetIndex(0);
		$sheet = $xls->getActiveSheet();
		$rowIterator = $sheet->getRowIterator();
		$i = 0;
		foreach ($rowIterator as $row) {
			$cellIterator = $row->getCellIterator();
			$row = array();
			foreach($cellIterator as $cell){
				$row[] = $cell->getCalculatedValue();
			} 
			$i++;

			// debug($row);
			// if ($i > 100) break;
			// continue;

			if ($row[0] == 'Производитель') continue;
			if (!$row[0] || !$row[1]){
				$price->log->error("В строке $i произошла ошибка.");
				continue;
			}
			$brend_id = $price->getBrendId($row[0]);
			if (!$brend_id) continue;
			$item_id = $price->getItemId([
				'brend_id' => $brend_id,
				'brend' => $row[0],
				'article' => $row[1],
				'title' => $row[3],
				'row' => $i
			]);
			if (!$item_id) continue;
			$price->insertStoreItem([
				'store_id' => 22,
				'item_id' => $item_id,
				'price' => $row[5],
				'in_stock' => $row[4],
				'packaging' => $row[6],
				'row' => $i
			]);
		}

		$db->query("UPDATE #provider_stores SET `price_updated` = CURRENT_TIMESTAMP WHERE `provider_id`= 13", '');

		$price->log->alert("Обработано $i строк");
		$price->log->alert("Добавлено в прайс: $price->insertedStoreItems записей");
		$price->log->alert("Вставлено: $price->insertedBrends брендов");
		$price->log->alert("Вставлено: $price->insertedItems номенклатуры");
		
		echo "<br>Обработано <b>$i</b> строк";
		echo "<br>Добавлено в прайс: <b>$price->insertedStoreItems</b> записей";
		echo "<br>Вставлено: <b>$price->insertedBrends</b> брендов";
		echo "<br>Вставлено: <b>$price->insertedItems</b> номенклатуры";
		echo "<br><a target='_blank' href='/admin/logs/$price->nameFileLog'>Лог</a>";
		break;
	case 'priceForumAuto':
		echo "<h2>Прайс Forum-Auto</h2>";
		require_once ('../class/PHPExcel/IOFactory.php');
		$price = new core\Price($db, 'priceForumAuto');
		$price->isInsertItem = true;
		$price->isInsertBrend = true;

		$imap = new core\Imap('{imap.mail.ru:993/imap/ssl}INBOX/Newsletters');
		$fileImap = $imap->getLastMailFrom(['from' => 'post@mx.forum-auto.ru', 'name' => 'Forum-Auto_Price.zip']);
		if (!$fileImap) die("<br>Не удалось получить файл из почты.");
		
		$db->query("
			DELETE si FROM
				#store_items si
			LEFT JOIN
				#provider_stores ps ON ps.id=si.store_id
			WHERE 
				ps.provider_id = 17
		", '');
		
		$zipArchive = new ZipArchive();
		$res = $zipArchive->open($fileImap);
		if (!$res){
			echo "<br>Ошибка чтения файла Forum-Auto_Price.zip";
			break;
		};
		$res = $zipArchive->extractTo("{$_SERVER['DOCUMENT_ROOT']}/tmp/", ["Forum-Auto_Price.xlsx"]);
		if (!$res){
			echo "<br>Ошибка извлечения файла Forum-Auto_Price.xlsx";
			break;
		};

		$xls = PHPExcel_IOFactory::load("{$_SERVER['DOCUMENT_ROOT']}/tmp/Forum-Auto_Price.xlsx");
		$xls->setActiveSheetIndex(0);
		$sheet = $xls->getActiveSheet();
		$rowIterator = $sheet->getRowIterator();
		$i = 0;
		foreach ($rowIterator as $row) {
			$cellIterator = $row->getCellIterator();
			$row = array();
			foreach($cellIterator as $cell){
				$row[] = $cell->getCalculatedValue();
			} 
			$i++;

			// debug($row);
			// if ($i > 20) break;
			// continue;

			if (!$row[0]) continue;
			if ($row[0] == 'ГРУППА') continue;
			if (!$row[0] || !$row[1]){
				$price->log->error("В строке $i произошла ошибка.");
				continue;
			}
			$brend_id = $price->getBrendId($row[0]);
			if (!$brend_id) continue;
			$item_id = $price->getItemId([
				'brend_id' => $brend_id,
				'brend' => $row[0],
				'article' => $row[1],
				'title' => $row[2],
				'row' => $i
			]);
			if (!$item_id) continue;
			$price->insertStoreItem([
				'store_id' => 22380,
				'item_id' => $item_id,
				'price' => $row[4],
				'in_stock' => $row[5],
				'packaging' => $row[6],
				'row' => $i
			]);
		}

		$db->query("UPDATE #provider_stores SET `price_updated` = CURRENT_TIMESTAMP WHERE `provider_id`= 17", '');

		$price->log->alert("Обработано $i строк");
		$price->log->alert("Добавлено в прайс: $price->insertedStoreItems записей");
		$price->log->alert("Вставлено: $price->insertedBrends брендов");
		$price->log->alert("Вставлено: $price->insertedItems номенклатуры");
		
		echo "<br>Обработано <b>$i</b> строк";
		echo "<br>Добавлено в прайс: <b>$price->insertedStoreItems</b> записей";
		echo "<br>Вставлено: <b>$price->insertedBrends</b> брендов";
		echo "<br>Вставлено: <b>$price->insertedItems</b> номенклатуры";
		echo "<br><a target='_blank' href='/admin/logs/$price->nameFileLog'>Лог</a>";
		break;
	case 'emailPrice':
		require_once($_SERVER['DOCUMENT_ROOT'].'/admin/functions/providers.function.php');
		$emailPrice = $db->select_one('email_prices', '*', "`store_id`={$_GET['store_id']}");
		$emailPrice = json_decode($emailPrice['settings'], true);
		//debug($emailPrice); //exit();
		$store = $db->select_unique("
			SELECT
				ps.id AS store_id,
				ps.title AS store,
				ps.provider_id,
				p.title AS provider
			FROM
				#provider_stores ps
			LEFT JOIN
				#providers p ON p.id = ps.provider_id
			WHERE
				ps.id = {$_GET['store_id']}
		");
		$store = $store[0];
		
		echo "<h2>Прайс {$emailPrice['title']}</h2>";

		switch($emailPrice['clearPrice']){
			case 'onlyStore': $db->delete('store_items', "`store_id`={$_GET['store_id']}"); break;
			case 'provider': $db->query("
				DELETE si FROM
					#store_items si
				LEFT JOIN
					#provider_stores ps ON ps.id=si.store_id
				WHERE 
					ps.provider_id = {$store['provider_id']}
			", '');
			break;
		}

		$price = new core\Price($db, $emailPrice['title']);
		if ($emailPrice['isAddItem']) $price->isInsertItem = true;
		if ($emailPrice['isAddBrend']) $price->isInsertBrend = true;

		$imap = new core\Imap('{imap.mail.ru:993/imap/ssl}INBOX/Newsletters');
		$fileImap = $imap->getLastMailFrom([
			'from' => $emailPrice['from'],
			'name' => $emailPrice['name']
		]);
		if (!$fileImap){
			echo ("<br>Не удалось получить {$emailPrice['name']} из почты.");
			break;
		} 
		
		$fileImap = "{$_SERVER['DOCUMENT_ROOT']}/tmp/{$emailPrice['name']}";

		if ($emailPrice['isArchive']){
			$zipArchive = new ZipArchive();
			$res = $zipArchive->open($fileImap);
			if (!$res){
				echo "<br>Ошибка чтения файла {$emailPrice['']}";
				break;
			};
			$res = $zipArchive->extractTo("{$_SERVER['DOCUMENT_ROOT']}/tmp/", [$emailPrice['nameInArchive']]);
			if (!$res){
				echo "<br>Ошибка извлечения файла {$emailPrice['nameInArchive']}: $res";
				break;
			};
			if ($emailPrice['fileType'] == 'excel') $workingFile = "{$_SERVER['DOCUMENT_ROOT']}/tmp/{$emailPrice['nameInArchive']}";
			else $workingFile = $zipArchive->getStream($emailPrice['nameInArchive']);
		}
		else $workingFile = "{$_SERVER['DOCUMENT_ROOT']}/tmp/{$emailPrice['name']}";
		/**
		 * [$stringNumber counter for strings in file]
		 * @var integer
		 */
		$stringNumber = 0;
		switch($emailPrice['fileType']){
			case 'excel':
				require_once ($_SERVER['DOCUMENT_ROOT'].'/class/PHPExcel/IOFactory.php');
				$xls = PHPExcel_IOFactory::load($workingFile);
				$xls->setActiveSheetIndex(0);
				$sheet = $xls->getActiveSheet();
				$rowIterator = $sheet->getRowIterator();
				foreach ($rowIterator as $row) {
					$cellIterator = $row->getCellIterator();
					$row = array();
					foreach($cellIterator as $cell){
						$row[] = $cell->getCalculatedValue();
					} 
					$stringNumber++;
					parse_row($row, $emailPrice['fields'], $price, $stringNumber);
					
					// debug($row);
					// if ($stringNumber > 100) break;
					// echo "<hr>";
				}
				break;
			case 'csv':
				while ($data = fgetcsv($workingFile, 1000, "\n")) {
					$row = iconv('windows-1251', 'utf-8', $data[0]);
					$row = explode(';', str_replace('"', '', $row));
					$stringNumber++;
					parse_row($row, $emailPrice['fields'], $price, $stringNumber);

					// debug($row);
					// if ($stringNumber > 100) break;
					// echo "<hr>";
				}
				break;
		}

		$db->query("UPDATE #provider_stores SET `price_updated` = CURRENT_TIMESTAMP WHERE `id`={$_GET['store_id']}", '');

		$price->log->alert("Обработано $stringNumber строк");
		$price->log->alert("Добавлено в прайс: $price->insertedStoreItems записей");
		$price->log->alert("Вставлено: $price->insertedBrends брендов");
		$price->log->alert("Вставлено: $price->insertedItems номенклатуры");
		
		echo "<br>Обработано <b>$stringNumber</b> строк";
		echo "<br>Добавлено в прайс: <b>$price->insertedStoreItems</b> записей";
		echo "<br>Вставлено: <b>$price->insertedBrends</b> брендов";
		echo "<br>Вставлено: <b>$price->insertedItems</b> номенклатуры";
		echo "<br><a target='_blank' href='/admin/logs/$price->nameFileLog'>Лог</a>";
		break;
}
$endProcess = time();
echo "<br>Окончание: <b>".date("d.m.Y H:i:s")."</b>";
echo "<br>Время обработки: <b>".($endProcess - $startProcess)."</b> секунд";
