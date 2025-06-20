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
        .about-section {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            padding: 2rem;
            margin: 2rem auto;
            max-width: 700px;
            text-align: center;
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
