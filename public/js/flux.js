document.addEventListener("DOMContentLoaded", function () {
  document.getElementById("upload").addEventListener("click", function () {
    let fileInput = document.getElementById("files");
    let files = fileInput.files;
    if (files.length === 0) {
      alert("Please select files to upload.");
      return;
    }

    let formData = new FormData();
    for (let i = 0; i < files.length; i++) {
      formData.append("files[]", files[i]);
    }

    let xhr = new XMLHttpRequest();
    xhr.open("POST", "upload.php", true);

    // Get the selected upload directory if it exists
    let uploadDirectory = document.getElementById("upload_directory");
    if (uploadDirectory) {
      formData.append("upload_directory", uploadDirectory.value);
    }

    xhr.onload = function () {
      if (xhr.status === 200) {
        let res = JSON.parse(xhr.responseText);
        let statusElement = document.getElementById("status");
        statusElement.innerHTML = "";
        res.forEach((file) => {
          let p = document.createElement("p");
          p.textContent = `${file.file}: ${file.status}`;
          statusElement.appendChild(p);
        });
      }
    };

    xhr.onerror = function () {
      document.getElementById("status").innerHTML =
        "<p>Error uploading files.</p>";
    };

    xhr.send(formData);
  });
});

function validateFileSize(input, maxSize) {
  console.log("input.files: ", input.files);
  const files = input.files;
  let totalSize = 0;

  // Check individual file sizes
  for (let i = 0; i < files.length; i++) {
    if (files[i].size > maxSize) {
      alert(
        `File "${
          files[i].name
        }" exceeds the maximum upload size of ${formatBytes(maxSize)}.`
      );
      input.value = ""; // Clear the input
      return false;
    }
    totalSize += files[i].size;
  }

  // Check combined file size
  if (totalSize > maxSize) {
    alert(
      `The combined size of all files (${formatBytes(
        totalSize
      )}) exceeds the maximum upload size of ${formatBytes(maxSize)}.`
    );
    input.value = ""; // Clear the input
    return false;
  }

  return true;
}

function formatBytes(bytes) {
  if (bytes === 0) return "0 Bytes";
  const k = 1024;
  const sizes = ["Bytes", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
}
