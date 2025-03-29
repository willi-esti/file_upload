<?php
include_once 'auth_check.php';
requireLogin();
include_once 'config.php';

$uploadSuccess = [];
$uploadErrors = [];
$message = null;

// Define allowed file extensions and MIME types
$ALLOWED_EXTENSIONS = $_ENV['ALLOWED_EXTENSIONS'];
$ALLOWED_MIME_TYPES = $_ENV['ALLOWED_MIME_TYPES'];
// Check if there's a message from another page (like delete.php)
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

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
                //echo $fileExtension;
                //print_r($ALLOWED_EXTENSIONS);
                
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

// Function to list files in a directory
function listFiles($path) {
    $files = scandir($path);
    return array_diff($files, ['.', '..']); // Remove "." and ".."
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        h2 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        h3 {
            color: #2c3e50;
            margin-top: 30px;
        }
        
        h4 {
            background-color: #3498db;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        
        form {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        input[type="file"] {
            border: 1px solid #ddd;
            padding: 8px;
            width: 100%;
            margin-bottom: 10px;
        }
        
        select {
            border: 1px solid #ddd;
            padding: 8px;
            width: 100%;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        
        button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        
        button:hover {
            background-color: #2980b9;
        }
        
        ul {
            list-style-type: none;
            padding: 0;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        li {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
        }
        
        li:last-child {
            border-bottom: none;
        }
        
        p strong {
            display: block;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    
    <div style="text-align: right; margin-bottom: 20px; display: flex; justify-content: flex-end; align-items: center;">
        <span style="margin-right: 15px; font-weight: 500;">Welcome, <?= htmlspecialchars($_SESSION['username']) ?></span>
        <a href="logout.php" style="text-decoration: none; background-color: #dc3545; color: white; padding: 8px 15px; border-radius: 4px; font-weight: bold; transition: background-color 0.3s;">Logout</a>
    </div>
    <h2>File Manager</h2>
    

    <?php if (!empty($uploadSuccess)): ?>
        <p><strong class="success">Successfully uploaded: <?= htmlspecialchars(implode(', ', $uploadSuccess)) ?></strong></p>
    <?php endif; ?>
    
    <?php if (!empty($uploadErrors)): ?>
        <p><strong class="error">Failed to upload: <?= htmlspecialchars(implode(', ', $uploadErrors)) ?></strong></p>
    <?php endif; ?>
    
    <?php if ($message !== null): ?>
        <p><strong class="<?= strpos($message, 'success') !== false || strpos($message, 'deleted successfully') !== false ? 'success' : 'error' ?>"><?= htmlspecialchars($message) ?></strong></p>
    <?php endif; ?>

    <h3>Upload Files</h3>
    <form action="" method="POST" enctype="multipart/form-data">
        <label for="upload_directory">Select Directory:</label>
        <select name="upload_directory" id="upload_directory" required>
            <?php foreach ($directories as $name => $path): ?>
                <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?> (<?= htmlspecialchars($path) ?>)</option>
            <?php endforeach; ?>
        </select>
        
        <label for="files">Select Files:</label>
        <?php
        
        $maxUploadSize = min(
            convertToBytes(ini_get('upload_max_filesize')),
            convertToBytes(ini_get('post_max_size'))
        );
        //echo '<input type="hidden" name="MAX_FILE_SIZE" value="' . $maxUploadSize . '">';
        $maxUploadSizeFormatted = formatBytes($maxUploadSize);
        ?>
        <input type="file" name="files[]" id="files" multiple required
               onchange="validateFileSize(this, <?= $maxUploadSize ?>)">
        <small>Maximum upload size: <?= $maxUploadSizeFormatted ?></small><br>
        
        <script>
        function validateFileSize(input, maxSize) {
            const files = input.files;
            for (let i = 0; i < files.length; i++) {
            if (files[i].size > maxSize) {
                alert(`File "${files[i].name}" exceeds the maximum upload size of ${formatBytes(maxSize)}.`);
                input.value = ''; // Clear the input
                return false;
            }
            }
            return true;
        }
        
        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        </script>
        
        <?php
        // Helper function to convert PHP size strings (like 8M, 200K) to bytes
        function convertToBytes($size) {
            $unit = strtolower(substr($size, -1));
            $value = (int)$size;
            
            switch ($unit) {
            case 'g': $value *= 1024;
            case 'm': $value *= 1024;
            case 'k': $value *= 1024;
            }
            
            return $value;
        }
        
        // Helper function to format bytes to human-readable format
        function formatBytes($bytes, $precision = 2) {
            $units = ['Bytes', 'KB', 'MB', 'GB'];
            $bytes = max($bytes, 0);
            $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
            $pow = min($pow, count($units) - 1);
            $bytes /= pow(1024, $pow);
            return round($bytes, $precision) . ' ' . $units[$pow];
        }
        ?>
        <button type="submit">Upload</button>
    </form>
    <h3>Directory Contents</h3>
    <?php foreach ($directories as $name => $path): ?>
        <h4><?= htmlspecialchars($name) ?> (<?= htmlspecialchars($path) ?>)</h4>
        <div style="margin-bottom: 10px;">
            <a href="download.php?dir=<?= urlencode($name) ?>&action=download_all" class="button" style="display: inline-block; background-color: #28a745; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; margin-right: 10px;">Download All as ZIP</a>
            <a href="delete.php?dir=<?= urlencode($name) ?>&action=delete_all" class="button" style="display: inline-block; background-color: #dc3545; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; margin-bottom: 10px;" onclick="return confirm('Are you sure you want to delete all files in this directory?');">Delete All Files</a>
        </div>
        <ul>
            <?php foreach (listFiles($path) as $file): ?>
                <li>
                    <?= htmlspecialchars($file) ?>
                    <a href="download.php?dir=<?= urlencode($name) ?>&file=<?= urlencode($file) ?>">Download</a>
                    <a href="delete.php?dir=<?= urlencode($name) ?>&file=<?= urlencode($file) ?>" style="color: #dc3545; margin-left: 10px;" onclick="return confirm('Are you sure you want to delete this file?');">Delete</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endforeach; ?>
</body>
</html>