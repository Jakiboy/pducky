<?php

require_once '../src/Adapter.php';

$price = (new Pducky\Adapter('data.csv'))->import()->single(
    'SELECT `price` FROM `temp` WHERE `ean` = "4567890123456";'
);

echo $price;
