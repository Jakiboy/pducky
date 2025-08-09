<?php

include 'vendor/autoload.php';

$rows = (new Pducky\Adapter('data.csv'))->import()->query(
    'SELECT * FROM `temp` LIMIT 5;'
);

print_r($rows);
