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


// Номер строки, на которой мы остановились
$price_line_end = $_SESSION['price_line_end'];

if (empty($price_line_end)){
	printf("Очищаем таблицу в базе данных.<br>");
	$BD_link->query('TRUNCATE '.$BD_prefix.'price');
	printf("Таблица очищена.<br>");
}


// Уничтожаем переменную с количеством загруженных строк на случай, если загрузка неожиданно прервется
$_SESSION['price_line_end'] = 0;


require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$inputFileName = './upload/price.xls';

/** Create a new Xls Reader  **/
$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
//    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
//    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xml();
//    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Ods();
//    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Slk();
//    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Gnumeric();
//    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
/** Load $inputFileName to a Spreadsheet Object  **/
$spreadsheet = $reader->load($inputFileName);

// Так можно достать объект Cells, имеющий доступ к содержимому ячеек
$cells = $spreadsheet->getSheet(0)->getCellCollection();


// Далее перебираем все заполненные строки, начиная со строки 5
for ($row = 5+$price_line_end; $row <= $price_line_end + $price_line AND $row <= $cells->getHighestRow(); $row++){

    $array['artikul']=($cells->get('B'.$row))?$cells->get('B'.$row)->getValue():'';
    $artikul_file = ($cells->get('B'.$row))?$cells->get('B'.$row)->getValue():'';

    $array['item_name']=($cells->get('C'.$row))?$cells->get('C'.$row)->getValue():'';
    $array['size']=($cells->get('J'.$row))?$cells->get('J'.$row)->getValue():'';

    $array['color']=($cells->get('K'.$row))?$cells->get('K'.$row)->getValue():'';
    $color_file = ($cells->get('K'.$row))?$cells->get('K'.$row)->getValue():'';

    $array['material']=($cells->get('L'.$row))?$cells->get('L'.$row)->getValue():'';
    $array['material_structure']=($cells->get('M'.$row))?$cells->get('M'.$row)->getValue():'';
    $array['season']=($cells->get('R'.$row))?$cells->get('R'.$row)->getValue():'';
    $array['machines_class']=($cells->get('S'.$row))?$cells->get('S'.$row)->getValue():'';
    $array['quantity']=($cells->get('T'.$row))?$cells->get('T'.$row)->getValue():'';
    $array['price']=($cells->get('U'.$row))?$cells->get('U'.$row)->getValue():'';
    $array['nds_price']=($cells->get('W'.$row))?$cells->get('W'.$row)->getValue():'';


    // Проверяем существует ли фото для данного товара
    $photo = (file_exists(PhotoFileName($artikul_file, $color_file).'_1.jpg')) ? 1 : 0;


    //echo"<pre>"; print_r($array); echo"</pre>";
    $stmt = $BD_link->prepare('INSERT INTO '.$BD_prefix.'price(artikul, item_name, size, color, material, material_structure, season, machines_class, quantity, price, nds_price, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('ssssssssiddi', $array['artikul'], $array['item_name'], $array['size'], $array['color'], $array['material'], $array['material_structure'], $array['season'], $array['machines_class'], $array['quantity'], $array['price'], $array['nds_price'], $photo);

    /* выполнение подготовленного выражения  */
    $stmt->execute();

    printf("Строка %s %s добавлена в базу.<br>",$array['artikul'], $array['item_name']);

    /* Закрытие соединения и выражения*/
    $stmt->close();

}

// Закрываем соединение с базой
$BD_link->close();


if ($row < $cells->getHighestRow()) {
	// Пишем в сессию номер строки на которой остановились. Учитываем, что начинали импорт со строки номер 5
	$_SESSION['price_line_end'] = $row-5;
	echo '<meta http-equiv="refresh" content="0; URL=./data_to_mysql.php" />';
	exit;
}

$_SESSION['price_line_end'] = 0;
print_r("Данные загружены в базу.<br>");
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

