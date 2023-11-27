<?php
/**
 * @author     : Jakiboy
 * @version    : 0.0.1-alpha
 * @copyright  : (c) 2023 Jihad Sinnaour <mail@jihadsinnaour.com>
 * @link       : https://github.com/Jakiboy/pducky
 * @license    : MIT
 */

declare(strict_types=1);

namespace Pducky;

/**
 * PHP DuckDB Importer.
 * @see https://duckdb.org/docs/
 */
class Adapter
{
    /**
     * @var string $db
     * @var string $table
     */
    public $db;
    public $table;
    
    /**
     * @var string $file
     * @var array $args
     * @var string $type
     * @var bool $override
     */
    protected $file;
    protected $args;
    protected $type = 'csv';
    protected $override = false;

    /**
     * @const string
     */
    protected const WINBIN = '/bin/win/duckdb.exe';
    protected const LINBIN = '/bin/lin/duckdb';
    protected const ATTACH = "ATTACH '{db}.db' AS db (TYPE SQLITE);";
    protected const DETACH = "DETACH db;";
    protected const CREATE = "CREATE TABLE db.{table} AS SELECT * FROM {func}('{file}', {args});";
    protected const SELECT = "SELECT * FROM {table} LIMIT 100;";

    /**
     * @const array
     */
    protected const CONFIG = [
        'ALL_VARCHAR' => true
    ];

    /**
     * @param string $file
     * @param array $args
     */
    public function __construct(string $file, array $args = [])
    {
        if ( !$this->hasFile($file, false) ) {
            throw new \Exception('Data file not found');

        } elseif ( !$this->hasBin() ) {
            throw new \Exception('Missing DuckDB binary');

        } elseif ( !$this->hasSQLite() ) {
            throw new \Exception('Missing SQLite extension');
        }

        $this->file = $file;
        $this->args = $args;
    }

    /**
     * Import data file,
     * Supports (CSV, JSON).
     * 
     * @param string $db
     * @param string $table
     * @return object
     */
    public function import(?string $db = null, string $table = 'temp') : self
    {
        $this->db = ($db) ? $db : preg_replace('/\.[^.]+$/', '', basename($this->file));

        $this->table = $table;

        if ( $this->override ) {
            @unlink("{$this->db}.db");
            @unlink('.shell.wal');
            @unlink('.shell');
        }

        $this->args = array_merge(self::CONFIG, $this->args);
        $args = '';
        foreach ( $this->args as $key => $value ) {
            $args .= "{$key} = ";
            if ( $value === true ) {
                $args .= 'true';

            } elseif ( $value === false ) {
                $args .= 'false';

            } else {
                $args .= $value;
            }
            $args .= ',';
        }

        $query  = self::ATTACH . PHP_EOL;
        $query .= self::CREATE . PHP_EOL;
        $query .= self::DETACH;

        $query = str_replace(
            ['{db}', '{table}', '{file}', '{args}'],
            [$this->db, $this->table, $this->file, rtrim($args, ',')],
            $query
        );

        if ( $this->type == 'csv' ) {
            $query = str_replace('{func}', 'read_csv_auto', $query);

        } elseif ( $this->type == 'json' ) {
            $query = str_replace('{func}', 'read_json_auto', $query);

        } else {
            throw new \Exception('Unknown file type');
        }

        $sql = file_put_contents('tmp.sql', $query);
        $cmd = $this->getBin() . ' .shell ".read tmp.sql"';
        $this->run($cmd);
        @unlink("tmp.sql");

        return $this;
    }

    /**
     * Wide query.
     *
     * @param string $db
     * @return mixed
     */
    public function query(?string $sql = null)
    {
        $sql = $this->formatQuery($sql);
        if ( ($r = $this->getDb()->query($sql)) ) {
            $w = [];
            while($d = $r->fetchArray(SQLITE3_ASSOC)) {
                $w[] = $d;
            }
            return $w;
        }
        return $r;
    }

    /**
     * Single query.
     *
     * @param string $sql
     * @return mixed
     */
    public function single(?string $sql = null)
    {
        $sql = $this->formatQuery($sql);
        return $this->getDb()->querySingle($sql);
    }

    /**
     * Get database.
     *
     * @return object
     */
    public function getDb() : object
    {
        return new \SQLite3("{$this->db}.db");
    }

    /**
     * Override existing database.
     *
     * @return object
     */
    public function override() : self
    {
        $this->override = true;
        return $this;
    }

    /**
     * Import data as JSON.
     *
     * @return object
     */
    public function asJson() : self
    {
        $this->type = 'json';
        return $this;
    }

    /**
     * Import data as CSV.
     *
     * @return object
     */
    public function asCsv() : self
    {
        $this->type = 'csv';
        return $this;
    }

    /**
     * Run command.
     * 
     * @param string $command
     * @return void
     */
    protected function run(string $command) : void
    {
        @exec($command);
    }

    /**
     * Get DuckDB binary.
     *
     * @return object
     * @throws Exception
     */
    protected function getBin() : string
    {
        if ( $this->isWin() ) {
            return $this->formatPath(__DIR__ . '/' . self::WINBIN);

        } elseif ( $this->isLin() ) {
            return $this->formatPath(__DIR__ . '/' . self::LINBIN);
        }
        throw new \Exception('Unrecognized platform');
    }

    /**
     * Check if Windows OS.
     *
     * @return bool
     */
    protected function isWin() : bool
    {
        return (strtolower(PHP_OS_FAMILY) === 'windows');
    }

    /**
     * Check if Linux OS.
     *
     * @return bool
     */
    protected function isLin() : bool
    {
        return (strtolower(PHP_OS_FAMILY) === 'linux');
    }

    /**
     * Check if DuckDB binary exists.
     *
     * @return bool
     */
    protected function hasBin() : bool
    {
        return ($this->hasFile(self::WINBIN) && $this->hasFile(self::LINBIN));
    }
    
    /**
     * Check if SQLite extension exists.
     *
     * @return bool
     */
    protected function hasSQLite() : bool
    {
        return class_exists('SQLite3');
    }

    /**
     * Check if file exists.
     *
     * @param string $file
     * @param bool $root
     * @return bool
     */
    protected function hasFile(string $file, bool $root = true) : bool
    {
        $dir = null;
        if ( $root ) {
            $dir = __DIR__;
        }
        return file_exists(
            $this->formatPath("{$dir}/{$file}")
        );
    }

    /**
     * Format path.
     *
     * @param string $path
     * @return string
     */
	protected function formatPath(string $path) : string
	{
	    $path = str_replace('\\', '/', $path);
	    $path = preg_replace('|(?<=.)/+|', '/', $path);
        $path = ltrim($path, '/');
	    if ( substr($path, 1, 1) === ':' ) {
	        $path = ucfirst($path);
	    }
	    return $path;
	}

    /**
     * Format query.
     *
     * @param string $sql
     * @return string
     */
	protected function formatQuery(?string $sql = null) : string
	{
        if ( !$sql ) {
            $sql = self::SELECT;
        }
        return str_replace('{table}', $this->table, $sql);
	}
}
