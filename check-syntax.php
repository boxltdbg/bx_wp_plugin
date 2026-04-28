<?php
/**
 * Temporary script to check for PHP syntax errors
 * Run this file directly to see any syntax errors
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if the main plugin file has syntax errors
$plugin_file = __DIR__ . '/box-now-delivery.php';

echo "Checking syntax of: $plugin_file\n\n";

// Use PHP's built-in syntax checker
$output = array();
$return_var = 0;
exec("php -l \"$plugin_file\" 2>&1", $output, $return_var);

if ($return_var === 0) {
    echo "✓ No syntax errors found!\n";
} else {
    echo "✗ Syntax errors found:\n";
    foreach ($output as $line) {
        echo $line . "\n";
    }
}

echo "\n\nTrying to include the file to check for runtime errors...\n";

// Try to include the file
ob_start();
try {
    include($plugin_file);
    $output = ob_get_clean();
    echo "✓ File included successfully!\n";
} catch (Exception $e) {
    $output = ob_get_clean();
    echo "✗ Exception caught: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} catch (Error $e) {
    $output = ob_get_clean();
    echo "✗ Fatal error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

