<?php 

// Папка с заказами
$zakaz_folder = '/orders/';

// Количество колонок в PDF-документе для коммерческих предложений
$col_num = 2;

// Количество колонок в PDF-документе для заявок
$col_num_order = 3;
// Количество строк на одной странице в PDF-документе для заявок
$row_num_order = 5;

// Максимальное количество позиций в PDF-документе
$max_item_num = 1500;

// Количество обрабатываемых за раз строк из прайс-листа
$price_line = 50;

/*Настройки базы дынных*/
$BD_user ='root';
$BD_pass ='root';
$BD_host = 'localhost';
$BD_name = 'price_gil';
$BD_prefix = 'gilpr_';


/* Подключение к серверу MySQL */
$BD_link = new mysqli($BD_host, $BD_user, $BD_pass, $BD_name);
$BD_link->query("SET NAMES 'utf8'");

if (mysqli_connect_errno()) {
	printf("Подключение к серверу MySQL невозможно. Код ошибки: %s\n", mysqli_connect_error());
	exit;
}

// Функция фильтрует текстовые переменные, введенные пользователями в форму
function filter_input_text($input_value){
	return $input_value = (!empty($input_value)) ? htmlspecialchars(strip_tags(trim($input_value))) : '' ;
}


/* Функция возвращает в форму статус и текст сообщения
Параметр функции:
$status - результат отработки функции
$result_message - сообщение о результате отработки
*/
function data_result($status, $result_message){
	return $result = [
		'status' => $status,
		'result_message' => $result_message
	];
}



/* Функция ищет пользователя в базе и проверяет соответствие указанной роли
Параметр функции:
$user_login - логин пользователя
$user_role - роль пользователя
$BD_link - соединение с базой данных
$BD_prefix - префикс базы данных
*/
function check_USER_role($user_login, $user_role, $BD_link, $BD_prefix){
	if (!$result = $BD_link->query('SELECT * FROM '.$BD_prefix.'user WHERE login="'.$user_login.'" AND active=1 AND role="'.$user_role.'"')->fetch_assoc()) {
		return FALSE;
	}
	return TRUE;
}


/* Функция ищет пользователя в базе, согласно введенным параметрам
Параметр функции:
$user_login - логин пользователя
$user_role - роль пользователя (user по умолчанию)
$active - активность (1-активен по умолчанию; 0-выключен)
$BD_link - соединение с базой данных
$BD_prefix - префикс базы данных
*/
function get_USER($user_login, $BD_link, $BD_prefix){
	if (!$result = $BD_link->query('SELECT * FROM '.$BD_prefix.'user WHERE login="'.$user_login.'"')->fetch_assoc()) {
		return FALSE;
	}
	return $result;
}


/* Функция перевода русских названий в транслит
Параметр функции:
$value - исходная строка
*/
function translit($value)
{
	$converter = array(
		'а' => 'a',    'б' => 'b',    'в' => 'v',    'г' => 'g',    'д' => 'd',
		'е' => 'e',    'ё' => 'e',    'ж' => 'zh',   'з' => 'z',    'и' => 'i',
		'й' => 'y',    'к' => 'k',    'л' => 'l',    'м' => 'm',    'н' => 'n',
		'о' => 'o',    'п' => 'p',    'р' => 'r',    'с' => 's',    'т' => 't',
		'у' => 'u',    'ф' => 'f',    'х' => 'h',    'ц' => 'c',    'ч' => 'ch',
		'ш' => 'sh',   'щ' => 'sch',  'ь' => '',     'ы' => 'y',    'ъ' => '',
		'э' => 'e',    'ю' => 'yu',   'я' => 'ya',
 
		'А' => 'A',    'Б' => 'B',    'В' => 'V',    'Г' => 'G',    'Д' => 'D',
		'Е' => 'E',    'Ё' => 'E',    'Ж' => 'Zh',   'З' => 'Z',    'И' => 'I',
		'Й' => 'Y',    'К' => 'K',    'Л' => 'L',    'М' => 'M',    'Н' => 'N',
		'О' => 'O',    'П' => 'P',    'Р' => 'R',    'С' => 'S',    'Т' => 'T',
		'У' => 'U',    'Ф' => 'F',    'Х' => 'H',    'Ц' => 'C',    'Ч' => 'Ch',
		'Ш' => 'Sh',   'Щ' => 'Sch',  'Ь' => '',     'Ы' => 'Y',    'Ъ' => '',
		'Э' => 'E',    'Ю' => 'Yu',   'Я' => 'Ya',
	);
 
	$value = strtr($value, $converter);
	return $value;
}


/* Функция формирует название файла с фотографией товара
Параметр функции:
$artikul_file - артикул товара
$color_file - цвет товара
*/
function PhotoFileName($artikul_file, $color_file)
{
	$artikul_file = strtolower(translit($artikul_file));
	$color_file = strtolower(translit($color_file));


	// заменям все ненужное нам на "_"
	$color_file = preg_replace('~[^-a-z0-9_]+~u', '_', $color_file);

	// удаляем начальные и конечные '_'
	$color_file = trim($color_file, "_");
	$color_file = (!empty($color_file)) ? '_'.$color_file : '' ;

	// Формируем имя файла для изображений в соответствии с шаблоном
	$filename = './prodimg/'.$artikul_file.'/'.$artikul_file.$color_file;
	return $filename;
}


/* Функция формирует название файла с фотографией товара для превью
Параметр функции:
$artikul_file - артикул товара
$color_file - цвет товара
*/
function PreviewPhotoFileName($artikul_file, $color_file)
{
	$artikul_file = strtolower(translit($artikul_file));
	$color_file = strtolower(translit($color_file));


	// заменям все ненужное нам на "-"
	$color_file = preg_replace('~[^-a-z0-9_]+~u', '_', $color_file);

	// удаляем начальные и конечные '_'
	$color_file = trim($color_file, "_");
	$color_file = (!empty($color_file)) ? '_'.$color_file : '' ;

	// Формируем имя файла для изображений в соответствии с шаблоном
	$filename = './prodimg/small/'.$artikul_file.'/'.$artikul_file.$color_file;
	return $filename;
}


/* массив соответсвия кодов валют Нацбанка РБ и их буквенных обозначений
//145 //доллары США USD
//290 //Гривны UAH
//292 //Евро EUR
//298 //Российские рубли RUB
*/
$currency_ARR = [
	'RUB' => 298,
	'USD' => 145,
	'EUR' => 292
];

/* Функция получает курс валют из Нацбанка РБ
Параметр функции:
$currency_ID - буквенный код валюты 
//145 //доллары США USD
//290 //Гривны UAH
//292 //Евро EUR
//298 //Российские рубли RUB
*/
function ExchangeRates($currency_ID)
{
	$arrContextOptions=array(
		"ssl"=>array(
			"verify_peer"=>false,
			"verify_peer_name"=>false,
		),
	); 

	$exchange_rates = file_get_contents('https://www.nbrb.by/api/exrates/rates/'.strtolower($currency_ID).'?parammode=2', false, stream_context_create($arrContextOptions));
	$exchange_rates = json_decode($exchange_rates, true);
	return $exchange_rates['Cur_OfficialRate']/$exchange_rates['Cur_Scale'];
}


/* function ExchangeRates($currency_ID)
{
	$currency_FIX = [
		'RUB' => 0.0255,
		'USD' => 3.2,
		'EUR' => 3.4
	];
	return $currency_FIX[$currency_ID];
} */

/* Функция перекодирует текс для формирования CSV под Windows */
function UTF_to_WIN($n){return(iconv( "UTF-8", "cp1251",  $n));}

/* Функция перекодирует текс из Win в UTF */
function WIN_to_UTF($n){return(iconv( "cp1251", "UTF-8",  $n));}

/* Функция переводит дату в формате timeshtamp в русское представление 
Пример rdate('d M Y'); выведет результат: 01 Марта 2012
*/
function rdate($param, $time=0) {
	if(intval($time)==0)$time=time();
	$MonthNames=array("Января", "Февраля", "Марта", "Апреля", "Мая", "Июня", "Июля", "Августа", "Сентября", "Октября", "Ноября", "Декабря");
	if(strpos($param,'M')===false) return date($param, $time);
		else return date(str_replace('M',$MonthNames[date('n',$time)-1],$param), $time);
}

/* Функция читает все файлы из заданного каталога и подкаталогов */
function find_all_files($dir)
{
    $root = scandir($dir);
    foreach($root as $value)
    {
        if($value === '.' || $value === '..') {continue;}
        if(is_file("$dir/$value")) {$result[]="$dir/$value";continue;}
        foreach(find_all_files("$dir/$value") as $value)
        {
            $result[]=$value;
        }
    }
    return $result;
}
?>
