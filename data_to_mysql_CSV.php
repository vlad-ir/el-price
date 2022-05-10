<?php

// Подключаем файл с настройками
require_once __DIR__ . '/settings.php';
//Инициализируем сессию:
session_start();
$user_login = filter_input_text($_SESSION['user_login']);

// Ищем пользователя в базе и проверяем, что он админ
if (!check_USER_role($user_login, 'admin', $BD_link, $BD_prefix)) {
    // Если не нашли, то уничтожаем сессию и перенаправляем на страницу входа в систему
    $BD_link->close();
    session_destroy();
    header('Location: ./');
    die();
}


/* Названия столбцов в базе данных
artikul
item_name
size
color
material
material_structure
season
machines_class
quantity
price
nds_price
*/

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <title>Электронный прайс-лист | Ромгиль-Текс</title>

    <!-- Bootstrap core CSS -->
    <link href="./css/bootstrap.css" rel="stylesheet">

    <!-- Custom styles for this template -->
    <link href="./css/signin.css" rel="stylesheet">
</head>
<body class="text-center">
<?php



printf("Очищаем таблицу в базе данных.<br>");
$BD_link->query('TRUNCATE '.$BD_prefix.'price');
printf("Таблица очищена.<br>");


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


		        printf("Строка %s %s добавлена в базу.<br>",$array['artikul'], $array['item_name']);

		        /* Закрытие соединения и выражения*/
		        $stmt->close();
			}
        $row++;
    }
    fclose($handle);
}

?>

    <p><br><br></p>
    <p class="btn_block">           
        <a href="./price.php" ><button type="button" class="btn btn-primary btn-lg btn-block">Перейти на страницу с прайс-листом</button></a>
    </p>
    <p class="btn_block">           
        <a href="./" ><button type="button" class="btn btn-success btn-block">Выход</button></a>
    </p>
    <p><br><br></p>

</body>
</html>

