<?php 

// Подключаем файл с настройками
require_once __DIR__ . '/settings.php';

$status = 'ERROR';

$artikul 	= filter_input_text($_POST['artikul']);
$color 		= filter_input_text($_POST['color']);
$item_name 	= filter_input_text($_POST['item_name']);


// Формируем имя файла для изображений в соответствии с шаблоном
$filename = PhotoFileName($artikul, $color).'_1.jpg';
// Если фотография существует, то формируем шаблон для вывода
if (file_exists($filename)) {
	$files_list = '<div class="col-12 text-center"><p><strong>'.$artikul.' '.$item_name.'</strong><br>'.$color.'</p><div data-slider="chiefslider" class="slider" id="prodslider"><div class="slider__container"><div class="slider__wrapper"><div class="slider__items">';
	$slider_control = '<a href="./" class="slider__control" data-slide="prev"></a><a href="./" class="slider__control" data-slide="next"></a><ol class="slider__indicators">';

// Находим все фотографии для товара в соответствии с шаблоном
	$i = 1;
	while (file_exists(PhotoFileName($artikul, $color).'_'.$i.'.jpg')) {

		$filename = PhotoFileName($artikul, $color).'_'.$i.'.jpg';
		$previewfilename = PreviewPhotoFileName($artikul, $color).'_'.$i.'.jpg';

		$files_list .= '<div class="slider__item"><a data-fancybox="gallery" href="'.$filename.'" data-caption="'.$artikul.' '.$item_name.'<br>Цвет: '.$color.'"><img src="'.$previewfilename.'" width="100%" title="Увеличить" alt="'.$artikul.' '.$item_name.' '.$color.'"></a></div>';

		$m = $i-1;
		$slider_control .= '<li data-slide-to="'.$m.'"></li>';
		$i++;
	}

// Если фотографий больше, чем одна, то выводим галерею (status = 'OK' и скрываем кнопки управления галереей)
// Если фотография одна, то просто отображаем одну фотографию 
	$files_list .= ($m > 0) ? '</div></div></div>'.$slider_control.'</ol></div></div>' : '</div></div></div></div></div>' ;
	$status = ($m > 0) ? 'OK' : 'ONE_PHOTO' ;

} else {
	$files_list = '<div class="col-12 text-center"><p><strong>'.$artikul.' '.$item_name.'</strong><br>'.$color.'</p><img src="./images/no_photo.jpg" width="100%" title="Фото НЕ найдено" alt="Фото не найдено"></div>';
}

echo json_encode(data_result($status, $files_list));

?>
