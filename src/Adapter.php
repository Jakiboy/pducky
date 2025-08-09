<?php
/**
 * @author     : Jakiboy
 * @version    : 0.2.x
 * @copyright  : (c) 2025 Jihad Sinnaour <me@jihadsinnaour.com>
 * @link       : https://github.com/Jakiboy/pducky
 * @license    : MIT
 */

declare(strict_types=1);

namespace Pducky;

/**
 * PHP DuckDB Importer - Enhanced version.
 * Supports CSV, JSON, and Parquet file imports with improved error handling.
 * @see https://duckdb.org/docs/
 */
class Adapter
{
	/**
	 * @var string $db Database name
	 * @var string $table Table name
	 * @var bool $debug Enable debug mode
	 * @var array $lastError Last error information
	 */
	public $db;
	public $table;
	public $debug = false;
	public $lastError = [];
	
	/**
	 * @var string $file Data file path
	 * @var array $args Import arguments
	 * @var string $type File type (csv|json|parquet)
	 * @var bool $override Override existing database
	 * @var resource|null $dbConnection Database connection
	 */
	protected $file;
	protected $args;
	protected $type = 'csv';
	protected $override = false;
	protected $dbConnection = null;

	/**
	 * @const string Binary paths and SQL templates
	 */
	protected const WINBIN = '/bin/win/duckdb.exe';
	protected const LINBIN = '/bin/lin/duckdb';
	protected const ATTACH = "ATTACH '{db}.db' AS db (TYPE SQLITE);";
	protected const DETACH = "DETACH db;";
	protected const DROP = "DROP TABLE IF EXISTS db.{table};";
	protected const CREATE = "CREATE TABLE db.{table} AS SELECT * FROM {func}('{file}', {args});";
	protected const SELECT = "SELECT * FROM {table} LIMIT 100;";

	/**
	 * @const array Default configuration options
	 */
	protected const CONFIG = [
		'ALL_VARCHAR' => true,
		'HEADER' => true,
		'AUTO_DETECT' => true
	];

	/**
	 * @const array Supported file types and their DuckDB functions
	 */
	protected const SUPPORTED_TYPES = [
		'csv' => 'read_csv_auto',
		'json' => 'read_json_auto',
		'parquet' => 'read_parquet'
	];

	/**
	 * Constructor - Initialize the adapter with a data file.
	 * 
	 * @param string $file Path to the data file
	 * @param array $args Additional import arguments
	 * @throws \Exception If file not found, binary missing, or SQLite unavailable
	 */
	public function __construct(string $file, array $args = [])
	{
		try {
			if (!$this->hasFile($file, false)) {
				throw new \Exception("Data file not found: {$file}");
			}

			if (!$this->hasBin()) {
				throw new \Exception('Missing DuckDB binary files');
			}

			if (!$this->hasSQLite()) {
				throw new \Exception('SQLite3 extension is not available');
			}

			$this->file = $this->formatPath($file);
			$this->args = $args;
			$this->detectFileType();
			
			if ($this->debug) {
				$this->log("Adapter initialized with file: {$this->file}, type: {$this->type}");
			}
			
		} catch (\Exception $e) {
			$this->setError('INIT_ERROR', $e->getMessage());
			throw $e;
		}
	}

	/**
	 * Import data file into database.
	 * Supports CSV, JSON, and Parquet files with enhanced error handling.
	 * 
	 * @param string|null $db Database name (auto-generated from filename if null)
	 * @param string $table Table name (default: 'temp')
	 * @return self Fluent interface
	 * @throws \Exception If import fails
	 */
	public function import(?string $db = null, string $table = 'temp'): self
	{
		try {
			$this->db = $db ?: preg_replace('/\.[^.]+$/', '', basename($this->file));
			$this->table = $this->sanitizeTableName($table);

			if ($this->override) {
				$this->cleanupFiles();
			}

			$query = $this->buildImportQuery();
			
			if ($this->debug) {
				$this->log("Generated SQL Query:\n{$query}");
			}

			$this->executeQuery($query);
			
			if ($this->debug) {
				$this->log("Import completed successfully");
			}

			return $this;
			
		} catch (\Exception $e) {
			$this->setError('IMPORT_ERROR', $e->getMessage());
			throw new \Exception("Import failed: " . $e->getMessage());
		}
	}

	/**
	 * Execute a wide query and return all results.
	 *
	 * @param string|null $sql SQL query (uses default SELECT if null)
	 * @return array|false Query results or false on failure
	 */
	public function query(?string $sql = null)
	{
		try {
			$sql = $this->formatQuery($sql);
			$db = $this->getDb();
			
			if ($this->debug) {
				$this->log("Executing query: {$sql}");
			}
			
			$result = $db->query($sql);
			
			if ($result === false) {
				$error = $db->lastErrorMsg();
				$this->setError('QUERY_ERROR', $error);
				return false;
			}
			
			$data = [];
			while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
				$data[] = $row;
			}
			
			$db->close();
			return $data;
			
		} catch (\Exception $e) {
			$this->setError('QUERY_EXCEPTION', $e->getMessage());
			return false;
		}
	}

	/**
	 * Execute a single value query.
	 *
	 * @param string|null $sql SQL query
	 * @return mixed Single value result or false on failure
	 */
	public function single(?string $sql = null)
	{
		try {
			$sql = $this->formatQuery($sql);
			$db = $this->getDb();
			
			if ($this->debug) {
				$this->log("Executing single query: {$sql}");
			}
			
			$result = $db->querySingle($sql);
			$db->close();
			
			return $result;
			
		} catch (\Exception $e) {
			$this->setError('SINGLE_QUERY_EXCEPTION', $e->getMessage());
			return false;
		}
	}

	/**
	 * Get database connection.
	 *
	 * @return \SQLite3 Database connection
	 * @throws \Exception If connection fails
	 */
	public function getDb(): \SQLite3
	{
		try {
			$dbPath = "{$this->db}.db";
			
			if (!file_exists($dbPath)) {
				throw new \Exception("Database file not found: {$dbPath}");
			}
			
			$connection = new \SQLite3($dbPath);
			
			if (!$connection) {
				throw new \Exception("Failed to connect to database: {$dbPath}");
			}
			
			return $connection;
			
		} catch (\Exception $e) {
			$this->setError('DB_CONNECTION_ERROR', $e->getMessage());
			throw $e;
		}
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
	 * Import data as Parquet.
	 *
	 * @return self Fluent interface
	 */
	public function asParquet(): self
	{
		$this->type = 'parquet';
		return $this;
	}

	/**
	 * Enable debug mode.
	 *
	 * @return self Fluent interface
	 */
	public function debug(): self
	{
		$this->debug = true;
		return $this;
	}

	/**
	 * Get last error information.
	 *
	 * @return array Error details
	 */
	public function getLastError(): array
	{
		return $this->lastError;
	}

	/**
	 * Check if there was an error.
	 *
	 * @return bool True if error exists
	 */
	public function hasError(): bool
	{
		return !empty($this->lastError);
	}

	/**
	 * Build the complete import query.
	 *
	 * @return string The SQL query
	 * @throws \Exception If unsupported file type
	 */
	protected function buildImportQuery(): string
	{
		$this->args = array_merge(self::CONFIG, $this->args);
		$args = $this->formatArgs($this->args);
		
		$query = self::ATTACH . PHP_EOL;
		$query .= self::DROP . PHP_EOL;
		$query .= self::CREATE . PHP_EOL;
		$query .= self::DETACH;

		$query = str_replace(
			['{db}', '{table}', '{file}', '{args}'],
			[$this->db, $this->table, $this->file, $args],
			$query
		);

		if (!isset(self::SUPPORTED_TYPES[$this->type])) {
			throw new \Exception("Unsupported file type: {$this->type}");
		}

		return str_replace('{func}', self::SUPPORTED_TYPES[$this->type], $query);
	}

	/**
	 * Format arguments for DuckDB query.
	 *
	 * @param array $args Arguments array
	 * @return string Formatted arguments string
	 */
	protected function formatArgs(array $args): string
	{
		$formatted = '';
		foreach ($args as $key => $value) {
			$formatted .= "{$key} = ";
			if ($value === true) {
				$formatted .= 'true';
			} elseif ($value === false) {
				$formatted .= 'false';
			} else {
				$formatted .= is_string($value) ? "'{$value}'" : $value;
			}
			$formatted .= ',';
		}
		return rtrim($formatted, ',');
	}

	/**
	 * Execute the import query.
	 *
	 * @param string $query SQL query to execute
	 * @throws \Exception If execution fails
	 */
	protected function executeQuery(string $query): void
	{
		$tmpFile = 'tmp_' . uniqid() . '.sql';
		
		try {
			if (file_put_contents($tmpFile, $query) === false) {
				throw new \Exception('Failed to write temporary SQL file');
			}

			$cmd = $this->getBin() . ' .shell ".read ' . $tmpFile . '"';
			$output = [];
			$returnCode = 0;
			
			exec($cmd . ' 2>&1', $output, $returnCode);
			
			if ($returnCode !== 0) {
				throw new \Exception('DuckDB execution failed: ' . implode("\n", $output));
			}
			
		} finally {
			@unlink($tmpFile);
		}
	}

	/**
	 * Detect file type from extension.
	 */
	protected function detectFileType(): void
	{
		$extension = strtolower(pathinfo($this->file, PATHINFO_EXTENSION));
		
		// Handle compressed files
		if ($extension === 'gz') {
			$basename = pathinfo($this->file, PATHINFO_FILENAME);
			$extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
		}
		
		if (isset(self::SUPPORTED_TYPES[$extension])) {
			$this->type = $extension;
		}
	}

	/**
	 * Sanitize table name to prevent SQL injection.
	 *
	 * @param string $tableName Table name to sanitize
	 * @return string Sanitized table name
	 */
	protected function sanitizeTableName(string $tableName): string
	{
		return preg_replace('/[^a-zA-Z0-9_]/', '_', $tableName);
	}

	/**
	 * Clean up database and temporary files.
	 */
	protected function cleanupFiles(): void
	{
		$files = [
			"{$this->db}.db",
			"{$this->db}.db-wal",
			"{$this->db}.db-shm",
			'.shell.wal',
			'.shell'
		];
		
		foreach ($files as $file) {
			@unlink($file);
		}
	}

	/**
	 * Set error information.
	 *
	 * @param string $code Error code
	 * @param string $message Error message
	 */
	protected function setError(string $code, string $message): void
	{
		$this->lastError = [
			'code' => $code,
			'message' => $message,
			'timestamp' => date('Y-m-d H:i:s')
		];
		
		if ($this->debug) {
			$this->log("ERROR [{$code}]: {$message}");
		}
	}

	/**
	 * Log debug messages.
	 *
	 * @param string $message Message to log
	 */
	protected function log(string $message): void
	{
		if ($this->debug) {
			error_log("[PDucky Debug] " . $message);
		}
	}

	/**
	 * Import data as CSV.
	 *
	 * @return self Fluent interface
	 */
	public function asCsv(): self
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
