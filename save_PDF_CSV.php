<?php

/* ============================================================================
Формирует PDF-файл с выбранными артикулами, фотографиями и подробными характеристиками
По 1 фотографии для выбранного артикула. Без группировки по артикулам.
Количество блоков в строке задается в настройках файла settings.php
=============================================================================*/

// Подключаем файл с настройками
require_once __DIR__ . '/settings.php';

$zakaz_folder_path = __DIR__ .$zakaz_folder;

session_start();
$user_login       = filter_input_text($_SESSION['user_login']);


//*******ДАННЫЕ О ПОЛЬЗОВАТЕЛЕ для PDF-документа *******//
$user_name 			= get_USER($user_login, $BD_link, $BD_prefix);
$user_name_PDF		= $user_name['name'];

$user_name			= strtolower(translit($user_name['name']));
// заменям все ненужное нам на "-"
$user_name = preg_replace('~[^-a-z0-9_]+~u', '_', $user_name);

// удаляем начальные и конечные '_'
$user_name = trim($user_name, "_");
$user_name = (!empty($user_name)) ? '_'.$user_name : '' ;


$status = 'ERROR';


$currency_ID      = filter_input_text($_COOKIE['currency_ID']);
$currency_ID = (!empty($currency_ID) && array_key_exists($currency_ID, $currency_ARR)) ? $currency_ID : 'BYN';

if ($user_login == 'testpriceru' || $user_login == 'Gallery') {
    $currency_ID = 'RUB';
}

//$sel_arr = $_POST['sel_arr'];
$sel_arr = json_decode($_POST['sel_arr'], true);


if (empty($sel_arr[0])) {
	echo json_encode(data_result($status, '<p style="color: #CC0814;">Для формирования PDF-файла выберите нужные позиции из списка</p>'));
	die();
}

if (count($sel_arr) > $max_item_num) {
    echo json_encode(data_result($status, '<p style="color: #CC0814;">Выбрано слишком много записей. Попробуйте уложиться в&nbsp;'.$max_item_num.' позиций.</p>'));
    die();
}


$sel_arr[0] += ['valuta' => $currency_ID];
// Получаем заголовки для CSV - файла
// Перекодируем в Win-кодировку данные для записи в csv-файл
$file_header = array_map('UTF_to_WIN', array_keys($sel_arr[0]));

$file_name = date("Y-m-d").'_'.strtolower($user_login).'_'.date('H-i-s', time());
$file_csv = fopen($zakaz_folder_path.$file_name.'.csv', 'w');
fputcsv($file_csv, $file_header, ";");   /* записываем заголовок в csv-файл */

//print_r($sel_arr);


// Массив с соответствием названий ключей массива с данными русским значениям
$key_RUS = [
    'artikul' => 'Артикул',
    'item_name' => 'Наименование',
    'size' => 'Размер',
    'color' => 'Цвет',
    'material' => 'Материал',
    'material_structure' => 'Состав материала',
    'season' => 'Сезонность',
    'machines_class' => 'Класс машин',
    'quantity' => 'Кол-во на складе (шт.)',
    'nds_price' => 'Цена'
];



$html_PDF = '<table width="100%" border="0" cellspacing="5" cellpadding="3"><tr>';
$idx = 1;
foreach ($sel_arr as $key => $value) {

	$artikul 	= filter_input_text($value['artikul']);
	$color 		= filter_input_text($value['color']);
	$item_name 	= filter_input_text($value['item_name']);

	$value['valuta'] = $currency_ID;

	/* записываем в csv-файл выбранные позиции*/
    // Перекодируем в Win-кодировку данные для записи в csv-файл
	$file_data = array_map('UTF_to_WIN', array_values($value));
	fputcsv($file_csv, $file_data, ";");   /* записываем данные в csv-файл */


    // Формируем имя файла для изображений в соответствии с шаблоном
	$filename = PhotoFileName($artikul, $color).'_1.jpg';

    $image_PDF = (file_exists($filename)) ? '<img src="'.$filename.'" height="200px;">' : '<img src="./images/PDF_no_photo.jpg">';
    unset($value['status'], $value['id'], $value['valuta'], $value['price'], $value['recid'], $value['quantity'],  $value['orderquantity'], $value['photo'], $value['sumorder']);

    $html_PDF .= '<td>'.$image_PDF.'</td><td>';


/*  Чтобы не показывать заглушку, если фото не найдено
    $html_PDF .= (file_exists($filename)) ? '<td><img src="'.$filename.'"></td>' : '';
*/

    foreach($value as $key_item => $value_item) {
        if ($key_item == 'nds_price') { // Выводим цену в PDF
            //$html_PDF .= $key_RUS[$key_item].': <br>'.$value_item.'&nbsp;'.$currency_ID.'<br>';
            $html_PDF .= $key_RUS[$key_item].',&nbsp;'.$currency_ID.': <br>'.$value_item.'<br>';
        }else{
            $key_item = (in_array($key_item, ['color', 'artikul', 'item_name'])) ? '' : $key_RUS[$key_item].': ';
            $html_PDF .= (empty($value_item)) ? '' : $key_item.$value_item.'<br>';
        }
    }

    $html_PDF .= '</td>';

    if ($idx % $col_num == 0) {
        $html_PDF .= '</tr><tr>';
    }
    $idx++;
}


    $html_PDF .= '</tr></table>';

fclose($file_csv);

//*******  ФОРМИРУЕМ PDF-документ  *******//
// always load alternative config file for examples
require_once('./vendor/tecnickcom/tcpdf/config/tcpdf_config.php');

// Include the main TCPDF library (search the library on the following directories).
$tcpdf_include_dirs = array(
	realpath('./vendor/tecnickcom/tcpdf/tcpdf.php')
);
foreach ($tcpdf_include_dirs as $tcpdf_include_path) {
	if (@file_exists($tcpdf_include_path)) {
		require_once($tcpdf_include_path);
		break;
	}
}


// Extend the TCPDF class to create custom Header and Footer
class MYPDF extends TCPDF {

    //Page header
    public function Header() {
        // Set font
        $this->SetFont('dejavusans', 'B', 16, '', true);
        // Title
        $this->Cell(0, 10, '', 0, false, 'C', 0, '', 0, false, 'M', 'M');
    }

    // Page footer
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('dejavusans', 'I', 8, '', true);
        // Page number
        $this->Cell(0, 10, 'Страница '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }

}


// create new PDF document
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(15);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);


// set font
$pdf->SetFont('dejavusans', '', 12, '', true);

// set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// add a page
$pdf->AddPage('R', 'A4');

// Получаем представление даты на русском
$data_zakaza = strtotime($data_zakaza);
$data_zakaza = rdate('d M Y', $data_zakaza);




$html = 'ЧАСТНОЕ ПРОИЗВОДСТВЕННОЕ УНИТАРНОЕ ПРЕДПРИЯТИЕ';
$pdf->SetFont('dejavusans', 'B', 12, '', true);
//writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true) ⇒ Object
$pdf->writeHTMLCell('', '', '', 10, $html, 0, 0, 0, true, 'C');
$pdf->Ln();

$html = '«РОМГИЛЬ-ТЕКС»';
$pdf->SetFont('dejavusans', 'B', 16, '', true);
$pdf->writeHTMLCell('', '', '', '', $html, 0, 0, 0, true, 'C');
$pdf->Ln();

$html = 'предложение от '.$data_zakaza.' г.';
$pdf->SetFont('dejavusans', 'B', 10, '', true);
//writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true) ⇒ Object
$pdf->writeHTMLCell('', '', '', '', $html, 0, 0, 0, true, 'C');
$pdf->Ln();
$pdf->Ln();

$pdf->SetFont('dejavusans', 'N', 8, '', true);
#writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true)
$pdf->writeHTMLCell('', '', 0, '', $html_PDF, 0, 1);

// Берем из файла подпись с контактами для подвала PDF-документа
$html = file_get_contents('./templates/pdf_footer.html');

// Для агентов делаем свою подпись в конце PDF-файла
if (check_USER_role($user_login, 'agent', $BD_link, $BD_prefix)) {
        $html = file_get_contents('./templates/pdf_footer_'.strtolower($user_login).'.html');
}

$pdf->SetFont('dejavusans', 'N', 10, '', true);
#writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true)
$pdf->writeHTMLCell('', '', '', '', $html, 0, 0, 0, true, 'C');

$pdf->Output($zakaz_folder_path.$file_name.'.pdf', 'F');

//*******  ФОРМИРУЕМ PDF-документ  END *******//


$status = 'OK';
$message = '<p>Выбранные позиции сохранены в файл. </p><p><a href=".'.$zakaz_folder.$file_name.'.pdf" target="_blank">Посмотреть в браузере</a><br />
<a href=".'.$zakaz_folder.$file_name.'.pdf" target="_blank" download>Скачать файл</a></p>';
echo json_encode(data_result($status, $message));

?>
