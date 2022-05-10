<?php

// Подключаем файл с настройками
require_once __DIR__ . '/settings.php';
//Инициализируем сессию:
session_start();
$user_date        = filter_input_text($_SESSION['user_date']);
$user_permission  = filter_input_text($_SESSION['user_permission']);
$user_login       = filter_input_text($_SESSION['user_login']);

$currency_ID      = filter_input_text($_COOKIE['currency_ID']);

$currency_ID = (!empty($currency_ID) && array_key_exists($currency_ID, $currency_ARR)) ? $currency_ID : 'BYN';

if ($user_login == 'testpriceru' || $user_login == 'Gallery') {
    $currency_ID = 'RUB';
}

if ($user_date !== date("Y-m-d") OR $user_permission !== 'dostupno') {
//уничтожаем сессию и перенаправляем на страницу входа в систему
session_destroy();
header('Location: ./');
die();
}
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
    <link href="./css/w2ui.min.css" rel="stylesheet">
    <link href="./css/jquery.fancybox.min.css" rel="stylesheet">

    <link href="./css/chief-slider.min.css" rel="stylesheet">
    <link href="./css/price.css" rel="stylesheet">

</head>

<body>

    <div class="container" style="max-width: 100%; min-width: 900px; overflow: auto;">
        <div class="row">
          <div class="col-2">
              <h6 class="photo_header">Фотографии</h6>
              <div id="grid2"></div>
          </div>
          <div class="col-10">
              <div id="grid" style="height: 99vh;"></div>
          </div>
        </div>
    </div>

<?php

// Получаем курс выбранной валюты
$exchange_rates = (!empty($currency_ID) && $currency_ID !='BYN') ? ExchangeRates($currency_ID) : 1;

// Обычным пользователям показываем только товары с количеством 5 и выше
$BD_query = 'SELECT * FROM '.$BD_prefix.'price WHERE quantity > 4 AND season NOT LIKE "заказ%" AND color IS NOT NULL ORDER BY photo DESC';
// Если в систему заходит наш сотрудник или админ, то показываем все записи из базы данных
if (check_USER_role($user_login, 'manager', $BD_link, $BD_prefix) || check_USER_role($user_login, 'admin', $BD_link, $BD_prefix)) {
    $BD_query = 'SELECT * FROM '.$BD_prefix.'price ORDER BY id ASC';
}

$result = $BD_link->query($BD_query);

    /* Выбираем результаты запроса: */
    $row_n = '';
    while( $row = $result->fetch_assoc() ){
      $row['price'] = $row['price'] / $exchange_rates;
      $row['nds_price'] = $row['nds_price'] / $exchange_rates;

        // Округление для разных валют
        if ($currency_ID == 'RUB') {
            $row['price'] = ceil($row['price']/10) * 10;
            $row['nds_price'] = ceil($row['nds_price']/10) * 10;
        }

        if ($currency_ID == 'EUR' OR $currency_ID == 'USD') {
            $row['price'] = round($row['price']*10) / 10;
            $row['nds_price'] = round($row['nds_price']*10) / 10;
        }

        // Форматируем получившиеся цены для отображения в таблице
        $row['price']       = number_format($row['price'], 2, '.', ' ');
        $row['nds_price']   = number_format($row['nds_price'], 2, '.', ' ');

      $row_n .= json_encode($row).',';
      //echo"<pre>"; print_r($row_n); echo"</pre>";
    }

?>

    <script src="./js/jquery-3.5.1.min.js"></script>
    <script src="./js/w2ui.min.js"></script>
    <script src="./js/jquery.fancybox.min.js"></script>
    <script src="./js/chief-slider.min.js"></script>


    <script type="text/javascript">

    w2utils.locale('./js/locale/ru-ru.json');

    $(function () {
        $('#grid').w2grid({
            name: 'grid',
            recordHeight : 28,
            show: {
                toolbar: true,
                footer: true
            },


            checkAll: function () {
                // Если ничего не искали
                if (0 === this.searchData.length){
                    this.set({ status: true });
                    $('#check_all_box').prop('checked', !0);
                }else{
                    array = [];
                    array = this.last.searchIds;
                    $('#check_all_box').prop('checked', 0);

                    if (0 !== array.length){
                        array.forEach(function(w) {
                            var checkedrow = w2ui['grid'].records[w]['id'];
                            w2ui['grid'].set(checkedrow, { status: true });
                        });
                        $('#check_all_box').prop('checked', !0);
                    }
                }

                // Показываем количество выбранных записей в стоке статуса
                w2ui.grid.status('Выбрано записей: '+w2ui.grid.getAllChecked().length);
            },

            uncheckAll: function () {
                this.set({ status: false });
            },

            getAllChecked: function () { // Пользовательская функция. Получает отмеченные чекбоксами позиции
            	return this.find({ status: true });
            },

            toolbar: {
                tooltip: 'bottom',
                right : '<img src="./images/romgil-logo.png" alt="Ромгиль-Текс" height="20" title="Ромгиль-Текс">&nbsp;Электронный прайс-лист | Ромгиль-Текс&nbsp;',
                items: [
                    { type: 'break' },

                   { type: 'menu', id: 'mainMenu', text: 'Главное меню', items: [
                        { type: 'button', id: 'save_PDForder', text: 'Сохранить в PDF', img: 'icon-pdf' },
                        { type: 'button', id: 'save_PDF_bigphoto', text: 'Сохранить в PDF (большие фото)', img: 'icon-pdf' },
                        { type: 'button', id: 'save_PDF_catalog', text: 'Сохранить в PDF (каталог)', img: 'icon-pdf' },
                        { type: 'button', id: 'save_PDF_catalog_allphoto', text: 'Сохранить в PDF (каталог, все фото)', img: 'icon-pdf' },
                        { type: 'button', id: 'save_ORDER', text: 'Сформировать заявку', img: 'icon-xls' },
                        ],
                    img: 'icon-folder' },

                    <?php 
                    // Ищем пользователя в базе и проверяем, что он админ
                    if (check_USER_role($user_login, 'admin', $BD_link, $BD_prefix)) {
                    ?>
                        /*Только для админа*/
                        { type: 'menu', id: 'adminMenu', text: 'Для админа', items: [
                            { type: 'button', id: 'uploadPrice', text: 'Загрузить прайс-лист', img: 'icon-upload'},
                            { type: 'button', id: 'usersAdmin', text: 'Управление пользователями', img: 'icon-users' },
                            ],
                        img: 'icon-folder' },
                        /*Только для админа*/
                    <?php 
                    }
                    ?>

                    { type: 'button', id: 'contactMenu', text: 'Контакты', img: 'icon-contacts'},

                    { type: 'menu-radio', id: 'currencyMenu', img: 'icon-wallet',
                        text: function (item) {
                            var text = item.selected;
                            var el   = this.get('currencyMenu:' + item.selected);
                            var currency_ID = el.id;
                            return 'Валюта: ' + el.text;
                        },
                        selected: '<?php echo $currency_ID; ?>',
                        items: [
                            { id: 'BYN', text: 'BYN'},
                            { id: 'RUB', text: 'RUB'},
                            { id: 'USD', text: 'USD'},
                            { id: 'EUR', text: 'EUR'}
                        ]
                    },

                    { type: 'break' },
                    { type: 'button', id: 'exit', text: 'Выход', img: 'icon-exit' },
                ],
                onClick: function (event) {
                    //console.log('item '+ event.target + ' is clicked.');
                    if (event.target=='contactMenu') {
                        var contactURL = './templates/popup_contacts-info.html';
                    <?php  // Ищем пользователя в базе и проверяем, что он агент
                    if (check_USER_role($user_login, 'agent', $BD_link, $BD_prefix)) { ?>
                        contactURL = './templates/popup_contacts-info_<?php echo strtolower($user_login); ?>.html';
                    <?php  }  ?>
                        w2popup.load({ url:contactURL});
                    }

                    if (event.target=='mainMenu:save_PDForder') {
                        ajaxPOST(grid, 'save_PDF_CSV.php');
                    }

                    if (event.target=='mainMenu:save_PDF_bigphoto') {
                        ajaxPOST(grid, 'save_PDF_bigphoto.php');
                    }

                    if (event.target=='mainMenu:save_PDF_catalog') {
                        ajaxPOST(grid, 'save_PDF_catalog.php');
                    }

                    if (event.target=='mainMenu:save_PDF_catalog_allphoto') {
                        ajaxPOST(grid, 'save_PDF_catalog_allphoto.php');
                    }

                    if (event.target=='mainMenu:save_ORDER') {

                        var sel = w2ui['grid'].getAllChecked(); // Выбранные элементы
                        var sel_arr=[]; // Массив с полученными значениями выбранных элементов
                        sel.forEach(function(item, i) {
                        	var new_arr_obj = w2ui['grid'].get(sel[i]);
                             // Добавляем количество каждой позиции по умолчанию
                            if (!new_arr_obj.orderquantity) {
                                new_arr_obj.orderquantity = 1;
                                }
                            var nds_price = new_arr_obj.nds_price;
                                // Считаем сумму заказа, преобразуем строку в число
                                new_arr_obj.sumorder = parseInt(String(nds_price).replace(/ /g, ''))*1;
                                sel_arr.push(new_arr_obj);
                        });

                        var width_popup = 900;
                        var height_popup = 600;
                        var title_popup = 'Сформировать заявку';
                        var body_popup = '<div style="padding-top: 10px; text-align:center; font-size:1.2em; line-height:1.2em;"><p>Для каждой выбранной позиции проставьте необходимое количество в предпоследнее поле «Кол-во под заказ».<br>Редактирование включается двойным щелчком «мыши» по полю. <strong>Валюта: <?php echo $currency_ID; ?></strong>.</p></div><div style="text-align:center; font-size:1.3em; line-height:1.2em;" id="dopmessages"></div><div id="main_popup" style="position: relative; height: calc( 100% - 120px );"></div>';
                        var buttons_popup = '<button class="w2ui-btn" onclick="w2popup.close();">Отмена</button> <button class="btn btn-primary btn-sm" onclick="SaveORDER();">Сформировать заявку</button>';

                        if (sel_arr.length < 1) {
                            width_popup = 500;
                            height_popup = 300;
                        	title_popup = '<strong style="color: #CC0814;">Внимание! Ошибка!</strong>';
                        	body_popup = '<div class="w2ui-centered" style="font-size:2em; line-height:1.2em;"><p style="color: #CC0814;">Для формирования заявки выберите нужные позиции из списка</p></div>';
                        	buttons_popup = '<button class="w2ui-btn" onclick="w2popup.close();">Закрыть</button>';
                        }


                        w2popup.open({
                        	title   : title_popup,
                        	width   : width_popup,
                        	height  : height_popup,
                        	showMax : true,
                        	body    : body_popup,
                        	buttons: buttons_popup,
                        	onOpen  : function (event) {
                        		event.onComplete = function () {

                        			$('#main_popup').w2grid({
                        				name: 'gridUP',
                        				recid: 'id',
                        				show: {
                        					lineNumbers : true
                        				},

                        				columns: [
                        				{ field: 'id', text: '#', size: '50px', sortable: true, render: 'int', hidden: true},
                        				{ field: 'artikul', text: 'Артикул', size: '80px', sortable: true, style: 'font-weight: bold; color:#079437; min-width:200px;'},
                        				{ field: 'item_name', text: 'Наименование', sortable: true },
                        				{ field: 'size', text: 'Размер', sortable: true},
                        				{ field: 'color', text: 'Цвет', sortable: true},
                        				{ field: 'material', text: 'Сырье', hidden: true},
                        				{ field: 'material_structure', text: 'Сырьевой состав', sortable: true},
                        				{ field: 'season', text: 'Сезонность', hidden: true},
                        				{ field: 'machines_class', text: 'Класс машин', hidden: true},
                        				{ field: 'quantity', text: 'Кол-во', size: '60px', render: 'int', hidden: true},
								        //{ field: 'price', text: 'Цена без НДС', size: '100px', sortable: true},
								        { field: 'nds_price', text: 'Цена с НДС', size: '100px', sortable: true},
								        { field: 'orderquantity', text: 'Кол-во под заказ', title: 'Кол-во под заказ', size: '80px', style: 'font-weight: bold; border: 2px solid #1da800; background-color: #b1ff9b;', render: 'number:0', editable: { type: 'float:0' }},
                                        { field: 'sumorder', text: 'Сумма', title: 'Сумма', size: '80px', style: 'font-weight: bold;', render: 'float'}
								             ],
								             records: sel_arr,
                                             summary: [
                                                    {recid: 's-1', nds_price: '<span style="float: right; font-weight: bold;">ИТОГО: </span>', orderquantity: 0, sumorder: 0 }
                                             ],
                                            onRender: function(event) {
                                              var obj = this;
                                              event.done(function() {
                                                updateTotal(obj);
                                              });
                                            },
								             onChange: function(event) {
								             	if (parseInt(String(event.value_new).replace(/ /g, '')) > 5000 || event.value_new < 1){
								             		w2ui['gridUP'].error('<p style="font-size:1.2em; line-height:1.2em; color: #CC0814;">Количество должно находиться в пределах <strong>от 1 до 5000</strong>.</p>');
								             		event.preventDefault();
								             	}else{
								             		w2ui['gridUP'].set(event.recid,{orderquantity:event.value_new});
                                                    w2ui['grid'].set(event.recid,{orderquantity:event.value_new});
								             	}
                                                  var obj = this;
                                                  event.done(function() {
                                                    updateTotal(obj);
                                                  });
								             }
								          });
                        		};
                        	},
							onClose : function () {
								w2ui['gridUP'].destroy();
							}

                        });
                    }

                    if (event.target=='adminMenu:uploadPrice') {
                        w2popup.load({ url: './templates/popup_price-upload.html'});
                    }
                    if (event.target=='adminMenu:usersAdmin') {
                        alert('Управление пользователями');
                    }

                    var el = this.get(event.target);
                    var Menu_Selected = 'currencyMenu:' + el.id;
                    if (event.target==Menu_Selected) {
                        // Запоминаем выбранную валюту в Cookie на месяц
                        document.cookie = 'currency_ID='+el.id+'; SameSite=Lax; max-age=2592000';
                        //window.location.href = './price.php?currency_ID='+el.id;
                        window.location.href = './price.php';
                     }

                    if (event.target=='exit') {
                        window.location.href = './';
                    }
                }
            },
            recid: 'id',
            multiSearch: true,
            multiSelect: false,
            searches: [
                { field: 'artikul', text: 'Артикул', type: 'text', label: 'Артикул', operator: 'contains'},
                { field: 'item_name', text: 'Наименование', type: 'text', label: 'Наименование', operator: 'contains'},
                { field: 'size', text: 'Размер', type: 'text', label: 'Размер', operator: 'contains'},
                { field: 'color', text: 'Цвет', type: 'text', label: 'Цвет', operator: 'contains'},
                { field: 'material', text: 'Материал', type: 'text', label: 'Материал', operator: 'contains'},
                { field: 'material_structure', text: 'Сырьевой состав', type: 'text', label: 'Сырьевой состав', operator: 'contains'},
                { field: 'season', text: 'Сезонность', type: 'text', label: 'Сезонность', operator: 'contains'},
                { field: 'machines_class', text: 'Класс машин', type: 'text', label: 'Класс машин', operator: 'contains'},
                { field: 'quantity', text: 'Количество', type: 'int', label: 'Количество', operator: 'contains'}
            ],
            sortData: [ { field: 'id', direction: 'asc' } ],
            columns: [

                { field: 'status', text: '<div style="text-align: right;"><input type="checkbox" id="check_all_box" tabindex="-1" onmousedown="if (event.stopPropagation) event.stopPropagation(); else event.cancelBubble = true;" onclick="var grid = w2ui[\'grid\']; if (this.checked) grid.checkAll(); else grid.uncheckAll(); if (event.stopPropagation) event.stopPropagation(); else event.cancelBubble = true; clearTimeout(grid.last.kbd_timer); "></div>', size: '40px', 
                    render: function (record) {
                        return '<div style="text-align: center">'+
                        '    <input class="w2ui-grid-select-box" type="checkbox" ' + (record.status ? 'checked' : '') + 
                        '        onclick="var obj = w2ui[\''+ this.name + '\']; obj.get('+ record.recid +').status = this.checked; ">'+
                        '</div>';
                    }
                },

                { field: 'id', text: '#', size: '50px', sortable: true, render: 'int'},
                { field: 'photo', text: 'Фото', size: '54px', sortable: true, 

                    render: function (record) {
                        if (+record.photo > 0) {
                        return '<div style="text-align: center"><img src="./images/photo_icon.png" width="20px"></div>';
                        }
                    }

            },
                { field: 'artikul', text: 'Артикул', size: '80px', sortable: true, style: 'font-weight: bold; color:#079437;'},
                { field: 'item_name', text: 'Наименование', size: '200px', sortable: true },
                { field: 'size', text: 'Размер', size: '140px', sortable: true},
                { field: 'color', text: 'Цвет', size: '170px', sortable: true},
                { field: 'material', text: 'Сырье', size: '90px', sortable: true},
                { field: 'material_structure', text: 'Сырьевой состав', size: '170px', sortable: true},
                { field: 'season', text: 'Сезонность', size: '110px', sortable: true},
                { field: 'machines_class', text: 'Класс машин', size: '170px', sortable: true},
                { field: 'quantity', text: 'Кол-во', title:'Кол-во', size: '60px', render: 'int', sortable: true},
                //{ field: 'price', text: 'Цена без НДС', size: '100px', sortable: true},
                { field: 'nds_price', text: 'Цена с НДС', title:'Цена с НДС', size: '90px', sortable: true},
                { field: 'orderquantity', text: 'Заказано', title:'Заказано', size: '1px', render: 'int', hidden: true}
            ],
            //url: 'upload/price.json',
            //method: 'GET' // need this to avoid 412 error on Safari
            records: [<?php echo $row_n; ?>],
            onClick: function (event) {
                $('#grid2').empty();
                var record = this.get(event.recid);
                //console.log(event);
                $.ajax({
                    type: 'POST',
                    url: 'show_photo.php',
                    dataType: 'JSON',
                    //contentType: false,
                    //processData: false,
                    data: {artikul:record.artikul, color:record.color, item_name:record.item_name},
                    success:function(response) {
                        if(response.status=='OK'){
                            $('#grid2').append(response.result_message);
                            new ChiefSlider('#prodslider', {
                                loop: true,
                                autoplay: true,
                                interval: 3000,
                              });
                        }else{
                            $('#grid2').append(response.result_message);
                            //alert(response.result_message);
                        }
                    }
                });
            }
        });

    });



    // Функция вызывает разные файлы для формирования PDF-документов и передает им информацию о выбранных товарах
    function ajaxPOST(grid, postURL) {

        var sel = w2ui['grid'].getAllChecked(); // Выбранные элементы
        // var sel = w2ui['grid'].getSelection(); // Выбранные элементы
        var sel_arr=[]; // Массив с полученными значениями выбранных элементов
        sel.forEach(function(item, i) {
            sel_arr.push(w2ui['grid'].get(sel[i]));
        });

        w2ui.grid.lock('Формируем PDF...', true);

        $.ajax({
            type: 'POST',
            url: postURL,
            dataType: 'JSON',
            data: {sel_arr:JSON.stringify(sel_arr)},
            success:function(response) {
                if(response.status=='OK'){
                    w2popup.open({
                        title: 'PDF-файл сформирован',
                        body: '<div class="w2ui-centered" style="font-size:1.4em; line-height:1.2em;">'+response.result_message+'</div>',
                        buttons: '<button class="w2ui-btn" onclick="w2popup.close()">Закрыть</button>',
                        showMax: true
                    });
                    }else{
                    w2popup.open({
                        title: '<strong style="color: #CC0814;">Внимание! Ошибка!</strong>',
                        body: '<div class="w2ui-centered" style="font-size:2em; line-height:1.2em;">'+response.result_message+'</div>',
                        buttons: '<button class="w2ui-btn" onclick="w2popup.close()">Закрыть</button>',
                        showMax: true
                    });
                    //alert(response.result_message);
                }
                w2ui.grid.unlock();
            }
        });
    };

    function updateTotal(grid) {
          var sel = grid.records;
          var SUM_total = 0.0;
          var KOL_total = 0;
          var currency_ID = '<?php echo $currency_ID; ?>';
          var user_login = '<?php echo $user_login; ?>';
          var discount_message = '';
          var discount_volume = 10;
          var discount_dop = 0;
          var dop_discount_message ='';
          $('#dopmessages').empty();

          for (var i = 0; i < sel.length; i++) {
              SUM_total += parseInt(String(sel[i].nds_price).replace(/ /g, ''))*parseInt(String(sel[i].orderquantity).replace(/ /g, ''));
              KOL_total += parseInt(String(sel[i].orderquantity).replace(/ /g, ''));
          }

        if (user_login == 'Gallery') {
            discount_volume = 3;
        }

        if (user_login == 'testpriceru' || user_login == 'Gallery') {

                  if (SUM_total > 50000 && currency_ID == 'RUB') {
                     discount_dop = 2;
                     discount_all = discount_dop+discount_volume;
                     dop_discount_message ='Скидка за объем партии: <strong style="color: #CC0814;">'+discount_dop+'%</strong>. Общая скидка: <strong style="color: #CC0814;">'+discount_all+'%</strong>.';
                 }

                 if (40000 < SUM_total && SUM_total < 50000 && currency_ID == 'RUB') {
                    discount_dop = 2;
                    discount_all = discount_dop+discount_volume;
                    var dop_sum = 50000-SUM_total;
                    dop_discount_message = 'Докупите на сумму <strong style="color: #CC0814;">'+dop_sum+' '+currency_ID+'</strong>, чтобы получить скидку <strong style="color: #CC0814;">'+discount_dop+'%</strong>. Общая скидка будет равна <strong style="color: #CC0814;">'+discount_all+'%</strong>.';
                }

                if (SUM_total > 100000 && currency_ID == 'RUB') {
                    discount_dop = 3;
                    discount_all = discount_dop+discount_volume;
                     dop_discount_message ='Скидка за объем партии: <strong style="color: #CC0814;">'+discount_dop+'%</strong>. Общая скидка: <strong style="color: #CC0814;">'+discount_all+'%</strong>.';
                }

                if (80000 < SUM_total && SUM_total < 100000 && currency_ID == 'RUB') {
                    discount_dop = 3;
                    discount_all = discount_dop+discount_volume;
                    var dop_sum = 100000-SUM_total;
                    dop_discount_message = 'Докупите на сумму <strong style="color: #CC0814;">'+dop_sum+' '+currency_ID+'</strong>, чтобы получить скидку <strong style="color: #CC0814;">'+discount_dop+'%</strong>. Общая скидка будет равна <strong style="color: #CC0814;">'+discount_all+'%</strong>.';
                }

                if (currency_ID == 'RUB') {
                    discount_message = 'Ваша СКИДКА на условиях предоплаты: <strong style="color: #CC0814;">'+discount_volume+'%</strong>. ';
                    $('#dopmessages').append('<p>'+discount_message+dop_discount_message+'</p>');
                }

        }

        grid.set('s-1', {orderquantity: KOL_total, sumorder: SUM_total});
    };


	// Функция формированя заявки из выбранных позиций
		function SaveORDER(){
			var sel = w2ui['gridUP'].records;

           sel.forEach(function(item, i) {
           	var new_arr_obj = w2ui['gridUP'].get(sel[i]);
           	delete sel[i].w2ui; // удаляем лишние объекты из массива с данными
           });

			w2popup.close();
			w2ui.grid.lock('Формируем заявку...', true);

           $.ajax({
               type: 'POST',
               url: 'save_PDF_CSV_order.php',
               dataType: 'JSON',
               data: {sel_arr:JSON.stringify(sel)},
               success:function(response) {
                   if(response.status=='OK'){
                       w2popup.open({
                           title: 'Заявка сформирована',
                           body: '<div class="w2ui-centered" style="font-size:1.4em; line-height:1.2em;">'+response.result_message+'</div>',
                           buttons: '<button class="w2ui-btn" onclick="w2popup.close()">Закрыть</button>',
                           showMax: true
                       });
                		}else{
                       w2popup.open({
                           title: '<strong style="color: #CC0814;">Внимание! Ошибка!</strong>',
                           body: '<div class="w2ui-centered" style="font-size:2em; line-height:1.2em;">'+response.result_message+'</div>',
                           buttons: '<button class="w2ui-btn" onclick="w2popup.close()">Закрыть</button>',
                           showMax: true
                       });
                   }
                   w2ui.grid.unlock();
               }
           });

	   };



    // Проверка на поддержку Drug & Drop возможностей браузера
    var isAdvancedUpload = function() {
      var div = document.createElement('div');
      return (('draggable' in div) || ('ondragstart' in div && 'ondrop' in div)) && 'FormData' in window && 'FileReader' in window;
    }();

		$('#grid').on('click', '.w2ui-grid-select-box', function(){
			// Показываем количество выбранных записей в стоке статуса
			w2ui.grid.status('Выбрано записей: '+w2ui.grid.getAllChecked().length);
		});

    </script>

</body>
</html>