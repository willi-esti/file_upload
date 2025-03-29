<?php

require_once __DIR__ . '/../utils/auth_check.php';
requireLogin();

require_once __DIR__ . '/../utils/config.php';

// Security check: Validate directory parameter
if (!isset($_GET['dir']) || 
    !is_string($_GET['dir']) || 
    empty($_GET['dir']) || 
    //!preg_match('/^[a-zA-Z0-9_]+$/', $_GET['dir']) || // Allow only alphanumeric and underscore
    !array_key_exists($_GET['dir'], $directories)) {
    die('Invalid directory specified.');
}
$requestedDir = $_GET['dir'];

$dirPath = $directories[$requestedDir];

if (isset($_GET['action']) && $_GET['action'] === 'download_all') {
    require_once __DIR__ . '/../vendor/autoload.php';
    
    $zip = new \ZipStream\ZipStream(
        outputName: $requestedDir . '_files.zip',
    );
    
    $dirIterator = new DirectoryIterator($dirPath);
    //print_r($dirIterator);
    
    foreach ($dirIterator as $fileInfo) {
        //echo 'File: ' . $fileInfo->getFilename() . '<br>';
        if ($fileInfo->isFile()) {
            $filePath = $fileInfo->getPathname();
            $zip->addFileFromPath($fileInfo->getFilename(), $filePath);
            //print_r($fileInfo->getFilename());
        }
    }
    //print_r($zip);

    $zip->finish();
    exit;
} else if (isset($_GET['file'])) {
    echo '<h1>Download File</h1>';
    $requestedFile = $_GET['file'];
    $filePath = $dirPath . '/' . $requestedFile;
    
    $realFilePath = realpath($filePath);
    $realDirPath = realpath($dirPath);
    
    if ($realFilePath === false || !file_exists($realFilePath)) {
        die("Error: File does not exist.");
    }
    
    if (strpos($realFilePath, $realDirPath) !== 0) {
        die("Error: Access denied.");
    }
    
    $fileInfo = pathinfo($realFilePath);
    $fileName = $fileInfo['basename'];
    
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($realFilePath));
    
    ob_clean();
    flush();
    
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

// No valid parameters, redirect back to upload page
//header('Location: flux.php');
exit;
?>
