<?php
// Define the same directories as in upload.php

require_once __DIR__ . '/../utils/auth_check.php';
requireLogin();

require_once __DIR__ . '/../utils/config.php';

// Security check: Validate directory parameter
if (!isset($_GET['dir']) || !array_key_exists($_GET['dir'], $directories)) {
    die('Invalid directory specified.');
}

$dirKey = $_GET['dir'];
$dirPath = $directories[$dirKey];

// Check for single file deletion
if (isset($_GET['file'])) {
    $filename = basename($_GET['file']); // Sanitize filename
    $filepath = $dirPath . '/' . $filename;
    
    // Security check: Ensure the file is within the allowed directory
    if (!file_exists($filepath) || !is_file($filepath)) {
        die('File not found or invalid.');
    }
    
    // Delete the file
    if (unlink($filepath)) {
        header('Location: flux.php?message=File deleted successfully');
        exit;
    } else {
        header('Location: flux.php?message=Failed to delete file');
        exit;
    }
}
// Check for delete all action
elseif (isset($_GET['action']) && $_GET['action'] === 'delete_all') {
    $deleted = 0;
    $failed = 0;
    
    // Get all files in the directory
    $files = scandir($dirPath);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue; // Skip directory references
        }
        
        $filepath = $dirPath . '/' . $file;
        
        // Only delete files, not directories
        if (is_file($filepath)) {
            if (unlink($filepath)) {
                $deleted++;
            } else {
                $failed++;
            }
        }
    }
    
    if ($failed === 0) {
        header('Location: flux.php?message=All files deleted successfully');
        exit;
    } else {
        header('Location: flux.php?message=' . $deleted . ' files deleted, ' . $failed . ' failed');
        exit;
    }
} else {
    // No action specified
    header('Location: upload.php');
    exit;
}
?>
