<?php
session_start();

// --- MySQL connection for user system ---
$host = 'localhost';
$db   = 'pixnest'; // Use the same DB as admin
$user = 'root'; // Change if needed
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
$pdo = null;
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // Create users table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL
    )");
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Create uploads folder if not exists
$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Debug settings
ini_set('display_errors', 1);
error_reporting(E_ALL);

$authError = '';
$uploadError = '';
$showRegister = isset($_GET['register']);

// Handle authentication
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auth'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user) {
        // Login
        if (password_verify($password, $user['password'])) {
            $_SESSION['user'] = $username;
        } else {
            $authError = "Incorrect password.";
        }
    } else {
        // Register
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare('INSERT INTO users (username, password) VALUES (?, ?)');
            $stmt->execute([$username, $hash]);
            $_SESSION['user'] = $username;
        } catch (PDOException $e) {
            $authError = "Username already exists.";
        }
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
            background: linear-gradient(120deg, #ffe2e6 0%, #f6f6f6 100%);
        }
        header {
            background: #fff;
            color: #e60023;
            padding: 1.5rem 0 1.2rem 0;
            text-align: center;
            margin-bottom: 2.5rem;
            letter-spacing: 2px;
            font-size: 2.5rem;
            font-weight: 800;
            box-shadow: 0 2px 12px rgba(230,0,35,0.07);
            border-radius: 0 0 32px 32px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }
        .user-menu {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 2.5rem;
        }
        .user-menu a, .user-menu button {
            background: #fff;
            color: #e60023;
            border: 1.5px solid #e60023;
            border-radius: 22px;
            padding: 0.55rem 1.5rem;
            font-size: 1.13rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s, color 0.2s, border 0.2s, box-shadow 0.2s;
            box-shadow: 0 2px 8px rgba(230,0,35,0.07);
        }
        .user-menu a.active, .user-menu a:hover, .user-menu button:hover {
            background: #e60023;
            color: #fff;
            box-shadow: 0 4px 16px rgba(230,0,35,0.13);
        }
        .user-form-block {
            max-width: 400px;
            margin: 2.5rem auto 1.5rem auto;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(230,0,35,0.10);
            padding: 2.5rem 2.5rem 2rem 2.5rem;
        }
        .user-form-block h2 {
            color: #e60023;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            font-weight: 700;
        }
        .user-form-block label {
            font-weight: 600;
            color: #444;
        }
        .user-form-block input {
            width: 100%;
            padding: 12px;
            margin-bottom: 22px;
            border: 1.5px solid #eee;
            border-radius: 8px;
            font-size: 1.13rem;
            background: #f9f9f9;
            transition: border 0.2s;
        }
        .user-form-block input:focus {
            border: 1.5px solid #e60023;
            outline: none;
        }
        .user-form-block button {
            width: 100%;
            margin-top: 0.5rem;
            background: #e60023;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.8rem 0;
            font-size: 1.13rem;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(230,0,35,0.10);
            transition: background 0.2s, box-shadow 0.2s;
        }
        .user-form-block button:hover {
            background: #ad081b;
            box-shadow: 0 4px 16px rgba(230,0,35,0.13);
        }
        .menu-bar {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 2rem;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            margin-bottom: 2.5rem;
            padding: 0.8rem 0;
        }
        .menu-bar a {
            color: #e60023;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.18rem;
            padding: 0.5rem 1.5rem;
            border-radius: 22px;
            transition: background 0.2s, color 0.2s;
        }
        .menu-bar a.active, .menu-bar a:hover {
            background: #e60023;
            color: #fff;
        }
        .error {
            color: #e60023;
            margin-top: 1rem;
            font-weight: bold;
            text-align: center;
        }
        .about-section {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            padding: 2.5rem;
            margin: 2.5rem auto;
            max-width: 700px;
            text-align: center;
        }
        .masonry {
            column-count: 4;
            column-gap: 1.5rem;
            margin-top: 2rem;
        }
        @media (max-width: 1100px) { .masonry { column-count: 3; } }
        @media (max-width: 800px) { .masonry { column-count: 2; } }
        @media (max-width: 500px) { .masonry { column-count: 1; } }
        .gallery-item {
            break-inside: avoid;
            margin-bottom: 1.5rem;
        }
        .card {
            background: #fff;
            border-radius: 22px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.13);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: box-shadow 0.25s, transform 0.2s;
            position: relative;
            border: 1px solid #f3f3f3;
        }
        .card:hover {
            box-shadow: 0 12px 32px rgba(0,0,0,0.18);
            transform: translateY(-6px) scale(1.025);
        }
        .card img {
            width: 100%;
            display: block;
            border-bottom: 1px solid #eee;
            max-height: 420px;
            object-fit: cover;
            background: #f0f0f0;
            transition: filter 0.2s;
        }
        .card img:hover {
            filter: brightness(0.95);
        }
        .card-actions {
            padding: 0.9rem 1.2rem;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 12px;
            background: #fafafa;
        }
        .download-btn {
            background: #e60023;
            color: #fff;
            padding: 0.45rem 1.3rem;
            border: none;
            border-radius: 22px;
            text-decoration: none;
            font-size: 1.05rem;
            font-weight: 600;
            box-shadow: 0 1px 4px rgba(230,0,35,0.10);
            transition: background 0.2s, box-shadow 0.2s, transform 0.1s;
            cursor: pointer;
        }
        .download-btn:hover {
            background: #ad081b;
            box-shadow: 0 2px 8px rgba(230,0,35,0.13);
            transform: scale(1.07);
        }
        .save-btn, .unsave-btn {
            border-radius: 22px;
            padding: 0.45rem 1.3rem;
            font-size: 1.05rem;
            font-weight: 600;
            border: 1.5px solid #e60023;
            transition: background 0.2s, color 0.2s, border 0.2s, transform 0.1s;
            cursor: pointer;
        }
        .save-btn {
            background: #fff;
            color: #e60023;
        }
        .save-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            background: #f6f6f6;
            color: #aaa;
            border-color: #eee;
        }
        .save-btn:hover:not(:disabled) {
            background: #ffe2e6;
            color: #ad081b;
            transform: scale(1.07);
        }
        .unsave-btn {
            color: #fff;
            background: #e60023;
        }
        .unsave-btn:hover {
            background: #ad081b;
            border-color: #ad081b;
            transform: scale(1.07);
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
    <div class="user-menu">
        <a href="pixnest.php" class="<?= !$showRegister ? 'active' : '' ?>">Existing User</a>
        <a href="pixnest.php?register" class="<?= $showRegister ? 'active' : '' ?>">New User?</a>
    </div>
    <?php if ($showRegister): ?>
        <form method="post" class="user-form-block">
            <h2>Register</h2>
            <input type="hidden" name="auth" value="1">
            <label>Username:</label>
            <input type="text" name="username" required>
            <label>Password:</label>
            <input type="password" name="password" required>
            <button type="submit">Register</button>
        </form>
    <?php else: ?>
        <form method="post" class="user-form-block">
            <h2>Login</h2>
            <input type="hidden" name="auth" value="1">
            <label>Username:</label>
            <input type="text" name="username" required>
            <label>Password:</label>
            <input type="password" name="password" required>
            <button type="submit">Login</button>
        </form>
    <?php endif; ?>
    <?php if ($authError): ?>
        <p class="error"> <?= htmlspecialchars($authError) ?> </p>
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
