<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Initialize error message variable
$error_message = null;

// Load environment variables from .env file
require_once __DIR__ . '/../vendor/autoload.php';

try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();

    // Check if the required environment variables are set
    $requiredEnvVars = ['ADMIN_USERNAME', 'ADMIN_PASSWORD', 'DIRECTORIES_JSON'];
    foreach ($requiredEnvVars as $var) {
        if (!isset($_ENV[$var])) {
            throw new Exception("Environment variable '$var' is not set.");
        } else if (empty($_ENV[$var])) {
            throw new Exception("Environment variable '$var' is empty.");
        }
    }
    
    // Check if the directories JSON is valid
    if (empty($_ENV['DIRECTORIES_JSON'])) {
        throw new Exception('DIRECTORIES_JSON environment variable is empty.');
    }
    
    // Decode the JSON string
    $directories = json_decode($_ENV['DIRECTORIES_JSON'], true);
    
    // Check if the JSON is valid
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON in DIRECTORIES_JSON environment variable.');
    }

    if ($directories === null) {
        throw new Exception('Invalid JSON in DIRECTORIES_JSON environment variable.');
    }
    
    // Check if the directories are valid
    foreach ($directories as $name => $path) {
        if (!is_dir($path)) {
            throw new Exception("Directory '$path' does not exist.");
        }
        if (!is_writable($path)) {
            throw new Exception("Directory '$path' is not writable.");
        }
        if (!is_readable($path)) {
            throw new Exception("Directory '$path' is not readable.");
        }
        if (!is_executable($path)) {
            throw new Exception("Directory '$path' is not executable.");
        }
    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Display error if any
if ($error_message && basename($_SERVER['PHP_SELF']) !== 'index.php') {
    echo '<div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); padding: 20px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; text-align: center;">';
    echo '<h3 style="color: #721c24;">Error</h3>';
    echo '<p>' . htmlspecialchars($error_message) . '</p>';
    echo '<a href="logout.php" style="display: inline-block; margin-top: 10px; padding: 5px 10px; background-color: #dc3545; color: white; text-decoration: none; border-radius: 3px;">Logout</a>';
    echo '</div>';
    exit;
}
?>
