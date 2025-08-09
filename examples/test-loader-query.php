<?php

require_once '../src/Adapter.php';
require_once '../src/Loader.php';

$rows = (new Pducky\Loader())->connect('data.db')
	   ->importCsv('data.csv', 'product')
	   ->query('SELECT * FROM product LIMIT 5');

print_r($rows);
