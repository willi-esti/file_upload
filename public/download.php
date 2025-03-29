<?php
// Define the same directories as in upload.php for consistency
include_once 'config.php';
// Process download request
if (isset($_GET['dir'])) {
    $requestedDir = $_GET['dir'];
    
    // Validate that the requested directory exists in our configuration
    if (!array_key_exists($requestedDir, $directories)) {
        die("Error: Invalid directory specified.");
    }
    
    $dirPath = $directories[$requestedDir];
    
    // Check if this is a download all request
    if (isset($_GET['action']) && $_GET['action'] === 'download_all') {
        // Use ZipStream to create and stream ZIP files
        require 'vendor/autoload.php'; // Include ZipStream library
        use ZipStream\ZipStream;

        $zip = new ZipStream($requestedDir . '_files.zip');

        $dirIterator = new DirectoryIterator($dirPath);
        foreach ($dirIterator as $fileInfo) {
            if ($fileInfo->isFile()) {
                $filePath = $fileInfo->getPathname();
                $zip->addFileFromPath($fileInfo->getFilename(), $filePath);
            }
        }

        $zip->finish();
        exit;
    }
    // Regular single file download
    elseif (isset($_GET['file'])) {
        $requestedFile = $_GET['file'];
        $filePath = $dirPath . '/' . $requestedFile;
        
        // Validate the file exists and prevent directory traversal attacks
        $realFilePath = realpath($filePath);
        $realDirPath = realpath($dirPath);
        
        if ($realFilePath === false || !file_exists($realFilePath)) {
            die("Error: File does not exist.");
        }
        
        // Security check to ensure the file is within the intended directory
        if (strpos($realFilePath, $realDirPath) !== 0) {
            die("Error: Access denied.");
        }
        
        // Get file information
        $fileInfo = pathinfo($realFilePath);
        $fileName = $fileInfo['basename'];
        
        // Set headers for download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($realFilePath));
        
        // Clear output buffer
        ob_clean();
        flush();
        
        // Stream the file in chunks to reduce memory usage
        $chunkSize = 1024 * 1024; // 1MB
        $handle = fopen($realFilePath, 'rb');
        if ($handle) {
            while (!feof($handle)) {
                echo fread($handle, $chunkSize);
                flush(); // Ensure the buffer is sent to the client
            }
            fclose($handle);
        }
        exit;
    }
} 

// No valid parameters, redirect back to upload page
header('Location: upload.php');
exit;
?>
