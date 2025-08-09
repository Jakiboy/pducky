<?php

require_once '../src/Adapter.php';

$price = (new Pducky\Adapter('data.csv'))->import()->single(
	'SELECT `price` FROM `temp` WHERE `ean` = "1000000656814";' # Line: 656815
);

echo "Price: {$price}$";
