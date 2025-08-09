# Pducky

This is a basic PHP [DuckDB](https://duckdb.org/) adapter, that executes SQL queries on large data files (CSV).  
Using SQLite database & automated CSV parser.  

## Benchmark:

Tested using large CSV of 1M rows **1 Go**.  
**Note:** No optimizations applied! The benchmark includes databse creation from CSV & query execution.

![Pducky](assets/screenshot.png)

| CPU           | Memory        | Disk     | OS                     | Timing     |
| ------------- |:-------------:| --------:| ----------------------:| ----------:|
| i7 (13K)      | 32 Go         | SSD NVMe | **Windows** 10 Pro x64 | **2.19s**  |
| Xeon (E22)    | 16 Go         | SSD      | **Linux** Debian 11    | **3.58s**  |
| i3 (3)        | 8 Go          | SSD      | **Windows** 10 Pro x64 | **30.23s** |

## Requirements:

* PHP **exec** function (Adapter)
* PHP **SQLite3** extension (Adapter)
* PHP **FFI** extension (Loader)

## Install:

```
composer require jakiboy/pducky
```

## Examples:

### Fetch single value:

```php
$price = (new Pducky\Adapter('data.csv'))->import()->single(
    'SELECT `price` FROM `temp` WHERE `ean` = "4567890123456";'
); // 540.23$
```

### Fetch rows:

```php
$rows = (new Pducky\Adapter('data.csv'))->import()->query(
    'SELECT * FROM `temp` LIMIT 100;'
); // []
```

### Create database:

Create database "data" with table "product" from compressed file "data.csv.gz".

```php
(new Pducky\Adapter('data.csv.gz'))->import('data', 'product');
```

### Loader query (FFI):

```php
$rows = (new Pducky\Loader())->connect('data.db')
       ->importCsv('data.csv', 'product')
       ->query('SELECT * FROM product LIMIT 100;');
```

## References:

* [SQL Introduction](https://duckdb.org/docs/stable/sql/introduction)
* [Importing Data](https://duckdb.org/docs/stable/data/overview)

## Todo:

* Add support for **XML** and other structured format
* Add structured format converter
* Add header parser

## Authors:

* [Jakiboy](https://github.com/Jakiboy) (*Initial work*)

## ⭐ Support:

Skip the coffee! If you like the project, a Star would mean a lot.
