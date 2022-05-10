<?php 

/* ====================================================================================
Формирует PDF-файл с выбранными артикулами, фотографиями и подробными характеристиками
По 1 фотографии для выбранного артикула. Группировка по артикулам.
Количество блоков в строке задается в настройках файла settings.php.
PDF-файл дополнительно содержит табилцу с перечнем выбранных и заказанных позиций.
Дополнительно формируется файл CSV с выбранными позициями для импорта в 1С.
======================================================================================*/


// Подключаем файл с настройками
require_once __DIR__ . '/settings.php';

$zakaz_folder_path = __DIR__ .$zakaz_folder;

session_start();
$user_login       = filter_input_text($_SESSION['user_login']);


//*******ДАННЫЕ О ПОЛЬЗОВАТЕЛЕ для PDF-документа *******//
$user_name 			= get_USER($user_login, $BD_link, $BD_prefix);
$user_name_PDF		= $user_name['name'];

$user_name			= strtolower(translit($user_name['name']));
// заменяем все ненужное нам на "-"
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

$sel_arr = json_decode($_POST['sel_arr'], true);

if (empty($sel_arr[0])) {
	echo json_encode(data_result($status, '<p style="color: #CC0814;">Для формирования PDF-файла выберите нужные позиции из прайс-листа</p>'));
	die();
}

if (count($sel_arr) > $max_item_num) {
    echo json_encode(data_result($status, '<p style="color: #CC0814;">Выбрано слишком много записей. Попробуйте уложиться в&nbsp;'.$max_item_num.' позиций.</p>'));
    die();
}


$sel_arr[0] += ['valuta' => $currency_ID];
$sel_arr[0] += ['discount' => 0];
// Получаем заголовки для CSV - файла
// Перекодируем в Win-кодировку данные для записи в csv-файл
$file_header = array_map('UTF_to_WIN', array_keys($sel_arr[0]));

$file_name = date("Y-m-d").'_'.strtolower($user_login).'_'.date('H-i-s', time());
$file_csv = fopen($zakaz_folder_path.$file_name.'.csv', 'w');
fputcsv($file_csv, $file_header, ";");   /* записываем заголовок в csv-файл */


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
    'nds_price' => 'Цена',
    'orderquantity' => 'Кол-во под заказ (шт.)'
];


/////////////////////////////////////////////////////////////////////////////////////////////////
//*******  Таблица с перечнем заказанных наименований, ценами и суммой заказа  *******//
$html_PDF_order = '<table width="100%" border="0" cellspacing="0" cellpadding="3">';
    $html_PDF_order .= '<tr><td style="width:30px;"></td><td style="width:70px;"></td><td style="width:160px;"></td><td style="width:110px;"></td><td style="width:120px;"></td><td style="width:70px;"></td><td style="width:60px;"></td><td style="width:80px;"></td></tr> <tr style="text-align:center;"><td style="border: #000000 solid 1px;"><strong>№</strong></td>';
    // Формируем заголовок таблицы
    foreach($sel_arr[0] as $key_item => $value_item) {
        if ($key_item == 'nds_price') { // Выводим цену в PDF
            $html_PDF_order .= '<td style="border: #000000 solid 1px;"><strong>'.$key_RUS[$key_item].', '.$currency_ID.'</strong></td>';
        }else{
            $html_PDF_order .= (in_array($key_item, ['artikul', 'item_name', 'size', 'color', 'nds_price', 'orderquantity'])) ? '<td style="border: #000000 solid 1px;"><strong>'.$key_RUS[$key_item].'</strong></td>' : '';
        }
    }
    $html_PDF_order .= '<td style="border: #000000 solid 1px;"><strong>Стоимость, '.$currency_ID.'</strong></td></tr>';

$item_summ_itog = 0;
$col_summ_itog = 0;
$idx = 1;
foreach ($sel_arr as $key => $value) {
    $html_PDF_order .= '<tr>';
    $html_PDF_order .= '<td style="border: #000000 solid 1px;">'.$idx.'</td>';
    foreach($value as $key_item => $value_item) {
            $html_PDF_order .= (in_array($key_item, ['artikul', 'item_name', 'size', 'color', 'nds_price', 'orderquantity'])) ? '<td style="border: #000000 solid 1px;">'.$value_item.'</td>' : '';
    }
    $nds_price = filter_var($value['nds_price'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $item_summ_itog += $value['orderquantity']*$nds_price;
    $col_summ_itog += $value['orderquantity'];
    $item_summ = number_format($value['orderquantity']*$nds_price, 2, '.', ' ');
    $html_PDF_order .= '<td style="border: #000000 solid 1px;">'.$item_summ.'</td>';
    $html_PDF_order .= '</tr>';
    $idx++;
}


$html_PDF_order_itog = '<tr><td></td><td></td><td></td><td></td><td></td><td style="border: #000000 solid 1px;"><strong>ИТОГО</strong></td><td style="border: #000000 solid 1px;"><strong>'.$col_summ_itog.'</strong></td><td style="border: #000000 solid 1px;"><strong>'.number_format($item_summ_itog, 2, '.', ' ').'</strong></td></tr></table><p></p>';

    ///// Вычисляем скидку /////
        $discount_message = '';
        $discount_volume = 10;
        $discount_dop = 0;
        $discount_all = 0;
        $dop_discount_message ='';

        if ($user_login == 'Gallery') {
            $discount_volume = 3;
        }


        if ($user_login == 'testpriceru' || $user_login == 'Gallery') {

                  if ($item_summ_itog > 50000 && $currency_ID == 'RUB') {
                     $discount_dop = 2;
                     $discount_all = $discount_dop + $discount_volume;
                     $dop_discount_message ='<br>Скидка за объем партии: <strong style="color: #CC0814;">'.$discount_dop.'%</strong>. <br>Общая скидка: <strong style="color: #CC0814;">'.$discount_all.'%</strong>';
                 }

                if ($item_summ_itog > 100000 && $currency_ID == 'RUB') {
                    $discount_dop = 3;
                    $discount_all = $discount_dop + $discount_volume;
                     $dop_discount_message ='<br>Скидка за объем партии: <strong style="color: #CC0814;">'.$discount_dop.'%</strong>. <br>Общая скидка: <strong style="color: #CC0814;">'.$discount_all.'%</strong>';
                }

                if ($currency_ID == 'RUB') {
                    $discount_message = '<br><br>Ваша СКИДКА на условиях предоплаты: <strong style="color: #CC0814;">'.$discount_volume.'%</strong>';
                    $discount_all = $discount_dop + $discount_volume;

                    $html_PDF_order_itog = '<tr><td colspan="5" rowspan="3">'.$discount_message.$dop_discount_message.'</td><td style="border: #000000 solid 1px;"><strong>ИТОГО</strong></td><td style="border: #000000 solid 1px;"><strong>'.$col_summ_itog.'</strong></td><td style="border: #000000 solid 1px;"><strong>'.number_format($item_summ_itog, 2, '.', ' ').'</strong></td></tr><tr><td style="border: #000000 solid 1px;"><strong>Скидка</strong></td><td style="border: #000000 solid 1px;"><strong>'.$discount_all.'%</strong></td><td style="border: #000000 solid 1px;"><strong>'.number_format($item_summ_itog*$discount_all/100, 2, '.', ' ').'</strong></td></tr><tr><td colspan="2" rowspan="1" style="border: #000000 solid 1px;"><strong>К ОПЛАТЕ</strong></td><td style="border: #000000 solid 1px;"><strong>'.number_format($item_summ_itog-$item_summ_itog*$discount_all/100, 2, '.', ' ').'</strong></td></tr></table><p></p>';
                }

        }
    ///// ******* END  Вычисляем скидку  END *******/////

$html_PDF_order .=  $html_PDF_order_itog;

//******* END  Таблица с перечнем заказанных наименований, ценами и суммой заказа  END *******//
/////////////////////////////////////////////////////////////////////////////////////////////////






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
        $this->Cell(0, 5, '', 0, false, 'C', 0, '', 0, false, 'M', 'M');
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
$pdf->SetAutoPageBreak(TRUE, 20);

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

$html = 'заявка от '.$data_zakaza.' г.';
$pdf->SetFont('dejavusans', 'B', 10, '', true);
//writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true) ⇒ Object
$pdf->writeHTMLCell('', '', '', '', $html, 0, 0, 0, true, 'C');
$pdf->Ln();
$pdf->Ln();





/////////////////////////////////////////////////////////////////////////////////////////////////
//*******  Таблица с перечнем заказанных наименований, фотографиями и характеристиками  *******//
$html_PDF = '<table width="100%" border="0" cellspacing="5" cellpadding="3"><tr>';
$idx = 1; // Счетчик итераций в цикле


$artikul_arr = []; // массив артикулов для группировки

foreach ($sel_arr as $key => $value) {

	$artikul 	= filter_input_text($value['artikul']);
	$color 		= filter_input_text($value['color']);
	$item_name 	= filter_input_text($value['item_name']);

    $value['valuta'] = $currency_ID;
    $value['discount'] = $discount_all;


	/* записываем в csv-файл выбранные позиции*/
    // Перекодируем в Win-кодировку данные для записи в csv-файл
	$file_data = array_map('UTF_to_WIN', array_values($value));
    fputs($file_csv, implode(";", $file_data)."\r\n");

	//fputcsv($file_csv, $file_data, ";");   /* записываем данные в csv-файл */

    // Если такого артикула не было раньше, то выводим в PDF фото и характеристики
    if (!in_array($artikul, $artikul_arr)) {

        // Формируем имя файла для изображений в соответствии с шаблоном
    	$filename = PhotoFileName($artikul, $color).'_1.jpg';

        $image_PDF = (file_exists($filename)) ? '<img src="'.$filename.'" height="150px;">' : '<img src="./images/PDF_no_photo.jpg">';
        unset($value['status'], $value['id'], $value['valuta'], $value['price'], $value['size'], $value['color'], $value['recid'], $value['quantity'],  $value['orderquantity'], $value['photo'], $value['sumorder'], $value['discount']);

        $html_PDF .= '<td>'.$image_PDF.'</td><td>';

    /*  Чтобы не показывать заглушку, если фото не найдено
        $html_PDF .= (file_exists($filename)) ? '<td><img src="'.$filename.'"></td>' : '';
    */

        foreach($value as $key_item => $value_item) {
            if ($key_item == 'nds_price') { // Выводим цену в PDF
                $html_PDF .= $key_RUS[$key_item].',&nbsp;'.$currency_ID.': <br>'.$value_item.'<br>';
            }else{
                $key_item = (in_array($key_item, ['color', 'artikul', 'item_name'])) ? '' : $key_RUS[$key_item].': ';
                $html_PDF .= (empty($value_item)) ? '' : $key_item.$value_item.'<br>';
            }
        }

        $html_PDF .= '</td>';

        if ($idx % $col_num_order == 0) {
            $html_PDF .= '</tr><tr>';
        }


        if ($idx % ($col_num_order*$row_num_order) == 0) { // Если достигли нужного количества строк на странице
            $html_PDF .= '</tr></table>'; // Закрываем созданную таблицу
    
            // Пишем данные в PDF-документ
            $pdf->SetFont('dejavusans', 'N', 7, '', true);
            #writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true)
            if ($col_num_order*$row_num_order < $idx){ // Если это не первая страница PDF-документа, то убираем поля сверху
                $pdf->writeHTMLCell('', '', 0, 10, $html_PDF, 0, 1);
            }else{
                $pdf->writeHTMLCell('', '', 0, '', $html_PDF, 0, 1);
            }    
    
            // Начинаем новую таблицу
            $html_PDF = '<table width="100%" border="0" cellspacing="5" cellpadding="3"><tr>';
            // add a page --- Добавляем новую страницу в PDF-документ
            $pdf->AddPage('P', 'A4');
        }


        $idx++;
    }



    $artikul_arr[] = $artikul;
}

$html_PDF       .= '</tr></table>';
//******* END  Таблица с перечнем заказанных наименований, фотографиями и характеристиками  END *******//
/////////////////////////////////////////////////////////////////////////////////////////////////

fclose($file_csv);



$pdf->SetFont('dejavusans', 'N', 7, '', true);
#writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true)
$pdf->writeHTMLCell('', '', 0, 10, $html_PDF, 0, 1);

$pdf->SetFont('dejavusans', 'N', 9, '', true);
$pdf->writeHTMLCell('', '', 5, '', $html_PDF_order, 0, 1);



// Берем из файла подпись с контактами для подвала PDF-документа
$html = file_get_contents('./templates/pdf_footer.html');

// Для агентов делаем свою подпись в конце PDF-файла
if (check_USER_role($user_login, 'agent', $BD_link, $BD_prefix)) {
        $html = file_get_contents('./templates/pdf_footer_'.strtolower($user_login).'.html');
}

// add a page --- Добавляем новую страницу в PDF-документ, чтобы подпись всегда была на отдельном листе
//$pdf->AddPage('P', 'A4');

$pdf->SetFont('dejavusans', 'N', 10, '', true);
#writeHTMLCell(w, h, x, y, html = '', border = 0, ln = 0, fill = 0, reseth = true, align = '', autopadding = true)
$pdf->writeHTMLCell('', '', '', '', $html, 0, 0, 0, true, 'C');

$pdf->Output($zakaz_folder_path.$file_name.'.pdf', 'F');

//*******  ФОРМИРУЕМ PDF-документ  END *******//


$status = 'OK';
$message = '<p>Выбранные позиции сохранены в файл. </p><p><a href=".'.$zakaz_folder.$file_name.'.pdf" target="_blank">Посмотреть в браузере</a><br /><a href=".'.$zakaz_folder.$file_name.'.pdf" target="_blank" download>Скачать файл</a></p>';

if (check_USER_role($user_login, 'manager', $BD_link, $BD_prefix) || check_USER_role($user_login, 'admin', $BD_link, $BD_prefix)) {
   $message = '<p>Выбранные позиции сохранены в файл. </p><p><a href=".'.$zakaz_folder.$file_name.'.pdf" target="_blank">Посмотреть в браузере</a><br /><a href=".'.$zakaz_folder.$file_name.'.pdf" target="_blank" download>Скачать файл</a></p><p><a href=".'.$zakaz_folder.$file_name.'.csv" target="_blank" download>Скачать файл для импорта в 1С</a></p>';
}
echo json_encode(data_result($status, $message));

?>
