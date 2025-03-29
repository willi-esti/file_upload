<?php

include_once 'auth_check.php';
requireLogin();
include_once 'config.php';

$uploadSuccess = [];
$uploadErrors = [];
$message = null;

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
                $targetFile = $uploadDir . '/' . basename($_FILES['files']['name'][$i]);
                
                if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $targetFile)) {
                    $uploadSuccess[] = basename($_FILES['files']['name'][$i]);
                } else {
                    $uploadErrors[] = basename($_FILES['files']['name'][$i]);
                }
            } else if  ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) {
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
    <h2>File Manager</h2>
    
    <div style="text-align: right; margin-bottom: 20px;">
        <span>Welcome, <?= htmlspecialchars($_SESSION['username']) ?></span>
        <a href="logout.php" style="margin-left: 10px; color: #dc3545;">Logout</a>
    </div>

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
        <input type="file" name="files[]" id="files" multiple required>
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