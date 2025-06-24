<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<pre>";
    print_r($_FILES);
    echo "</pre>";

    $uploadDir = 'uploads/';
    $filename = basename($_FILES['image']['name']);
    $targetPath = $uploadDir . $filename;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
        echo "✅ Upload successful!";
    } else {
        echo "❌ Upload failed.";
    }
}
?>

<!DOCTYPE html>
<html>
<head><title>Upload Test</title></head>
<body>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="image" required>
        <button type="submit">Upload</button>
    </form>
</body>
</html>
