<?php
require_once __DIR__ . '/../utils/auth_check.php';
requireLogin();

require_once __DIR__ . '/../utils/config.php';

$uploadSuccess = [];
$uploadErrors = [];

// Define allowed file extensions and MIME types
$ALLOWED_EXTENSIONS = $_ENV['ALLOWED_EXTENSIONS'];
$ALLOWED_MIME_TYPES = $_ENV['ALLOWED_MIME_TYPES'];


// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    $selectedDir = isset($_POST['upload_directory']) ? $_POST['upload_directory'] : '';
    
    // Verify that the selected directory exists in our config
    if (array_key_exists($selectedDir, $directories)) {
        $uploadDir = $directories[$selectedDir];
        
        // Handle multiple file uploads
        $totalFiles = count($_FILES['files']['name']);
        
        for ($i = 0; $i < $totalFiles; $i++) {
            if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                $fileName = basename($_FILES['files']['name'][$i]);
                $fileExtension = strtolower(substr($fileName, strrpos($fileName, ".")));
                
                // Check if file extension is allowed
                if (!in_array($fileExtension, $ALLOWED_EXTENSIONS)) {
                    $uploadErrors[] = $fileName . " (File extension not allowed)";
                    continue;
                }
                
                // Check MIME type
                $fileMimeType = mime_content_type($_FILES['files']['tmp_name'][$i]);
                if (!in_array($fileMimeType, $ALLOWED_MIME_TYPES)) {
                    $uploadErrors[] = $fileName . " (File type not allowed: " . $fileMimeType . ")";
                    continue;
                }
                $targetFile = $uploadDir . '/' . $fileName;
                
                if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $targetFile)) {
                    $uploadSuccess[] = $fileName;
                } else {
                    $uploadErrors[] = $fileName;
                }
            } else if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) {
                // Handle specific upload errors
                switch ($_FILES['files']['error'][$i]) {
                    case UPLOAD_ERR_INI_SIZE:
                        $uploadErrors[] = basename($_FILES['files']['name'][$i]) . " (File size exceeds the upload_max_filesize directive in php.ini)";
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        $uploadErrors[] = basename($_FILES['files']['name'][$i]) . " (File size exceeds the MAX_FILE_SIZE directive specified in the HTML form)";
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $uploadErrors[] = basename($_FILES['files']['name'][$i]) . " (File was only partially uploaded)";
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $uploadErrors[] = basename($_FILES['files']['name'][$i]) . " (No file was uploaded)";
                        break;
                    default:
                        $uploadErrors[] = basename($_FILES['files']['name'][$i]) . " (Unknown error)";
                        break;
                }
            } else {
                $uploadErrors[] = basename($_FILES['files']['name'][$i]) . " (Error code: " . $_FILES['files']['error'][$i] . ")";
            }
        }
    } else {
        $uploadErrors[] = "Invalid directory selected.";
    }
}

header('Content-Type: application/json');
//header('Access-Control-Allow-Origin: *');

if (!empty($uploadSuccess)) {
    echo json_encode(['message' => 'Files uploaded successfully.', 'status' => 'success', 'uploaded_files' => $uploadSuccess]);
} else if (!empty($uploadErrors)) {
    echo json_encode(['message' => 'Some files failed to upload.', 'status' => 'error', 'upload_errors' => $uploadErrors]);
} else {
    echo json_encode(['message' => 'No files uploaded.']);
}