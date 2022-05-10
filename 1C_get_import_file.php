<?

// Подключаем файл с настройками
require_once __DIR__ . '/settings.php';

$send_data = FALSE;

if (isset ( $_REQUEST ['mode'] ) && $_REQUEST ['mode'] == 'checkauth')
{
	$post_user 			= filter_input_text($_POST['cron_user']);
	$post_pw 			= filter_input_text($_POST['cron_pw']);

	// Запрашиваем логин и пароль из базы данных. Если данные не найдены
	if (!$result = $BD_link->query('SELECT * FROM '.$BD_prefix.'user WHERE login="'.$post_user.'" AND active=1 AND pwd="'.md5($post_pw).'"')->fetch_assoc()) {
		// Закрываем соединение с базой
		$BD_link->close();
		die("Ошибка авторизации. Проверьте логин и пароль.");
	
	}elseif (!check_USER_role($result['login'], 'admin', $BD_link, $BD_prefix)) {
		// Закрываем соединение с базой
		$BD_link->close();
		//Если юзер НЕ админ
		die("У вас нет прав на доступ к сайту!");
	}

	print "success\n";
}


if (isset ( $_REQUEST ['mode'] ) && $_REQUEST ['mode'] == 'file' && isset ( $_REQUEST ['filename'] ) ) 
{
	//print "Загружаем файл\n" . $_REQUEST ['filename'];
	$filename = $_REQUEST ['filename'];
	$f = fopen('./upload/'.$filename, 'w');
	fwrite($f, file_get_contents('php://input'));
	fclose($f);
	print "success\n";
}


if (isset ( $_REQUEST ['mode'] ) && $_REQUEST ['mode'] == 'import' )
{

    //printf("Очищаем таблицу в базе данных.\n");
    $BD_link->query('TRUNCATE '.$BD_prefix.'price');
    //printf("Таблица очищена.\n");


	$inputFileName = './upload/price.csv';


	$row = 1;
	if (($handle = fopen($inputFileName, 'r')) !== FALSE) {
		while (($data = fgets($handle, 4096)) !== FALSE) {

			$data = WIN_to_UTF($data);

			list($array['artikul'],
				$array['item_name'],
				$array['size'],
				$array['color'],
				$array['material'],
				$array['material_structure'],
				$array['season'],
				$array['machines_class'],
				$array['quantity'],
				$array['price'],
				$array['nds_price']) = explode(';', $data);

				if (!empty($array['artikul']) && !empty($array['item_name'])) { // Если есть стока с данными (артикул и название)

					// Проверяем существует ли фото для данного товара
					$photo = (file_exists(PhotoFileName($array['artikul'], $array['color']).'_1.jpg')) ? 1 : 0;

					//echo"<pre>"; print_r($array); echo"</pre>";

					$stmt = $BD_link->prepare('INSERT INTO '.$BD_prefix.'price(artikul, item_name, size, color, material, material_structure, season, machines_class, quantity, price, nds_price, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
					$stmt->bind_param('ssssssssiddi', $array['artikul'], $array['item_name'], $array['size'], $array['color'], $array['material'], $array['material_structure'], $array['season'], $array['machines_class'], $array['quantity'], $array['price'], $array['nds_price'], $photo);

					/* выполнение подготовленного выражения  */
					$stmt->execute();

					//printf("Строка %s %s добавлена в базу.\n",$array['artikul'], $array['item_name']);

					/* Закрытие соединения и выражения*/
					$stmt->close();
				}
			$row++;
		}
		fclose($handle);
	}

	print "success\n";
}

?>