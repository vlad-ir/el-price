<?php
/* ============================================================================
Формирует PDF-файл с выбранными артикулами и фотографиями по цветам
(если такой цвет есть в 1С и фото с цветом загружено на платформу).
Выводит по 1,2,4,5 фотографий на листе. Группировка по артикулам.
Итоговый PDF содержит краткую информацию: фото, артикул, наименование, цена
=============================================================================*/

// Подключаем файл с настройками
require_once __DIR__ . '/settings.php';

$zakaz_folder_path = __DIR__ . $zakaz_folder;

session_start();
$user_login       = filter_input_text($_SESSION['user_login']);

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
    echo json_encode(data_result($status, '<p style="color: #CC0814;">Выбрано слишком много записей. Попробуйте уложиться в&nbsp;' . $max_item_num . ' позиций.</p>'));
    die();
}


$sel_arr[0] += ['valuta' => $currency_ID];
$file_name = date("Y-m-d") . '_' . strtolower($user_login) . '_' . date('H-i-s', time());


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
class MYPDF extends TCPDF
{
    //Page header
    public function Header()
    {
        // Set font
        $this->SetFont('dejavusans', 'B', 16, '', true);
        // Title
        $this->Cell(0, 10, '', 0, false, 'C', 0, '', 0, false, 'M', 'M');
    }
}

// create new PDF document
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// set margins
$pdf->SetMargins(0, 0, 0);
$pdf->SetHeaderMargin(0);
$pdf->SetFooterMargin(0);


// set auto page breaks
//$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->SetAutoPageBreak(FALSE, 0);


$pdf->AddPage('P', 'A4');

// Получаем представление даты на русском
$data_zakaza = strtotime($data_zakaza);
$data_zakaza = rdate('d M Y', $data_zakaza);



$html = 'ЧАСТНОЕ ПРОИЗВОДСТВЕННОЕ УНИТАРНОЕ ПРЕДПРИЯТИЕ';
$pdf->SetFont('dejavusans', 'B', 12, '', true);
//writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true) ⇒ Object
$pdf->writeHTMLCell('', '', '', 40, $html, 0, 0, 0, true, 'C');
$pdf->Ln();

$html = '«РОМГИЛЬ-ТЕКС»';
$pdf->SetFont('dejavusans', 'B', 16, '', true);
$pdf->writeHTMLCell('', '', '', '', $html, 0, 0, 0, true, 'C');
$pdf->Ln();

$html = 'каталог от ' . $data_zakaza . ' г.';
$pdf->SetFont('dejavusans', 'B', 10, '', true);
//writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true) ⇒ Object
$pdf->writeHTMLCell('', '', '', '', $html, 0, 0, 0, true, 'C');
$pdf->Ln();
$pdf->Ln();


// Берем из файла подпись с контактами для шапки PDF-документа
$html = file_get_contents('./templates/pdf_header.html');

// Для агентов делаем свою подпись в конце PDF-файла
if (check_USER_role($user_login, 'agent', $BD_link, $BD_prefix)) {
    $html = file_get_contents('./templates/pdf_header_' . strtolower($user_login) . '.html');
}


$image_PDF = './images/pdf_header.jpg';
$pdf->Image($image_PDF, 0, 80, 210, '', 'JPG', '', '', TRUE);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('dejavusans', 'N', 10, '', true);
#writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true)
$pdf->writeHTMLCell('', '', '', 173, $html, 0, 0, 0, true, 'C');


$artikul_arr = []; // массив артикулов для группировки

foreach ($sel_arr as $key => $value) {


    $artikul    = filter_input_text($value['artikul']);
    $item_name  = filter_input_text($value['item_name']);

    $value['valuta'] = $currency_ID;
    $value['nds_price'] = preg_replace('/\..+$/', '', $value['nds_price']);

    // Если такого артикула не было раньше, то выводим в PDF фото и характеристики
    if (!in_array($artikul, $artikul_arr)) {

        // Выбираем из базы данных все цвета для данного артикула, у которых есть фото
        $bd_query = 'SELECT DISTINCT artikul, color FROM ' . $BD_prefix . 'price WHERE artikul="' . $artikul . '" AND photo=1';
        $result = $BD_link->query($bd_query);

            // Количество фтографий для разных цветов
            $col_photo = $result->num_rows;
            $col_photo = $col_photo > 5 ? 5 : $col_photo; // если фотографий больше 5, то берем только 5

            if ($col_photo > 0) { //Если есть фотографии

                $image_PDF = []; // Массив с именами файлов фотографий для PDF-документа

                while ($row = $result->fetch_assoc()) {
                    $color = filter_input_text($row['color']);
                    // Формируем имя файла для изображений в соответствии с шаблоном
                    $image_PDF[] = PhotoFileName($artikul, $color) . '_1.jpg';
                }


                $html_PDF_txt = '<span style="background-color: rgb(255, 0, 0); color: rgb(255, 255, 255);">' . $value['artikul'] . ' <strong>' . $value['item_name'] . '</strong></span><br>';
                $html_PDF_txt .= (mb_strlen($value['material_structure']) > 5) ? '<span style="background-color: rgb(255, 0, 0); color: rgb(255, 255, 255);">' . $value['material_structure'] . '</span><br>' : '';
                $html_PDF_txt .= '<span style="background-color: rgb(255, 0, 0); color: rgb(255, 255, 255);">Цена с НДС, ' . $currency_ID . ': ' . $value['nds_price'] . '</span>';

                switch ($col_photo) {
                    case 1:
                        // add a page
                        $pdf->AddPage('P', 'A4');
                        $pdf->Image($image_PDF[0], 0, 0, 210, '', '', '', '', false, 150, '', false, false, 0, false, false, false);

                        $pdf->SetFont('dejavusans', 'N', 10, '', true);
                        #writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true)
                        $pdf->writeHTMLCell('', '', 5, 278, $html_PDF_txt, 0, 1);
                        break;


                    case 2:
                    case 3:
                        // add a page
                        $pdf->AddPage('L', 'A4');

                        // Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)
                        $pdf->Image($image_PDF[0], 0, 0, '', 210, '', '', '', false, 150, '', false, false, 0, false, false, false);
                        $pdf->Image($image_PDF[1], 148.5, 0, '', 210, '', '', '', false, 150, '', false, false, 0, false, false, false);

                        $pdf->SetFont('dejavusans', 'N', 10, '', true);
                        #writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true)
                        $pdf->writeHTMLCell('', '', 5, 190, $html_PDF_txt, 0, 1);
                        break;


                    case 4:
                        // add a page
                        $pdf->AddPage('P', 'A4');
                        $pdf->Image($image_PDF[0], 0, 0, '', 148.5, '', '', '', false, 150, '', false, false, 0, false, false, false);
                        $pdf->Image($image_PDF[1], 105, 0, '', 148.5, '', '', '', false, 150, '', false, false, 0, false, false, false);
                        $pdf->Image($image_PDF[2], 0, 148.5, '', 148.5, '', '', '', false, 150, '', false, false, 0, false, false, false);
                        $pdf->Image($image_PDF[3], 105, 148.5, '', 148.5, '', '', '', false, 150, '', false, false, 0, false, false, false);
                        #Image(file, x, y, w, h, type, link, align, resize, dpi, palign, ismask, imgmask, border, fitbox, hidden, fitonpage)

                        $pdf->SetFont('dejavusans', 'N', 10, '', true);
                        #writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true)
                        $pdf->writeHTMLCell('', '', 5, 278, $html_PDF_txt, 0, 1);
                        break;


                    case 5:
                        // add a page
                        $pdf->AddPage('L', 'A4');
                        $pdf->Image($image_PDF[0], 0, 0, '', 210, '', '', '', false, 150, '', false, false, 0, false, false, false);
                        $pdf->Image($image_PDF[1], 148.5, 0, '', 105, '', '', '', false, 150, '', false, false, 0, false, false, false);
                        $pdf->Image($image_PDF[2], 222.75, 0, '', 105, '', '', '', false, 150, '', false, false, 0, false, false, false);
                        $pdf->Image($image_PDF[3], 148.5, 105, '', 105, '', '', '', false, 150, '', false, false, 0, false, false, false);
                        $pdf->Image($image_PDF[4], 222.75, 105, '', 105, '', '', '', false, 150, '', false, false, 0, false, false, false);
                        #Image(file, x, y, w, h, type, link, align, resize, dpi, palign, ismask, imgmask, border, fitbox, hidden, fitonpage)

                        $pdf->SetFont('dejavusans', 'N', 10, '', true);
                        #writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true)
                        $pdf->writeHTMLCell('', '', 5, 190, $html_PDF_txt, 0, 1);
                        break;
                }
            }
        
    }

    $artikul_arr[] = $artikul;
}

$pdf->Output($zakaz_folder_path . $file_name . '.pdf', 'F');

//*******  ФОРМИРУЕМ PDF-документ  END *******//


$status = 'OK';
$message = '<p>Выбранные позиции сохранены в файл. </p><p><a href=".' . $zakaz_folder . $file_name . '.pdf" target="_blank">Посмотреть в браузере</a><br />
<a href=".' . $zakaz_folder . $file_name . '.pdf" target="_blank" download>Скачать файл</a></p>';
echo json_encode(data_result($status, $message));
