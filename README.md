# Pducky

[![DuckDB PHP adapter](assets/banner.png)](#)

This is a basic [DuckDB](https://duckdb.org/) PHP adapter, that executes SQL queries on large data files (CSV, JSON, Parquet).  
Using SQLite database & automated CSV parser.

## Benchmark:

> Tested using a large CSV dataset of 1M rows (**150 MB**).  
> **Note:** No optimizations were applied. The benchmark includes database creation from compressed CSV and query execution.

[![DuckDB PHP adapter](assets/screenshot.png)](#)

| CPU           | Memory        | Disk     | OS                     | Timing     |
| ------------- |:-------------:| --------:| ----------------------:| ----------:|
| i7 (13K)      | 32 Go         | NVMe     | **Windows** 10 Pro x64 | **2.19s**  |
| i7 (8)        | 8 Go          | NVMe     | **Windows** 10 Pro x64 | **3.054s** |
| Xeon (E22)    | 16 Go         | SSD      | **Linux** Debian 11    | **3.58s**  |
| *i3 (3)*      | *8 Go*        | *SSD*    | *Windows 10 Pro x64*   | *30.23s*   |

## Requirements:

* PHP **exec** function
* PHP **SQLite3** extension
* PHP **FFI** extension (Used by Loader)

## Install:

```
composer require jakiboy/pducky
```

## Examples:

### Fetch single value:

```php
$price = (new Pducky\Adapter('data.csv'))->import()->single(
    'SELECT `price` FROM `temp` WHERE `ean` = "4567890123456";'
); // 374.08$
```

### Fetch rows:

```php
$rows = (new Pducky\Adapter('data.csv'))->import()->query(
    'SELECT * FROM `temp` LIMIT 100;'
); // []
```

### Create database:

Create database `data` with table `product` from a compressed CSV file `data.csv.gz`.

```php
(new Pducky\Adapter('data.csv.gz'))->import('data', 'product');
```

### Loader query (FFI):

```php
$rows = (new Pducky\Loader())->connect('data.db')
       ->importCsv('data.csv', 'product')
       ->query('SELECT * FROM product LIMIT 100;'); // []
```

## References:

* [SQL Introduction](https://duckdb.org/docs/stable/sql/introduction)
* [Importing Data](https://duckdb.org/docs/stable/data/overview)

## Todo:

* Support for **XML** datasets
* Format converter (e.g., CSV → JSON)
* CSV header parser (column naming)
* Automated column parser

## Authors:

* [Jakiboy](https://github.com/Jakiboy) (*Initial work*)

## ⭐ Support:

Skip the coffee! If you like the project, a Star would mean a lot.
