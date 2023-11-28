# Pducky

This is a basic PHP [DuckDB](https://duckdb.org/) adapter, that executes SQL queries on large data files (CSV).  
Using SQLite database & automated CSV parser.  

*-Its actualy a "POC" alpha version, Wokring in progress on a built-in PHP API for DuckDB-*

## Benchmark:

Tested using large CSV of 1M rows **1 Go**.  
**Note:** No optimizations applied! The benchmark includes databse creation from CSV & query execution.

![Pducky](./.assets/screenshot.png)

| CPU           | Memory        | Disk     | OS                     | Timing     |
| ------------- |:-------------:| --------:| ----------------------:| ----------:|
| i7 (13K)      | 32 Go         | SSD NVMe | **Windows** 10 Pro x64 | **2.19s**  |
| Xeon (E22)    | 16 Go         | SSD      | **Linux** Debian 11    | **3.58s**  |
| i3 (3)        | 8 Go          | SSD      | **Windows** 10 Pro x64 | **30.23s** |

## Requirements:

* PHP **exec** function
* PHP **SQLite3** extension

## Install:

```
composer require jakiboy/pducky
```

## Example:

### Fetch single value:

```php

$price = (new Pducky\Adapter('data.csv'))->import()->single(
    'SELECT `price` FROM `temp` WHERE `ean` = "0000123456789";'
);

echo $price; // 540.23$

```

### Fetch rows:

```php

$rows = (new Pducky\Adapter('data.csv'))->import()->query(
    'SELECT * FROM `temp` LIMIT 100;'
);

echo $rows; // []

```

### Create database:

```php

 (new Pducky\Adapter('data.csv.gz'))->import('db', 'product');

```

## Todo:

* Add support for **XML** and other structured format
* Add support for PHP **FFI** extension (*Optional*)
* Add structured format converter
* Add header parser

## Authors:

* [Jakiboy](https://github.com/Jakiboy) (*Initial work*)

## ⭐ Support:

Please give it a Star if you like the project.
