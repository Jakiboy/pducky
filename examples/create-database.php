<?php

include 'vendor/autoload.php';

(new Pducky\Adapter('data.csv.gz'))->import('data', 'product');
