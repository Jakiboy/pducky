<?php
/**
 * Standalone DuckDB Binary Installer
 * 
 * This script can be used to install DuckDB binaries after running:
 * composer require jakiboy/pducky
 * 
 * Usage:
 * 1. Copy this file to your project root
 * 2. Run: php install-pducky-binaries.php
 * 
 * @package Pducky
 * @version 0.2.0
 */

echo "Installing DuckDB binaries for jakiboy/pducky...\n";

// Configuration
$vendorDir = "vendor";
$packagePath = $vendorDir . "/jakiboy/pducky/";
$binPath = $packagePath . "src/bin/";

// Check if pducky package exists
if (!is_dir($packagePath)) {
    echo "❌ Error: jakiboy/pducky package not found.\n";
    echo "   Please run: composer require jakiboy/pducky\n";
    exit(1);
}

// Function to check if binaries already exist
function binariesExist($binPath) {
    if (strtoupper(substr(PHP_OS, 0, 3)) === "WIN") {
        return file_exists($binPath . "win/duckdb.exe") && file_exists($binPath . "win/duckdb.dll");
    } else {
        return file_exists($binPath . "lin/duckdb") && file_exists($binPath . "lin/libduckdb.so");
    }
}

// Check if binaries already exist
if (binariesExist($binPath)) {
    echo "✅ DuckDB binaries already installed.\n";
    exit(0);
}

// Check if ZipArchive extension is available
if (!class_exists('ZipArchive')) {
    echo "❌ Error: PHP ZipArchive extension is required.\n";
    echo "   Please install php-zip extension.\n";
    exit(1);
}

// Install binaries from bin.zip
$zipFile = $binPath . "bin.zip";
if (!file_exists($zipFile)) {
    echo "❌ Error: bin.zip not found in {$binPath}\n";
    echo "   The package may be incomplete.\n";
    exit(1);
}

echo "Extracting DuckDB binaries...\n";

$zip = new ZipArchive();
if ($zip->open($zipFile) === TRUE) {
    // Extract to bin directory
    if ($zip->extractTo($binPath)) {
        $zip->close();
        
        // Make Linux binaries executable
        if (strtoupper(substr(PHP_OS, 0, 3)) !== "WIN") {
            $linuxBinary = $binPath . "lin/duckdb";
            if (file_exists($linuxBinary)) {
                chmod($linuxBinary, 0755);
                echo "Made Linux binary executable.\n";
            }
        }
        
        echo "✅ DuckDB binaries installed successfully!\n";
        echo "\nBinaries installed to: {$binPath}\n";
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === "WIN") {
            echo "Windows binaries: {$binPath}win/\n";
        } else {
            echo "Linux binaries: {$binPath}lin/\n";
        }
        
    } else {
        $zip->close();
        echo "❌ Error: Failed to extract files from bin.zip\n";
        exit(1);
    }
} else {
    echo "❌ Error: Failed to open bin.zip\n";
    echo "   The archive may be corrupted.\n";
    exit(1);
}
