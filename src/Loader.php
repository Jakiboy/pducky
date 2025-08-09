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

use FFI;
use FFI\CData;

/**
 * PHP DuckDB C/C++ Loader with FFI support.
 * Provides direct C library integration for DuckDB operations.
 * @see https://duckdb.org/docs/
 */
class Loader
{
	/**
	 * @var FFI|null $ffi FFI instance
	 * @var CData|null $database DuckDB database handle
	 * @var CData|null $connection DuckDB connection handle
	 * @var bool $connected Connection status
	 * @var string $databasePath Database file path
	 * @var bool $debug Debug mode
	 * @var array $lastError Last error information
	 */
	private ?FFI $ffi = null;
	private ?CData $database = null;
	private ?CData $connection = null;
	private bool $connected = false;
	private string $databasePath = '';
	private bool $debug = false;
	private array $lastError = [];

	/**
	 * @const string Default library paths
	 */
	private const WIN_LIB = '/bin/win/duckdb.dll';
	private const LIN_LIB = '/bin/lin/libduckdb.so';
	private const HEADERS = '/lib/duckdb.h';

	/**
	 * Constructor - Initialize FFI loader.
	 * 
	 * @param string|null $databasePath Database file path (optional)
	 * @param bool $autoConnect Auto-connect to database
	 * @throws \Exception If FFI not available or initialization fails
	 */
	public function __construct(?string $databasePath = null, bool $autoConnect = true)
	{
		try {
			$this->checkFFISupport();
			$this->initializeFFI();
			
			if ($databasePath && $autoConnect) {
				$this->connect($databasePath);
			}
			
			if ($this->debug) {
				$this->log("Loader initialized successfully");
			}
			
		} catch (\Exception $e) {
			$this->setError('INIT_ERROR', $e->getMessage());
			throw $e;
		}
	}

	/**
	 * Destructor - Clean up resources.
	 */
	public function __destruct()
	{
		$this->disconnect();
	}

	/**
	 * Connect to a DuckDB database.
	 * 
	 * @param string $databasePath Path to database file
	 * @return self Fluent interface
	 * @throws \Exception If connection fails
	 */
	public function connect(string $databasePath): self
	{
		try {
			if ($this->connected) {
				$this->disconnect();
			}

			$this->databasePath = $databasePath;
			
			// Create database and connection handles
			$this->database = $this->ffi->new('duckdb_database');
			$this->connection = $this->ffi->new('duckdb_connection');

			// Open database
			$error = $this->ffi->duckdb_open($databasePath, FFI::addr($this->database));
			if ($error !== 0) {
				throw new \Exception("Failed to open database: {$databasePath}");
			}

			// Connect to database
			$error = $this->ffi->duckdb_connect($this->database, FFI::addr($this->connection));
			if ($error !== 0) {
				throw new \Exception("Failed to connect to database");
			}

			$this->connected = true;
			
			if ($this->debug) {
				$this->log("Connected to database: {$databasePath}");
			}

			return $this;
			
		} catch (\Exception $e) {
			$this->setError('CONNECTION_ERROR', $e->getMessage());
			throw $e;
		}
	}

	/**
	 * Disconnect from database.
	 * 
	 * @return self Fluent interface
	 */
	public function disconnect(): self
	{
		if ($this->connected && $this->ffi) {
			try {
				if ($this->connection) {
					$this->ffi->duckdb_disconnect(FFI::addr($this->connection));
					FFI::free($this->connection);
					$this->connection = null;
				}

				if ($this->database) {
					$this->ffi->duckdb_close(FFI::addr($this->database));
					FFI::free($this->database);
					$this->database = null;
				}

				$this->connected = false;
				
				if ($this->debug) {
					$this->log("Disconnected from database");
				}
				
			} catch (\Exception $e) {
				$this->setError('DISCONNECT_ERROR', $e->getMessage());
			}
		}

		return $this;
	}

	/**
	 * Execute a SQL query and return results.
	 * 
	 * @param string $sql SQL query to execute
	 * @return array Query results
	 * @throws \Exception If query execution fails
	 */
	public function query(string $sql): array
	{
		if (!$this->connected) {
			throw new \Exception('Not connected to database');
		}

		try {
			$result = $this->ffi->new('duckdb_result');

			if ($this->debug) {
				$this->log("Executing query: {$sql}");
			}

			$error = $this->ffi->duckdb_query($this->connection, $sql, FFI::addr($result));
			
			if ($error !== 0) {
				$errorMessage = 'Query execution failed';
				if (isset($result->error_message)) {
					$errorMessage = FFI::string($result->error_message);
				}
				
				$this->ffi->duckdb_destroy_result(FFI::addr($result));
				throw new \Exception($errorMessage);
			}

			$data = $this->extractResults($result);
			$this->ffi->duckdb_destroy_result(FFI::addr($result));

			if ($this->debug) {
				$this->log("Query executed successfully, returned " . count($data) . " rows");
			}

			return $data;
			
		} catch (\Exception $e) {
			$this->setError('QUERY_ERROR', $e->getMessage());
			throw $e;
		}
	}

	/**
	 * Execute a query and return single value.
	 * 
	 * @param string $sql SQL query to execute
	 * @return mixed Single value result
	 * @throws \Exception If query execution fails
	 */
	public function querySingle(string $sql)
	{
		$results = $this->query($sql);
		
		if (empty($results)) {
			return null;
		}

		$firstRow = reset($results);
		return is_array($firstRow) ? reset($firstRow) : $firstRow;
	}

	/**
	 * Import CSV file into database table.
	 * 
	 * @param string $csvFile Path to CSV file
	 * @param string $tableName Target table name
	 * @param array $options Import options
	 * @return self Fluent interface
	 * @throws \Exception If import fails
	 */
	public function importCsv(string $csvFile, string $tableName, array $options = []): self
	{
		$defaultOptions = [
			'header' => true,
			'auto_detect' => true,
			'all_varchar' => false
		];
		
		$options = array_merge($defaultOptions, $options);
		$optionsStr = $this->formatImportOptions($options);
		
		$sql = "CREATE OR REPLACE TABLE {$tableName} AS SELECT * FROM read_csv_auto('{$csvFile}', {$optionsStr})";
		
		$this->query($sql);
		
		if ($this->debug) {
			$this->log("CSV file imported: {$csvFile} -> {$tableName}");
		}
		
		return $this;
	}

	/**
	 * Import JSON file into database table.
	 * 
	 * @param string $jsonFile Path to JSON file
	 * @param string $tableName Target table name
	 * @param array $options Import options
	 * @return self Fluent interface
	 * @throws \Exception If import fails
	 */
	public function importJson(string $jsonFile, string $tableName, array $options = []): self
	{
		$defaultOptions = [
			'auto_detect' => true,
			'format' => 'auto'
		];
		
		$options = array_merge($defaultOptions, $options);
		$optionsStr = $this->formatImportOptions($options);
		
		$sql = "CREATE OR REPLACE TABLE {$tableName} AS SELECT * FROM read_json_auto('{$jsonFile}', {$optionsStr})";
		
		$this->query($sql);
		
		if ($this->debug) {
			$this->log("JSON file imported: {$jsonFile} -> {$tableName}");
		}
		
		return $this;
	}

	/**
	 * Import Parquet file into database table.
	 * 
	 * @param string $parquetFile Path to Parquet file
	 * @param string $tableName Target table name
	 * @return self Fluent interface
	 * @throws \Exception If import fails
	 */
	public function importParquet(string $parquetFile, string $tableName): self
	{
		$sql = "CREATE OR REPLACE TABLE {$tableName} AS SELECT * FROM read_parquet('{$parquetFile}')";
		
		$this->query($sql);
		
		if ($this->debug) {
			$this->log("Parquet file imported: {$parquetFile} -> {$tableName}");
		}
		
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
	 * Check if connected to database.
	 * 
	 * @return bool Connection status
	 */
	public function isConnected(): bool
	{
		return $this->connected;
	}

	/**
	 * Get database path.
	 * 
	 * @return string Database path
	 */
	public function getDatabasePath(): string
	{
		return $this->databasePath;
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
	 * Get table information.
	 * 
	 * @param string $tableName Table name
	 * @return array Table schema information
	 */
	public function getTableInfo(string $tableName): array
	{
		return $this->query("PRAGMA table_info('{$tableName}')");
	}

	/**
	 * List all tables in database.
	 * 
	 * @return array List of table names
	 */
	public function listTables(): array
	{
		$results = $this->query("SELECT name FROM sqlite_master WHERE type='table'");
		return array_column($results, 'name');
	}

	/**
	 * Check FFI support.
	 * 
	 * @throws \Exception If FFI not supported
	 */
	private function checkFFISupport(): void
	{
		if (!extension_loaded('ffi')) {
			throw new \Exception('FFI extension is not loaded');
		}

		if (!class_exists('FFI')) {
			throw new \Exception('FFI class is not available');
		}
	}

	/**
	 * Initialize FFI with DuckDB library.
	 * 
	 * @throws \Exception If initialization fails
	 */
	private function initializeFFI(): void
	{
		$libPath = $this->getLibraryPath();
		$headerPath = $this->getHeaderPath();

		if (!file_exists($libPath)) {
			throw new \Exception("DuckDB library not found: {$libPath}");
		}

		if (!file_exists($headerPath)) {
			throw new \Exception("DuckDB headers not found: {$headerPath}");
		}

		$headers = file_get_contents($headerPath);
		if ($headers === false) {
			throw new \Exception("Failed to read headers: {$headerPath}");
		}

		$this->ffi = FFI::cdef($headers, $libPath);
		
		if ($this->debug) {
			$this->log("FFI initialized with library: {$libPath}");
		}
	}

	/**
	 * Get platform-specific library path.
	 * 
	 * @return string Library path
	 * @throws \Exception If platform not supported
	 */
	private function getLibraryPath(): string
	{
		$baseDir = __DIR__;
		
		if (PHP_OS_FAMILY === 'Windows') {
			return $baseDir . self::WIN_LIB;
		} elseif (PHP_OS_FAMILY === 'Linux') {
			return $baseDir . self::LIN_LIB;
		}
		
		throw new \Exception('Unsupported platform: ' . PHP_OS_FAMILY);
	}

	/**
	 * Get header file path.
	 * 
	 * @return string Header path
	 */
	private function getHeaderPath(): string
	{
		return __DIR__ . self::HEADERS;
	}

	/**
	 * Extract results from DuckDB result structure.
	 * 
	 * @param CData $result DuckDB result structure
	 * @return array Extracted data
	 */
	private function extractResults(CData $result): array
	{
		$data = [];
		$columns = [];

		// Get column count and row count
		$columnCount = $this->ffi->duckdb_column_count(FFI::addr($result));
		$rowCount = $this->ffi->duckdb_row_count(FFI::addr($result));

		// Extract column names
		for ($col = 0; $col < $columnCount; $col++) {
			$columnName = $this->ffi->duckdb_column_name(FFI::addr($result), $col);
			$columns[] = is_string($columnName) ? $columnName : FFI::string($columnName);
		}

		// Extract data rows
		for ($row = 0; $row < $rowCount; $row++) {
			$rowData = [];
			for ($col = 0; $col < $columnCount; $col++) {
				$value = $this->ffi->duckdb_value_varchar(FFI::addr($result), $col, $row);
				$rowData[$columns[$col]] = is_string($value) ? $value : FFI::string($value);
				if (!is_string($value)) {
					$this->ffi->duckdb_free($value);
				}
			}
			$data[] = $rowData;
		}

		return $data;
	}

	/**
	 * Format import options for SQL query.
	 * 
	 * @param array $options Options array
	 * @return string Formatted options string
	 */
	private function formatImportOptions(array $options): string
	{
		$formatted = [];
		
		foreach ($options as $key => $value) {
			if (is_bool($value)) {
				$formatted[] = "{$key} = " . ($value ? 'true' : 'false');
			} elseif (is_string($value)) {
				$formatted[] = "{$key} = '{$value}'";
			} else {
				$formatted[] = "{$key} = {$value}";
			}
		}

		return implode(', ', $formatted);
	}

	/**
	 * Set error information.
	 * 
	 * @param string $code Error code
	 * @param string $message Error message
	 */
	private function setError(string $code, string $message): void
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
	private function log(string $message): void
	{
		if ($this->debug) {
			error_log("[PDucky Loader] " . $message);
		}
	}
}
