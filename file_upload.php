<?php 

// Подключаем файл с настройками
require_once __DIR__ . '/settings.php';

$arr_file_types = ['application/vnd.ms-excel'];

foreach ($_FILES as $key => $value)
{
	if (!(in_array($value['type'], $arr_file_types))) {
		echo json_encode(data_result('ERROR', 'Загрузка файлов этого типа запрещена!'));
		return;
	}

	if (!file_exists('upload')) {
		mkdir('upload', 0777);
	}

	move_uploaded_file($value['tmp_name'], './upload/'. $value['name']);
}

echo json_encode(data_result('OK', 'Файлы загружены.'));

?>