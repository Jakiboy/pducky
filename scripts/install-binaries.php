<?php
/**
 * Composer post-install script to download and extract DuckDB binaries
 * 
 * @author Jakiboy
 * @version 0.2.0
 */

declare(strict_types=1);

class BinaryInstaller
{
	private const GITHUB_API_URL = 'https://api.github.com/repos/jakiboy/pducky/releases/latest';
	
	private string $binDir;
	private string $tempDir;
	
	public function __construct()
	{
		// Detect if we're running from vendor directory or main package
		$baseDir = $this->detectBaseDirectory();
		$this->binDir = $baseDir . '/src/bin';
		$this->tempDir = $baseDir . '/temp';
	}
	
	private function detectBaseDirectory(): string
	{
		$currentDir = __DIR__;
		
		// Check if we're in vendor directory
		if (strpos($currentDir, 'vendor/jakiboy/pducky') !== false) {
			// We're installed as a dependency
			// Find the vendor directory and go up to project root
			$vendorPos = strpos($currentDir, 'vendor/jakiboy/pducky');
			return substr($currentDir, 0, $vendorPos) . 'vendor/jakiboy/pducky';
		}
		
		// We're in the main package directory
		return dirname(__DIR__);
	}
	
	public function install(): void
	{
		echo "Installing DuckDB binaries...\n";
		
		try {
			// Create directories if they don't exist
			$this->createDirectories();
			
			// Check if binaries already exist
			if ($this->binariesExist()) {
				echo "✅ DuckDB binaries already installed.\n";
				return;
			}
			
			// Get latest release info
			$releaseInfo = $this->getLatestRelease();
			
			// Find bin.zip asset
			$downloadUrl = $this->findBinZipAsset($releaseInfo);
			
			// Download bin.zip
			$zipPath = $this->downloadBinZip($downloadUrl);
			
			// Extract to src/bin
			$this->extractBinZip($zipPath);
			
			// Cleanup
			$this->cleanup($zipPath);
			
			echo "✅ DuckDB binaries installed successfully!\n";
			
		} catch (Exception $e) {
			echo "❌ Error installing binaries: " . $e->getMessage() . "\n";
			echo "💡 You can manually download bin.zip from: https://github.com/jakiboy/pducky/releases\n";
		}
	}
	
	private function createDirectories(): void
	{
		if (!is_dir($this->binDir)) {
			mkdir($this->binDir, 0755, true);
		}
		
		if (!is_dir($this->tempDir)) {
			mkdir($this->tempDir, 0755, true);
		}
	}
	
	private function binariesExist(): bool
	{
		$winPath = $this->binDir . '/win/duckdb.exe';
		$linPath = $this->binDir . '/lin/duckdb';
		
		return file_exists($winPath) || file_exists($linPath);
	}
	
	private function getLatestRelease(): array
	{
		echo "Fetching latest release info...\n";
		
		$context = stream_context_create([
			'http' => [
				'method' => 'GET',
				'header' => [
					'User-Agent: pducky-installer',
					'Accept: application/vnd.github.v3+json'
				],
				'timeout' => 30
			]
		]);
		
		$response = @file_get_contents(self::GITHUB_API_URL, false, $context);
		
		if ($response === false) {
			throw new Exception('Failed to fetch release information from GitHub API');
		}
		
		$data = json_decode($response, true);
		
		if (!$data || !isset($data['assets'])) {
			throw new Exception('Invalid release data received from GitHub API');
		}
		
		return $data;
	}
	
	private function findBinZipAsset(array $releaseInfo): string
	{
		foreach ($releaseInfo['assets'] as $asset) {
			if ($asset['name'] === 'bin.zip') {
				return $asset['browser_download_url'];
			}
		}
		
		throw new Exception('bin.zip not found in latest release assets');
	}
	
	private function downloadBinZip(string $url): string
	{
		echo "⬇️  Downloading bin.zip...\n";
		
		$zipPath = $this->tempDir . '/bin.zip';
		
		$context = stream_context_create([
			'http' => [
				'method' => 'GET',
				'header' => [
					'User-Agent: pducky-installer'
				],
				'timeout' => 300 // 5 minutes for large files
			]
		]);
		
		$data = @file_get_contents($url, false, $context);
		
		if ($data === false) {
			throw new Exception('Failed to download bin.zip from: ' . $url);
		}
		
		if (file_put_contents($zipPath, $data) === false) {
			throw new Exception('Failed to save bin.zip to: ' . $zipPath);
		}
		
		echo "Downloaded bin.zip (" . $this->formatBytes(strlen($data)) . ")\n";
		
		return $zipPath;
	}
	
	private function extractBinZip(string $zipPath): void
	{
		echo "Extracting bin.zip...\n";
		
		if (!class_exists('ZipArchive')) {
			throw new Exception('ZipArchive extension is required to extract bin.zip');
		}
		
		$zip = new ZipArchive();
		$result = $zip->open($zipPath);
		
		if ($result !== true) {
			throw new Exception('Failed to open bin.zip: ' . $this->getZipError($result));
		}
		
		$extractPath = $this->binDir;
		
		if (!$zip->extractTo($extractPath)) {
			$zip->close();
			throw new Exception('Failed to extract bin.zip to: ' . $extractPath);
		}
		
		$zip->close();
		
		// Set executable permissions on Unix-like systems
		if (PHP_OS_FAMILY !== 'Windows') {
			$this->setExecutablePermissions();
		}
		
		echo "Extracted bin.zip to src/bin/\n";
	}
	
	private function setExecutablePermissions(): void
	{
		$linuxBinary = $this->binDir . '/lin/duckdb';
		if (file_exists($linuxBinary)) {
			chmod($linuxBinary, 0755);
		}
		
		$linuxLib = $this->binDir . '/lin/libduckdb.so';
		if (file_exists($linuxLib)) {
			chmod($linuxLib, 0755);
		}
	}
	
	private function cleanup(string $zipPath): void
	{
		if (file_exists($zipPath)) {
			unlink($zipPath);
		}
		
		if (is_dir($this->tempDir) && count(scandir($this->tempDir)) === 2) {
			rmdir($this->tempDir);
		}
	}
	
	private function formatBytes(int $size): string
	{
		$units = ['B', 'KB', 'MB', 'GB'];
		$unitIndex = 0;
		
		while ($size >= 1024 && $unitIndex < count($units) - 1) {
			$size /= 1024;
			$unitIndex++;
		}
		
		return round($size, 2) . ' ' . $units[$unitIndex];
	}
	
	private function getZipError(int $code): string
	{
		switch ($code) {
			case ZipArchive::ER_NOZIP:
				return 'Not a zip archive';
			case ZipArchive::ER_INCONS:
				return 'Inconsistent archive';
			case ZipArchive::ER_CRC:
				return 'CRC error';
			case ZipArchive::ER_OPEN:
				return 'Can\'t open file';
			default:
				return 'Error code: ' . $code;
		}
	}
}

// Run the installer
if (php_sapi_name() === 'cli') {
	$installer = new BinaryInstaller();
	$installer->install();
}
