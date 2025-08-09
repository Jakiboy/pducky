<?php
/**
 * @author    Jihad Sinnaour <me@jihadsinnaour.com>
 * @package   Pducky
 * @version   0.2.0
 * @license   MIT
 * @copyright (c) 2024 Jihad Sinnaour
 * @link      https://github.com/Jakiboy/pducky
 */

declare(strict_types=1);

namespace Pducky;

use Composer\Script\Event;
use Composer\Installer\PackageEvent;

/**
 * Composer script handler for installing DuckDB binaries.
 */
final class Installer
{
	/**
	 * Post install script handler.
	 *
	 * @param Event $event
	 * @return void
	 */
	public static function postInstall(Event $event): void
	{
		self::installBinaries($event);
	}

	/**
	 * Post update script handler.
	 *
	 * @param Event $event
	 * @return void
	 */
	public static function postUpdate(Event $event): void
	{
		self::installBinaries($event);
	}

	/**
	 * Post autoload dump script handler.
	 *
	 * @param Event $event
	 * @return void
	 */
	public static function postAutoloadDump(Event $event): void
	{
		self::installBinaries($event);
	}

	/**
	 * Install DuckDB binaries.
	 *
	 * @param Event $event
	 * @return void
	 */
	public static function installBinaries(Event $event): void
	{
		$io = $event->getIO();
		$vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
		
		// Determine if we're in the main package or installed as dependency
		$packagePath = null;
		
		// Check if we're in the pducky package root
		if (file_exists('./composer.json')) {
			$composerData = json_decode(file_get_contents('./composer.json'), true);
			if (isset($composerData['name']) && $composerData['name'] === 'jakiboy/pducky') {
				$packagePath = './';
			}
		}
		
		// Check if we're installed as a dependency
		if (!$packagePath && $vendorDir) {
			$dependencyPath = $vendorDir . '/jakiboy/pducky';
			if (is_dir($dependencyPath)) {
				$packagePath = $dependencyPath . '/';
			}
		}
		
		if (!$packagePath) {
			$io->write('<error>Could not determine pducky package path</error>');
			return;
		}
		
		$binPath = $packagePath . 'src/bin/';
		
		// Check if binaries already exist
		if (self::binariesExist($binPath)) {
			$io->write('<info>✅ DuckDB binaries already installed.</info>');
			return;
		}
		
		$io->write('<info>Installing DuckDB binaries...</info>');
		
		// Create bin directory if it doesn't exist
		if (!is_dir($binPath)) {
			mkdir($binPath, 0755, true);
		}
		
		// Install binaries based on OS
		if (self::isWindows()) {
			self::installWindowsBinaries($binPath, $io);
		} else {
			self::installLinuxBinaries($binPath, $io);
		}
		
		$io->write('<info>✅ DuckDB binaries installed successfully.</info>');
	}
	
	/**
	 * Check if binaries already exist.
	 *
	 * @param string $binPath
	 * @return bool
	 */
	private static function binariesExist(string $binPath): bool
	{
		if (self::isWindows()) {
			return file_exists($binPath . 'win/duckdb.exe') && file_exists($binPath . 'win/duckdb.dll');
		} else {
			return file_exists($binPath . 'lin/duckdb') && file_exists($binPath . 'lin/libduckdb.so');
		}
	}
	
	/**
	 * Check if running on Windows.
	 *
	 * @return bool
	 */
	private static function isWindows(): bool
	{
		return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
	}
	
	/**
	 * Install Windows binaries.
	 *
	 * @param string $binPath
	 * @param \Composer\IO\IOInterface $io
	 * @return void
	 */
	private static function installWindowsBinaries(string $binPath, $io): void
	{
		$winPath = $binPath . 'win/';
		if (!is_dir($winPath)) {
			mkdir($winPath, 0755, true);
		}
		
		// Extract from bin.zip if it exists
		$zipFile = $binPath . 'bin.zip';
		if (file_exists($zipFile)) {
			$zip = new \ZipArchive();
			if ($zip->open($zipFile) === TRUE) {
				$zip->extractTo($binPath);
				$zip->close();
				$io->write('<info>Extracted Windows binaries from bin.zip</info>');
			} else {
				$io->write('<error>Failed to extract bin.zip</error>');
			}
		} else {
			$io->write('<warning>bin.zip not found, binaries may need to be downloaded separately</warning>');
		}
	}
	
	/**
	 * Install Linux binaries.
	 *
	 * @param string $binPath
	 * @param \Composer\IO\IOInterface $io
	 * @return void
	 */
	private static function installLinuxBinaries(string $binPath, $io): void
	{
		$linPath = $binPath . 'lin/';
		if (!is_dir($linPath)) {
			mkdir($linPath, 0755, true);
		}
		
		// Extract from bin.zip if it exists
		$zipFile = $binPath . 'bin.zip';
		if (file_exists($zipFile)) {
			$zip = new \ZipArchive();
			if ($zip->open($zipFile) === TRUE) {
				$zip->extractTo($binPath);
				$zip->close();
				
				// Make Linux binaries executable
				if (file_exists($linPath . 'duckdb')) {
					chmod($linPath . 'duckdb', 0755);
				}
				
				$io->write('<info>Extracted Linux binaries from bin.zip</info>');
			} else {
				$io->write('<error>Failed to extract bin.zip</error>');
			}
		} else {
			$io->write('<warning>bin.zip not found, binaries may need to be downloaded separately</warning>');
		}
	}
}
