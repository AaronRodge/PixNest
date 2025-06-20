<?php include 'db.php';

$error = ""; // Initialize error message

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $title = htmlspecialchars(trim($_POST['title']));
    $file = $_FILES['image'];
    $filename = uniqid() . "_" . basename($file['name']);
    $targetPath = __DIR__ . "/images/" . $filename; // Ensure absolute path

    // Ensure the images folder exists and is writable
    if (!is_dir(__DIR__ . "/images")) {
        mkdir(__DIR__ . "/images", 0755, true); // Create the folder if it doesn't exist
    }

    if (!is_writable(__DIR__ . "/images")) {
        $error = "The images folder is not writable.";
    } else {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = "File upload error. Code: " . $file['error'];
        } else {
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            $fileType = mime_content_type($file['tmp_name']);

            if (in_array($fileType, $allowedTypes)) {
                // Move uploaded file to the target directory
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    // Insert metadata into the database
                    $stmt = $mysqli->prepare("INSERT INTO images (filename, title) VALUES (?, ?)");
                    $stmt->bind_param("ss", $filename, $title);
                    $stmt->execute();
                    header("Location: index.php");
                    exit;
                } else {
                    $error = "Failed to move the uploaded file.";
                    // Debugging output
                    echo "Temporary file: " . $file['tmp_name'] . "<br>";
                    echo "Target path: " . $targetPath . "<br>";
                    echo "File type: " . $fileType . "<br>";
                    echo "Error code: " . $file['error'] . "<br>";
                    exit;
                }
            } else {
                $error = "Only JPEG, JPG, and PNG formats are allowed.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Image</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header>
    <h1>Welcome to PixNest</h1>
    <nav>
        <a href="index.php">Home</a>
    </nav>
</header>
<div class="container">
    <div class="registration-form">
        <h1>Upload Image</h1>
        <form method="post" enctype="multipart/form-data">
            <label>Title:</label>
            <input name="title" required>
            <label>Image:</label>
            <input type="file" name="image" required>
            <button>Upload</button>
        </form>
        <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
    </div>
</div>
</body>
</html>

