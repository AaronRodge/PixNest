<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Step 1: Confirm uploads folder
$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
    echo "<p>📂 'uploads/' folder created</p>";
} else {
    echo "<p>✅ 'uploads/' folder already exists</p>";
}

// Step 2: When form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>🧪 DEBUG: \$_FILES Dump</h2><pre>";
    print_r($_FILES);
    echo "</pre>";

    if (isset($_FILES['image'])) {
        $tmp = $_FILES['image']['tmp_name'];
        $name = basename($_FILES['image']['name']);
        $type = mime_content_type($tmp);
        $target = $uploadDir . $name;

        echo "<p>📝 Temp file: $tmp</p>";
        echo "<p>📄 Target: $target</p>";
        echo "<p>📦 MIME type: $type</p>";

        if ($_FILES['image']['error'] !== 0) {
            echo "<p style='color:red;'>❌ Upload error code: {$_FILES['image']['error']}</p>";
        } elseif (!in_array($type, ['image/jpeg', 'image/png', 'image/gif'])) {
            echo "<p style='color:red;'>❌ File type not allowed: $type</p>";
        } elseif (move_uploaded_file($tmp, $target)) {
            echo "<p style='color:green;'>✅ Upload successful!</p>";
        } else {
            echo "<p style='color:red;'>❌ Failed to move uploaded file!</p>";
        }
    } else {
        echo "<p style='color:red;'>❌ No file received.</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head><title>Debug Upload</title></head>
<body>
<h1>🔍 Upload Debugger</h1>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="image" required>
    <button type="submit">Test Upload</button>
</form>
</body>
</html>
