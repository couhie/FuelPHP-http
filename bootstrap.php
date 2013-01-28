<?php
Autoloader::add_core_namespace('Http');
Autoloader::add_classes(
	array(
		'Http\\Http' => __DIR__.DS.'classes'.DS.'http.php',
	)
);

\Config::load('http', true);
