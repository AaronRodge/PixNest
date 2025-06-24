<?php
session_start();

// Create uploads folder if not exists
$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Debug settings
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Simple user system
$usersFile = 'users.json';
$users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
$authError = '';
$uploadError = '';

// Handle authentication
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auth'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (isset($users[$username])) {
        // Login
        if (password_verify($password, $users[$username])) {
            $_SESSION['user'] = $username;
        } else {
            $authError = "Incorrect password.";
        }
    } else {
        // Register
        $users[$username] = password_hash($password, PASSWORD_DEFAULT);
        file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
        $_SESSION['user'] = $username;
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: pixnest.php");
    exit;
}

// Handle image upload
if (isset($_SESSION['user']) && isset($_FILES['image'])) {
    if ($_FILES['image']['error'] === 0) {
        $fileType = mime_content_type($_FILES['image']['tmp_name']);
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

        if (in_array($fileType, $allowedTypes)) {
            $filename = uniqid() . '_' . basename($_FILES['image']['name']);
            $target = $uploadDir . $filename;

            if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                $uploadError = "Failed to move uploaded file.";
            }
        } else {
            $uploadError = "Only JPG, PNG, and GIF allowed.";
        }
    } else {
        $uploadError = "Upload error: code " . $_FILES['image']['error'];
    }
}

// Handle save/unsave actions
if (isset($_SESSION['user'])) {
    if (!isset($_SESSION['saved'])) $_SESSION['saved'] = [];
    if (isset($_POST['save_image'])) {
        $img = $_POST['save_image'];
        if (!in_array($img, $_SESSION['saved'])) {
            $_SESSION['saved'][] = $img;
        }
    }
    if (isset($_POST['unsave_image'])) {
        $img = $_POST['unsave_image'];
        $_SESSION['saved'] = array_values(array_diff($_SESSION['saved'], [$img]));
    }
    // Handle rating
    if (!isset($_SESSION['ratings'])) $_SESSION['ratings'] = [];
    if (isset($_POST['rate_image']) && isset($_POST['rating'])) {
        $img = $_POST['rate_image'];
        $rating = intval($_POST['rating']);
        if ($rating >= 1 && $rating <= 5) {
            $_SESSION['ratings'][$img] = $rating;
        }
    }
}

// Get all images
$images = glob("uploads/*.{jpg,jpeg,png,gif}", GLOB_BRACE);

// Determine which gallery to show
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'gallery';
$savedImages = isset($_SESSION['saved']) ? $_SESSION['saved'] : [];
$showImages = ($tab === 'saved') ? $savedImages : $images;
$ratings = isset($_SESSION['ratings']) ? $_SESSION['ratings'] : [];
?>

<!DOCTYPE html>
<html>
<head>
    <title>PixNest</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e0e7ff 100%);
            font-family: 'Segoe UI', 'Roboto', Arial, sans-serif;
            margin: 0;
            min-height: 100vh;
        }
        header {
            background: #e60023;
            color: #fff;
            padding: 1.5rem 0 1rem 0;
            text-align: center;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            letter-spacing: 2px;
        }
        .container {
            max-width: 1100px;
            margin: 2rem auto;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 32px rgba(0,0,0,0.08);
            padding: 2.5rem 2rem 2rem 2rem;
        }
        .registration-form, #upload-form {
            background: #f3f4f6;
            border-radius: 10px;
            padding: 2rem;
            max-width: 350px;
            margin: 2rem auto;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        .registration-form label, #upload-form label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        .registration-form input, #upload-form input {
            width: 100%;
            padding: 0.7rem;
            margin-bottom: 1.2rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
        }
        .registration-form button, #upload-form button, .save-btn, .unsave-btn, .download-btn {
            background: #e60023;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 0.7rem 1.3rem;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 0.5rem;
        }
        .registration-form button:hover, #upload-form button:hover, .save-btn:hover, .unsave-btn:hover, .download-btn:hover {
            background: #b8001c;
        }
        .menu-bar {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin: 1.5rem 0 1rem 0;
        }
        .menu-bar a {
            color: #e60023;
            text-decoration: none;
            font-weight: 600;
            padding: 0.5rem 1.2rem;
            border-radius: 6px;
            transition: background 0.2s, color 0.2s;
        }
        .menu-bar a.active, .menu-bar a:hover {
            background: #e60023;
            color: #fff;
        }
        .about-section {
            background: #f9fafb;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            padding: 2.5rem;
            margin: 2rem auto;
            max-width: 700px;
            text-align: center;
        }
        .error {
            color: #e60023;
            text-align: center;
            margin-top: 1rem;
            font-weight: 500;
        }
        .masonry {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 2rem;
            margin: 2rem 0;
        }
        .gallery-item {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.08);
            overflow: hidden;
            width: 100%;
            max-width: 320px;
            margin-bottom: 1rem;
            transition: transform 0.15s;
        }
        .card:hover {
            transform: translateY(-6px) scale(1.03);
            box-shadow: 0 6px 24px rgba(230,0,35,0.10);
        }
        .card img {
            width: 100%;
            height: 220px;
            object-fit: cover;
            display: block;
            background: #f3f4f6;
        }
        .card-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.7rem 1.2rem;
            background: #f9fafb;
        }
        .download-btn {
            background: #6366f1;
            margin-right: 0.5rem;
        }
        .download-btn:hover {
            background: #4338ca;
        }
        .save-btn[disabled] {
            background: #d1d5db;
            color: #888;
            cursor: not-allowed;
        }
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            font-size: 1.3rem;
            gap: 2px;
        }
        .star-rating input[type="radio"] {
            display: none;
        }
        .star-rating label {
            color: #ccc;
            cursor: pointer;
            transition: color 0.2s;
        }
        .star-rating input[type="radio"]:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #e60023;
        }
        .star-rating .rated {
            color: #e60023;
        }
        @media (max-width: 700px) {
            .container {
                padding: 1rem 0.2rem;
            }
            .about-section {
                padding: 1.2rem;
            }
            .card img {
                height: 140px;
            }
        }
    </style>
</head>
<body>
<header>
    <h1>PixNest</h1>
</header>
<div class="container">
<?php if (!isset($_SESSION['user'])): ?>
    <form method="post" class="registration-form">
        <input type="hidden" name="auth" value="1">
        <label>Username:</label>
        <input type="text" name="username" required>
        <label>Password:</label>
        <input type="password" name="password" required>
        <button type="submit">Register / Login</button>
    </form>
    <?php if ($authError): ?>
        <p class="error"><?= htmlspecialchars($authError) ?></p>
    <?php endif; ?>
<?php else: ?>
    <nav class="menu-bar">
        <a href="pixnest.php?tab=home" class="<?= $tab === 'home' ? 'active' : '' ?>">Home</a>
        <a href="pixnest.php?tab=gallery" class="<?= $tab === 'gallery' ? 'active' : '' ?>">Gallery</a>
        <a href="pixnest.php?tab=upload" class="<?= $tab === 'upload' ? 'active' : '' ?>">Upload</a>
        <a href="pixnest.php?tab=saved" class="<?= $tab === 'saved' ? 'active' : '' ?>">Saved</a>
        <a href="pixnest.php?tab=about" class="<?= $tab === 'about' ? 'active' : '' ?>">About</a>
        <a href="?logout">Logout</a>
    </nav>
    <p style="text-align:center;">Welcome, <strong><?= htmlspecialchars($_SESSION['user']) ?></strong>!</p>

    <?php if ($tab === 'upload'): ?>
        <form id="upload-form" method="post" enctype="multipart/form-data" style="text-align:center;">
            <input type="file" name="image" required>
            <button type="submit">Upload</button>
        </form>
        <?php if ($uploadError): ?>
            <p class="error"><?= htmlspecialchars($uploadError) ?></p>
        <?php endif; ?>
    <?php elseif ($tab === 'about'): ?>
        <div class="about-section">
            <h2>About PixNest</h2>
            <p>PixNest is a Pinterest-inspired image sharing platform. Upload, save, and rate your favorite images!</p>
            <p>Created for fun and inspiration. Enjoy!</p>
        </div>
    <?php elseif ($tab === 'home'): ?>
        <div class="about-section">
            <h2>Welcome to PixNest!</h2>
            <p>Discover, save, and rate beautiful images. Use the menu to browse the gallery, upload your own images, or view your saved collection.</p>
        </div>
    <?php else: ?>
        <h2 style="text-align:center;"><?= $tab === 'saved' ? 'Your Saved Images' : 'Gallery' ?></h2>
        <?php if ($showImages && count($showImages)): ?>
            <div class="masonry">
            <?php foreach ($showImages as $img): ?>
                <?php if (!file_exists($img)) continue; ?>
                <div class="gallery-item">
                    <div class="card">
                        <img src="<?= htmlspecialchars($img) ?>" alt="Uploaded">
                        <div class="card-actions">
                            <a href="<?= htmlspecialchars($img) ?>" download class="download-btn">Download</a>
                            <form method="post" style="display:inline;">
                                <?php if ($tab === 'saved'): ?>
                                    <input type="hidden" name="unsave_image" value="<?= htmlspecialchars($img) ?>">
                                    <button type="submit" class="unsave-btn">Unsave</button>
                                <?php else: ?>
                                    <?php if (in_array($img, $savedImages)): ?>
                                        <button type="button" class="save-btn" disabled>Saved</button>
                                    <?php else: ?>
                                        <input type="hidden" name="save_image" value="<?= htmlspecialchars($img) ?>">
                                        <button type="submit" class="save-btn">Save</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </form>
                        </div>
                        <div style="padding: 0.7rem 1.2rem; background: #fafafa;">
                            <form method="post" class="star-rating">
                                <input type="hidden" name="rate_image" value="<?= htmlspecialchars($img) ?>">
                                <?php
                                $userRating = isset($ratings[$img]) ? $ratings[$img] : 0;
                                for ($star = 5; $star >= 1; $star--):
                                ?>
                                    <input type="radio" id="star<?= $star . md5($img) ?>" name="rating" value="<?= $star ?>" <?= ($userRating == $star) ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <label for="star<?= $star . md5($img) ?>"<?= ($userRating >= $star) ? ' class="rated"' : '' ?>>&#9733;</label>
                                <?php endfor; ?>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="text-align:center;">No images yet.</p>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
</div>
</body>
</html>
