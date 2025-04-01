Okay, great! Thanks for the detailed answers. This gives me a much clearer picture.

Here's the refined plan and how we'll structure the project:

**Key Decisions Based on Your Feedback:**

- **File List UI:** We'll stick with a table format for listing files, similar to your current setup, but apply the styling (colors, fonts, spacing, borders) derived from `presaje.sga.defense.gouv.fr`'s elements.
- **Refresh:** Default 30 seconds, configurable via UI controls (enable/disable checkbox, number input for interval). State saved in browser cookies.
- **Logos:** We will use the logos from the reference site as requested (you take responsibility for usage rights). I'll likely need to extract these via browser developer tools.
- **File Size Limit:** We'll keep the logic of checking `php.ini` settings (`upload_max_filesize`, `post_max_size`) and display the effective limit in the UI. The PHP upload script should still respect these limits implicitly, but adding an explicit check is good practice.

**Project Structure:**

We'll refactor the project into a clearer frontend/backend separation.

```
/file_upload_vanillajs/
├── .env                 # Your configuration (UNCHANGED, add MAX_FILE_SIZE)
├── .env.example         # Example config (add MAX_FILE_SIZE)
├── composer.json        # Dependencies (phpdotenv)
├── vendor/              # Composer packages
│
├── public/              # Web server root
│   ├── index.php        # Main HTML shell loader
│   ├── style.css        # All CSS styles
│   ├── app.js           # All Vanilla JavaScript logic
│   ├── assets/          # Images, logos extracted from reference site
│   │   ├── logo-gouv.svg  # Example logo names
│   │   └── ...
│   │
│   └── api/             # Backend API endpoints
│       ├── list_files.php  # Endpoint to list directories and files
│       ├── upload.php      # Endpoint to handle file uploads
│       └── download.php    # Endpoint for file downloads (can stay similar)
│
└── src/                 # PHP backend logic/helpers (Not publicly accessible)
    ├── config.php       # Loads .env, defines constants/base paths
    ├── auth.php         # Authentication check function
    └── utils.php        # Helper functions (e.g., sanitize filename, format bytes)
```

**Implementation Steps & Code Snippets:**

**1. Backend (`/src/`, `/public/api/`)**

- **`src/config.php`:**

  ```php
  <?php
  require_once __DIR__ . '/../vendor/autoload.php'; // Adjust path if needed

  try {
      $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); // Point to root directory
      $dotenv->load();
      $dotenv->required(['AUTH_USER', 'AUTH_PASS', 'BASE_UPLOAD_PATH', 'UPLOAD_DIRS', 'ALLOWED_EXTENSIONS', 'ALLOWED_MIME_TYPES']);

      define('AUTH_USER', $_ENV['AUTH_USER']);
      define('AUTH_PASS', $_ENV['AUTH_PASS']);
      define('BASE_UPLOAD_PATH', rtrim($_ENV['BASE_UPLOAD_PATH'], '/'));
      // Ensure UPLOAD_DIRS is an array
      $uploadDirs = array_map('trim', explode(',', $_ENV['UPLOAD_DIRS']));
      define('UPLOAD_DIRS', $uploadDirs);

      define('ALLOWED_EXTENSIONS', array_map('trim', explode(',', $_ENV['ALLOWED_EXTENSIONS'])));
      define('ALLOWED_MIME_TYPES', array_map('trim', explode(',', $_ENV['ALLOWED_MIME_TYPES'])));
      define('MAX_FILE_SIZE', $_ENV['MAX_FILE_SIZE'] ?? '0'); // Add MAX_FILE_SIZE to .env (e.g., '50M')

  } catch (Exception $e) {
      // Handle error loading .env (e.g., log and exit)
      http_response_code(500);
      echo json_encode(['success' => false, 'error' => 'Server configuration error.']);
      exit;
  }

  // Function to get PHP upload limits
  function get_upload_max_size() {
      static $max_size = -1;

      if ($max_size < 0) {
          $post_max_size = parse_size(ini_get('post_max_size'));
          $upload_max_filesize = parse_size(ini_get('upload_max_filesize'));
          $env_max_size = parse_size(MAX_FILE_SIZE);

          $max_size = min($post_max_size > 0 ? $post_max_size : PHP_INT_MAX,
                          $upload_max_filesize > 0 ? $upload_max_filesize : PHP_INT_MAX,
                          $env_max_size > 0 ? $env_max_size : PHP_INT_MAX);
      }
      return $max_size;
  }

  function parse_size($size) {
      $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
      $size = (float) preg_replace('/[^0-9\.]/', '', $size);
      if ($unit) {
          return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
      } else {
          return round($size);
      }
  }
  ?>
  ```

- **`src/auth.php`:**
  ```php
  <?php
  function require_auth() {
      if (!isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) ||
          $_SERVER['PHP_AUTH_USER'] !== AUTH_USER ||
          $_SERVER['PHP_AUTH_PW'] !== AUTH_PASS) {
          header('WWW-Authenticate: Basic realm="File Uploader"');
          header('HTTP/1.0 401 Unauthorized');
          echo json_encode(['success' => false, 'error' => 'Authentication required.']);
          exit;
      }
      // User is authenticated
  }
  ?>
  ```
- **`src/utils.php`:**

  ```php
  <?php
  function format_bytes($bytes, $precision = 2) {
      // (Implementation for formatting bytes to KB, MB, GB...)
      // ... see previous examples or standard implementations ...
  }

  function sanitize_filename($filename) {
      // Remove path traversal
      $filename = basename($filename);
      // Remove potentially dangerous characters (adjust as needed)
      $filename = preg_replace("/[^a-zA-Z0-9\._\-]/", '_', $filename);
      // Prevent leading/trailing dots or underscores if desired
      $filename = trim($filename, '._');
      // Prevent names like ".htaccess"
      if (strpos($filename, '.') === 0) {
           $filename = '_' . $filename;
      }
      // Limit length
      $filename = mb_substr($filename, 0, 200); // Limit length
      return $filename ?: 'uploaded_file'; // Ensure not empty
  }
  ?>
  ```

- **`public/api/list_files.php`:**

  ```php
  <?php
  header('Content-Type: application/json');
  require_once __DIR__ . '/../../src/config.php';
  require_once __DIR__ . '/../../src/auth.php';
  require_once __DIR__ . '/../../src/utils.php';

  require_auth(); // Check authentication

  $selected_dir = $_GET['dir'] ?? (UPLOAD_DIRS[0] ?? null); // Default to first dir
  $response = ['success' => true, 'files' => [], 'directories' => UPLOAD_DIRS, 'max_upload_size_bytes' => get_upload_max_size()];

  if (!$selected_dir || !in_array($selected_dir, UPLOAD_DIRS)) {
      $response['success'] = false;
      $response['error'] = 'Invalid directory specified.';
      echo json_encode($response);
      exit;
  }

  $target_path = BASE_UPLOAD_PATH . '/' . $selected_dir;

  if (!is_dir($target_path) || !is_readable($target_path)) {
      $response['success'] = false;
      $response['error'] = 'Cannot access directory on server.';
       // Log detailed error internally here
      error_log("Error accessing directory: " . $target_path);
      echo json_encode($response);
      exit;
  }

  try {
      $items = scandir($target_path);
      $files = [];
      foreach ($items as $item) {
          if ($item === '.' || $item === '..') {
              continue;
          }
          $item_path = $target_path . '/' . $item;
          if (is_file($item_path)) { // Only list files
              $files[] = [
                  'name' => $item,
                  'size' => filesize($item_path),
                  'size_formatted' => format_bytes(filesize($item_path)),
                  'modified' => filemtime($item_path) * 1000 // JS uses milliseconds
                  // Add 'type' => mime_content_type($item_path) if needed
              ];
          }
      }
      // Sort files, e.g., by name
      usort($files, fn($a, $b) => strcmp(strtolower($a['name']), strtolower($b['name'])));
      $response['files'] = $files;

  } catch (Exception $e) {
       $response['success'] = false;
       $response['error'] = 'Error reading directory contents.';
       // Log detailed error internally here
       error_log("Scandir error for " . $target_path . ": " . $e->getMessage());
  }

  echo json_encode($response);
  ?>
  ```

- **`public/api/upload.php`:**

  ```php
  <?php
  header('Content-Type: application/json');
  require_once __DIR__ . '/../../src/config.php';
  require_once __DIR__ . '/../../src/auth.php';
  require_once __DIR__ . '/../../src/utils.php';

  require_auth(); // Check authentication

  $response = ['success' => false];

  // --- Validation ---
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $response['error'] = 'Invalid request method.';
      echo json_encode($response); exit;
  }

  if (!isset($_POST['directory']) || !in_array($_POST['directory'], UPLOAD_DIRS)) {
      $response['error'] = 'Target directory not specified or invalid.';
      echo json_encode($response); exit;
  }
  $target_dir_name = $_POST['directory'];
  $upload_path = BASE_UPLOAD_PATH . '/' . $target_dir_name;

  if (!isset($_FILES['file'])) {
      $response['error'] = 'No file uploaded.';
      echo json_encode($response); exit;
  }

  $file = $_FILES['file'];

  // Check for upload errors
  if ($file['error'] !== UPLOAD_ERR_OK) {
      // Map error codes to user-friendly messages
      $upload_errors = [ /* ... standard PHP upload error messages ... */ ];
      $response['error'] = $upload_errors[$file['error']] ?? 'Unknown upload error.';
      echo json_encode($response); exit;
  }

  // Size Check (against configured MAX_FILE_SIZE and PHP limits)
  $max_allowed_size = get_upload_max_size();
  if ($max_allowed_size > 0 && $file['size'] > $max_allowed_size) {
      $response['error'] = 'File exceeds maximum allowed size (' . format_bytes($max_allowed_size) . ').';
      echo json_encode($response); exit;
  }
   if ($file['size'] == 0) {
      $response['error'] = 'Uploaded file is empty.';
      echo json_encode($response); exit;
  }


  $original_filename = $file['name'];
  $file_ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
  $file_mime = mime_content_type($file['tmp_name']); // Check actual MIME

  // Type/Extension Check
  if (!in_array($file_ext, ALLOWED_EXTENSIONS) || !in_array($file_mime, ALLOWED_MIME_TYPES)) {
       $response['error'] = 'Invalid file type or extension not allowed.';
       echo json_encode($response); exit;
  }

  // Sanitize filename
  $safe_filename = sanitize_filename($original_filename);
  $destination = $upload_path . '/' . $safe_filename;

  // Prevent overwriting? (Optional: check if file exists and rename or reject)
  // if (file_exists($destination)) {
  //     $response['error'] = 'File with this name already exists.';
  //     echo json_encode($response); exit;
  // }

  // --- Move the file ---
  if (move_uploaded_file($file['tmp_name'], $destination)) {
      $response['success'] = true;
      $response['message'] = "File '$safe_filename' uploaded successfully to '$target_dir_name'.";
  } else {
      $response['error'] = 'Failed to save uploaded file.';
      // Log detailed error internally
      error_log("move_uploaded_file failed for temp name: " . $file['tmp_name'] . " to destination: " . $destination);
  }

  echo json_encode($response);
  ?>
  ```

- **`public/api/download.php`:**
  - Largely unchanged from your original, but ensure it includes `require_once` for `config.php` and `auth.php` and calls `require_auth()`.
  - Keep using `basename()` on `$_GET['file']`.
  - Validate `$_GET['dir']` against `UPLOAD_DIRS`.
  - Construct the full path carefully using `BASE_UPLOAD_PATH`, validated dir, and basenamed file.
  - Check `file_exists` and `is_readable` before sending headers and `readfile`.

**2. Frontend (`/public/`)**

- **`public/index.php` (HTML Shell):**

  ```php
  <?php
      // You could potentially pre-load the directory list here to avoid the first JS fetch
      // require_once __DIR__ . '/../src/config.php';
      // $initial_dirs_json = json_encode(UPLOAD_DIRS);
  ?>
  <!DOCTYPE html>
  <html lang="en">
  <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Secure File Upload</title>
      <link rel="stylesheet" href="style.css">
      <!-- Add link for favicon if desired -->
  </head>
  <body>
      <header id="main-header">
          <!-- Header content mimicking presaje.sga.defense.gouv.fr -->
          <div class="header-logo">
              <img src="assets/logo-rf.svg" alt="République Française">
               <img src="assets/logo-minarm.svg" alt="Ministère des Armées">
          </div>
          <div class="header-title">
              <h1>Secure File Transfer</h1> <!-- Or your app name -->
          </div>
          <div class="header-user">
              <!-- Maybe display username if needed later -->
          </div>
      </header>

      <main id="app-content">
          <div class="content-wrapper">
              <div class="controls-section">
                  <div class="directory-selector">
                      <label for="directory-select">Target Directory:</label>
                      <select id="directory-select"></select>
                  </div>

                  <div class="upload-form">
                       <label for="file-input">Select File:</label>
                       <input type="file" id="file-input" required>
                       <button id="upload-button" type="button">Upload</button>
                       <span id="max-size-info"></span>
                  </div>
                  <div id="status-messages"></div> <!-- For success/error feedback -->
              </div>

              <div class="file-list-section">
                  <h2>Files</h2>
                  <div class="refresh-controls">
                       <label>
                           <input type="checkbox" id="refresh-toggle"> Auto-refresh
                       </label>
                       <label>
                           Interval (sec):
                           <input type="number" id="refresh-interval" min="5" value="30" style="width: 60px;">
                       </label>
                       <span id="last-updated"></span>
                  </div>
                  <div id="file-list-container">
                      <!-- File table will be rendered here by JS -->
                      <p>Loading files...</p>
                  </div>
              </div>
          </div>
      </main>

      <footer id="main-footer">
          <!-- Footer content mimicking presaje.sga.defense.gouv.fr -->
          <p>&copy; <?php echo date('Y'); ?> - Your Company/Project | Secure File Transfer</p>
          <!-- Add links or text as seen on reference footer -->
      </footer>

      <script src="app.js"></script>
      <!-- Optionally pass initial data if preloaded in PHP -->
      <!-- <script>
           // const initialDirectories = <?php echo $initial_dirs_json ?? '[]'; ?>;
           // app.init(initialDirectories); // Modify app.js to accept this
      </script> -->
  </body>
  </html>
  ```

- **`public/style.css`:**
  - This requires significant effort. Use browser developer tools on `presaje.sga.defense.gouv.fr`.
  - Identify: Color palette (`#000091` blue, `#e3e3fd` light blue/gray, white, grays), fonts (likely `Marianne`, fall back to standard sans-serif like Arial, Helvetica), layout structure (Flexbox/Grid).
  - Style `body`, `#main-header`, `#app-content`, `.content-wrapper`, `#main-footer` for overall layout.
  - Style header elements (`.header-logo img`, `.header-title h1`).
  - Style controls (`.directory-selector select`, `.upload-form input[type=file]`, `.upload-form button`, `#status-messages`). Mimic the input field and button styles from the reference login form.
  - Style the file list section (`.file-list-section h2`, `.refresh-controls`, `#file-list-container`).
  - Style the file table (`<table>`, `<thead>`, `<tbody>`, `<tr>`, `<th>`, `<td>`) generated by JS: borders, padding, alignment, hover effects.
  - Add basic responsiveness using media queries.
- **`public/app.js` (Vanilla JS):**

  ```javascript
  document.addEventListener("DOMContentLoaded", () => {
    // --- Configuration & State ---
    const API_BASE = "./api/"; // Assuming api folder is relative
    const defaultRefreshInterval = 30; // seconds
    let currentDirectory = null;
    let fileListRefreshIntervalId = null;
    let maxUploadSizeBytes = 0;
    let basicAuthHeader = null; // Will be set on first interaction needing auth

    // --- DOM Elements ---
    const dirSelect = document.getElementById("directory-select");
    const fileInput = document.getElementById("file-input");
    const uploadButton = document.getElementById("upload-button");
    const statusMessages = document.getElementById("status-messages");
    const fileListContainer = document.getElementById("file-list-container");
    const maxSizeInfo = document.getElementById("max-size-info");
    const refreshToggle = document.getElementById("refresh-toggle");
    const refreshIntervalInput = document.getElementById("refresh-interval");
    const lastUpdatedSpan = document.getElementById("last-updated");

    // --- Utility Functions ---
    function formatBytes(bytes, decimals = 2) {
      if (bytes === 0) return "0 Bytes";
      const k = 1024;
      const dm = decimals < 0 ? 0 : decimals;
      const sizes = ["Bytes", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB"];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + " " + sizes[i];
    }

    function formatDate(timestamp) {
      if (!timestamp) return "-";
      try {
        const date = new Date(timestamp);
        // Make locale specific and more readable
        return date.toLocaleString(undefined, {
          dateStyle: "short",
          timeStyle: "short",
        });
      } catch (e) {
        return "-";
      }
    }

    function showStatus(message, isError = false) {
      statusMessages.textContent = message;
      statusMessages.className = isError ? "status-error" : "status-success";
      // Auto-clear after a few seconds?
      // setTimeout(() => statusMessages.textContent = '', 5000);
    }

    function getAuthHeader() {
      if (!basicAuthHeader) {
        // Prompt for credentials only once if needed, or assume browser handles it
        // For simplicity here, we assume the browser's basic auth popup will handle it
        // triggered by the 401 from the server if not already authenticated.
        // A more robust solution might involve storing credentials after first successful prompt.
        console.warn(
          "Attempting request without explicit auth header. Browser might prompt."
        );
        return {}; // Return empty header object
      }
      return { Authorization: basicAuthHeader };
    }

    // --- Cookie Functions ---
    function setCookie(name, value, days) {
      let expires = "";
      if (days) {
        const date = new Date();
        date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
        expires = "; expires=" + date.toUTCString();
      }
      // Ensure cookie applies to the whole app path
      document.cookie =
        name + "=" + (value || "") + expires + "; path=/; SameSite=Lax";
    }

    function getCookie(name) {
      const nameEQ = name + "=";
      const ca = document.cookie.split(";");
      for (let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) == " ") c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
      }
      return null;
    }

    // --- API Interaction ---
    async function fetchData(endpoint, options = {}) {
      const defaultHeaders = {
        Accept: "application/json",
        // Basic Auth will be handled by browser prompt on 401, or pre-set header if implemented
      };
      // If we had stored credentials (e.g., after a successful login/prompt)
      // const auth = getAuthHeader(); // Get {'Authorization': 'Basic ...'} or {}
      // options.headers = { ...defaultHeaders, ...auth, ...options.headers };

      options.headers = { ...defaultHeaders, ...options.headers };

      try {
        const response = await fetch(API_BASE + endpoint, options);

        if (response.status === 401) {
          showStatus(
            "Authentication required. Please refresh and enter credentials.",
            true
          );
          // Optionally try to trigger re-auth here, but browser default is usually sufficient
          throw new Error("Authentication Required");
        }
        if (!response.ok) {
          let errorMsg = `HTTP error! Status: ${response.status}`;
          try {
            // Try to get error message from JSON response body
            const errData = await response.json();
            errorMsg = errData.error || errorMsg;
          } catch (e) {
            /* Ignore if body isn't JSON */
          }
          throw new Error(errorMsg);
        }

        // Check content type before parsing JSON
        const contentType = response.headers.get("content-type");
        if (contentType && contentType.indexOf("application/json") !== -1) {
          return await response.json();
        } else {
          // Handle non-JSON responses if necessary, otherwise throw error
          throw new Error("Received non-JSON response from API");
        }
      } catch (error) {
        console.error("Fetch error:", error);
        showStatus(`Error: ${error.message}`, true);
        throw error; // Re-throw to allow calling function to handle
      }
    }

    async function fetchFilesAndDirectories(directory = null) {
      const endpoint = directory
        ? `list_files.php?dir=${encodeURIComponent(directory)}`
        : "list_files.php";
      try {
        const data = await fetchData(endpoint);
        if (data && data.success) {
          updateDirectorySelector(data.directories);
          renderFileList(data.files);
          updateMaxSizeInfo(data.max_upload_size_bytes);
          currentDirectory = directory || data.directories[0] || null; // Update current directory
          if (dirSelect.value !== currentDirectory && currentDirectory) {
            dirSelect.value = currentDirectory; // Sync dropdown
          }
          updateLastUpdated();
        } else {
          showStatus(data.error || "Failed to load file list.", true);
          renderFileList([]); // Show empty list on error
        }
      } catch (error) {
        // Error already shown by fetchData
        renderFileList([]); // Show empty list on error
      }
    }

    async function uploadFile() {
      const file = fileInput.files[0];
      const selectedDir = dirSelect.value;

      if (!file) {
        showStatus("Please select a file to upload.", true);
        return;
      }
      if (!selectedDir) {
        showStatus("Please select a target directory.", true);
        return;
      }
      if (maxUploadSizeBytes > 0 && file.size > maxUploadSizeBytes) {
        showStatus(
          `File exceeds maximum size (${formatBytes(maxUploadSizeBytes)}).`,
          true
        );
        return;
      }

      const formData = new FormData();
      formData.append("file", file);
      formData.append("directory", selectedDir);

      uploadButton.disabled = true;
      uploadButton.textContent = "Uploading...";
      showStatus("Uploading...", false);

      try {
        const data = await fetchData("upload.php", {
          method: "POST",
          body: formData,
          // Basic Auth headers handled by browser/fetch defaults on 401
        });

        if (data && data.success) {
          showStatus(data.message || "Upload successful!", false);
          fileInput.value = ""; // Clear file input
          await fetchFilesAndDirectories(selectedDir); // Refresh list
        } else {
          showStatus(data.error || "Upload failed.", true);
        }
      } catch (error) {
        // Error already shown by fetchData
        showStatus(`Upload failed: ${error.message}`, true);
      } finally {
        uploadButton.disabled = false;
        uploadButton.textContent = "Upload";
      }
    }

    // --- UI Rendering ---
    function updateDirectorySelector(directories) {
      const currentVal = dirSelect.value;
      // Check if options need updating (simple check based on length and first element)
      if (
        dirSelect.options.length !== directories.length ||
        (dirSelect.options.length > 0 &&
          dirSelect.options[0].value !== directories[0])
      ) {
        dirSelect.innerHTML = ""; // Clear existing options
        directories.forEach((dir) => {
          const option = document.createElement("option");
          option.value = dir;
          option.textContent = dir;
          dirSelect.appendChild(option);
        });
        // Try to restore previous selection if it still exists
        if (directories.includes(currentVal)) {
          dirSelect.value = currentVal;
        } else if (directories.length > 0) {
          dirSelect.value = directories[0]; // Default to first
        }
      }
      // Ensure currentDirectory state matches dropdown after potential update
      if (dirSelect.value) {
        currentDirectory = dirSelect.value;
      }
    }

    function renderFileList(files) {
      fileListContainer.innerHTML = ""; // Clear previous list

      if (!files || files.length === 0) {
        fileListContainer.innerHTML = "<p>No files in this directory.</p>";
        return;
      }

      const table = document.createElement("table");
      table.className = "file-table"; // Add class for styling
      const thead = table.createTHead();
      const headerRow = thead.insertRow();
      ["Name", "Size", "Last Modified", "Actions"].forEach((text) => {
        const th = document.createElement("th");
        th.textContent = text;
        headerRow.appendChild(th);
      });

      const tbody = table.createTBody();
      files.forEach((file) => {
        const row = tbody.insertRow();

        const nameCell = row.insertCell();
        nameCell.textContent = file.name;

        const sizeCell = row.insertCell();
        sizeCell.textContent = file.size_formatted || formatBytes(file.size); // Use pre-formatted if available

        const modifiedCell = row.insertCell();
        modifiedCell.textContent = formatDate(file.modified);

        const actionsCell = row.insertCell();
        const downloadLink = document.createElement("a");
        // Ensure directory name is included for download script
        downloadLink.href = `${API_BASE}download.php?dir=${encodeURIComponent(
          currentDirectory
        )}&file=${encodeURIComponent(file.name)}`;
        downloadLink.textContent = "Download";
        downloadLink.className = "button button-download"; // Add class for styling
        // downloadLink.setAttribute('download', file.name); // Optional: Suggest filename to browser
        actionsCell.appendChild(downloadLink);

        // Add delete button (Optional Feature)
        /*
               const deleteButton = document.createElement('button');
               deleteButton.textContent = 'Delete';
               deleteButton.className = 'button button-delete';
               deleteButton.onclick = () => handleDeleteFile(currentDirectory, file.name); // Need handleDeleteFile function and API endpoint
               actionsCell.appendChild(deleteButton);
               */
      });

      fileListContainer.appendChild(table);
    }

    function updateMaxSizeInfo(sizeBytes) {
      if (sizeBytes > 0) {
        maxUploadSizeBytes = sizeBytes;
        maxSizeInfo.textContent = `Max upload size: ${formatBytes(sizeBytes)}`;
      } else {
        maxSizeInfo.textContent =
          "Max upload size: Unlimited (or server default)";
      }
    }

    function updateLastUpdated() {
      lastUpdatedSpan.textContent = `Last updated: ${new Date().toLocaleTimeString()}`;
    }

    // --- Refresh Logic ---
    function stopAutoRefresh() {
      if (fileListRefreshIntervalId) {
        clearInterval(fileListRefreshIntervalId);
        fileListRefreshIntervalId = null;
        console.log("Auto-refresh stopped.");
      }
    }

    function startAutoRefresh(intervalSeconds) {
      stopAutoRefresh(); // Clear existing interval first
      const intervalMs = Math.max(5, intervalSeconds) * 1000; // Minimum 5 seconds
      if (intervalMs > 0) {
        fileListRefreshIntervalId = setInterval(() => {
          console.log(`Auto-refreshing directory: ${currentDirectory}`);
          if (currentDirectory) {
            fetchFilesAndDirectories(currentDirectory);
          }
        }, intervalMs);
        console.log(`Auto-refresh started with interval ${intervalSeconds}s.`);
      }
    }

    function setupRefreshControls() {
      const savedRefreshEnabled = getCookie("refreshEnabled") === "true";
      const savedInterval = parseInt(
        getCookie("refreshInterval") || defaultRefreshInterval,
        10
      );

      refreshToggle.checked = savedRefreshEnabled;
      refreshIntervalInput.value =
        savedInterval > 0 ? savedInterval : defaultRefreshInterval;

      refreshToggle.addEventListener("change", () => {
        const isEnabled = refreshToggle.checked;
        setCookie("refreshEnabled", isEnabled, 365); // Save for 1 year
        if (isEnabled) {
          const currentInterval = parseInt(refreshIntervalInput.value, 10);
          startAutoRefresh(currentInterval);
        } else {
          stopAutoRefresh();
        }
      });

      refreshIntervalInput.addEventListener("change", () => {
        let newInterval = parseInt(refreshIntervalInput.value, 10);
        if (isNaN(newInterval) || newInterval < 5) {
          newInterval = defaultRefreshInterval; // Reset to default if invalid
          refreshIntervalInput.value = newInterval;
        }
        setCookie("refreshInterval", newInterval, 365);
        // Restart refresh only if it's currently enabled
        if (refreshToggle.checked) {
          startAutoRefresh(newInterval);
        }
      });

      // Initial start if enabled
      if (savedRefreshEnabled) {
        startAutoRefresh(savedInterval);
      }
    }

    // --- Event Listeners ---
    dirSelect.addEventListener("change", (event) => {
      const newDir = event.target.value;
      if (newDir && newDir !== currentDirectory) {
        console.log(`Directory changed to: ${newDir}`);
        currentDirectory = newDir;
        renderFileList([]); // Clear list immediately
        showStatus("Loading files...", false);
        fetchFilesAndDirectories(newDir);
        // Restart refresh timer for the new directory if enabled
        if (refreshToggle.checked) {
          startAutoRefresh(parseInt(refreshIntervalInput.value, 10));
        }
      }
    });

    uploadButton.addEventListener("click", uploadFile);

    // Optional: Trigger upload on pressing Enter after selecting a file (enhancement)
    fileInput.addEventListener("keypress", (e) => {
      if (e.key === "Enter") {
        uploadFile();
      }
    });

    // --- Initialization ---
    function init() {
      console.log("Initializing File Uploader App...");
      showStatus("Loading configuration...", false);
      setupRefreshControls();
      fetchFilesAndDirectories(); // Initial load (gets directories and files for default/first dir)
      console.log("Initialization complete.");
    }

    init(); // Start the application
  });
  ```

**3. Configuration & Setup**

- **`.env`:**
  - Make sure `AUTH_USER`, `AUTH_PASS`, `BASE_UPLOAD_PATH`, `UPLOAD_DIRS`, `ALLOWED_EXTENSIONS`, `ALLOWED_MIME_TYPES` are set correctly.
  - **Add:** `MAX_FILE_SIZE=50M` (or your desired limit, e.g., `100M`, `1G`). Use `0` or omit for no limit other than PHP's config.
- **`composer.json`:**
  ```json
  {
    "require": {
      "vlucas/phpdotenv": "^5.0" // Or the appropriate version
    },
    "autoload": {
      "psr-4": {
        "App\\": "src/" // Optional: For namespacing if you add classes
      }
    }
  }
  ```
  Run `composer install` in the project root directory.
- **Web Server Configuration (Apache/Nginx):**
  - Set the document root to the `/public` directory.
  - Ensure `.env` and `src/` directory are _not_ accessible via the web.
  - Ensure PHP is configured correctly (file uploads enabled, `post_max_size` and `upload_max_filesize` set appropriately in `php.ini`).
  - For Apache, you might need `AllowOverride All` and a `.htaccess` in `public/` to handle routing or security headers if needed. Consider adding headers like `X-Content-Type-Options: nosniff`.
  - Basic Authentication support must be enabled in the webserver/PHP setup.

**Next Steps:**

1.  **Create the Directory Structure:** Set up the folders as outlined.
2.  **Copy/Create Files:** Place the code snippets into their respective files.
3.  **Implement CSS:** Style `style.css` based on the reference site. This will be the most visually intensive part. Extract logos/assets from the reference site and place them in `public/assets/`.
4.  **Configure `.env`:** Add your specific paths, credentials, allowed types, and the new `MAX_FILE_SIZE`.
5.  **Run `composer install`**.
6.  **Configure Web Server:** Point the web server to the `public` directory.
7.  **Test Thoroughly:** Check uploading, downloading, directory switching, error handling, refresh functionality, refresh settings persistence (cookies), and responsiveness across different browsers/devices.

This provides a solid foundation for the refactored application. Remember that the CSS implementation requires careful work to match the target design accurately. Let me know if any part needs further clarification!
