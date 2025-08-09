<?php

require_once '../src/Adapter.php';

(new Pducky\Adapter('data.csv.gz'))->import('data', 'product');
