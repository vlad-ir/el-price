<?php 
	//Инициализируем сессию:
	session_start();

	// Подключаем файл с настройками
	require_once __DIR__ . '/settings.php';

	$post_user 			= filter_input_text($_POST['cron_user']);
	$post_pw 			= filter_input_text($_POST['cron_pw']);

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
	// Запрашиваем логин и пароль из базы данных. Если данные не найдены
	if (!$result = $BD_link->query('SELECT * FROM '.$BD_prefix.'user WHERE login="'.$post_user.'" AND active=1 AND pwd="'.md5($post_pw).'"')->fetch_assoc()) {
		// Закрываем соединение с базой
		$BD_link->close();
		session_destroy();
?>

		<main class="form-signin">
			<form id="user_login" method="POST" action="./">
				<img class="mb-4" src="./images/romgil-logo.png" alt="Ромгиль-Текс" width="172">
				<h1 class="h3 mb-3 fw-normal">Вход</h1>
				<p>Для доступа к электронному прайс-листу введите логин и пароль</p>
				<?php 
				if (!empty($post_user) OR !empty($post_pw)) {
					?>
					<div class="alert alert-danger" role="alert">Ошибка входа! Попробуйте еще раз!</div>
					<?php 
				}
				?>
				<div class="form-floating">
					<input type="text" name="cron_user" class="form-control" id="floatingInput" placeholder="Логин" required>
					<label for="floatingInput">Логин</label>
				</div>
				<div class="form-floating">
					<input type="password" name="cron_pw" class="form-control" id="floatingPassword" placeholder="Пароль" required>
					<label for="floatingPassword">Пароль</label>
				</div>

				<button class="w-100 btn btn-lg btn-primary" type="submit">Войти</button>
				<p class="mt-5 mb-3 text-muted">&copy; 2011–<?php echo date('Y'); ?></p>
			</form>
		</main>


<?php

	}else{
			//Пишем в сессию сегодняшнюю дату и флаг разрешения доступа
		$_SESSION['user_date'] = date("Y-m-d");
		$_SESSION['user_permission'] = 'dostupno';
		$_SESSION['user_login'] = $result['login'];

		if (!check_USER_role($result['login'], 'admin', $BD_link, $BD_prefix)) {
			// Закрываем соединение с базой
			$BD_link->close();
			//Перенаправляем на страницу с прайс-листом если юзер НЕ админ
			echo '<meta http-equiv="refresh" content="0; URL=./price.php" />';
			exit;
		}

?>


					<p><br><br></p>
					<p class="btn_block">        	
						<a href="./price.php" ><button type="button" class="btn btn-primary btn-lg btn-block">Перейти на страницу с прайс-листом</button></a>
					</p>
					<p class="btn_block">        	
						<a href="./data_to_mysql.php" ><button type="button" class="btn btn-primary btn-lg btn-block">Загрузить данные в базу из Excell</button></a>
					</p>
					<p class="btn_block">        	
						<a href="./data_to_mysql_CSV.php" ><button type="button" class="btn btn-primary btn-lg btn-block">Загрузить данные в базу из CSV</button></a>
					</p>
					<p class="btn_block">        	
						<a href="./" ><button type="button" class="btn btn-success btn-block">Выход</button></a>
					</p>
<?php 
	}
?>


	</body>
	</html>
