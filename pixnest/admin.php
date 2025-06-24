<?php
session_start();

// --- MySQL connection for admin users ---
$host = 'localhost';
$db   = 'pixnest'; // Make sure this DB exists
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
    // Create admin table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL
    )");
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

$adminError = '';
$showRegister = isset($_GET['register']);

// Handle admin registration (for first time setup)
if (isset($_POST['admin_register'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    if ($username && $password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare('INSERT INTO admins (username, password) VALUES (?, ?)');
            $stmt->execute([$username, $hash]);
            $adminError = 'Admin registered. You can now login.';
            $showRegister = false;
        } catch (PDOException $e) {
            $adminError = 'Username already exists.';
        }
    } else {
        $adminError = 'Please fill all fields.';
    }
}

// Handle admin login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password']) && !isset($_POST['admin_register'])) {
    $user = trim($_POST['username']);
    $pass = trim($_POST['password']);
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = ?');
    $stmt->execute([$user]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($pass, $admin['password'])) {
        $_SESSION['is_admin'] = $admin['username'];
    } else {
        $adminError = 'Invalid admin credentials.';
    }
}

// Handle admin logout
if (isset($_GET['admin_logout'])) {
    unset($_SESSION['is_admin']);
    header('Location: admin.php');
    exit;
}

// Handle delete image
if (isset($_SESSION['is_admin']) && isset($_POST['delete_image'])) {
    $img = $_POST['delete_image'];
    if (file_exists($img) && strpos($img, 'uploads/') === 0) {
        unlink($img);
    }
}

// Get all images
$images = glob('uploads/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
?>
<!DOCTYPE html>
<html>
<head>
    <title>PixNest Admin</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            background: linear-gradient(120deg, #ffe2e6 0%, #f6f6f6 100%);
        }
        .admin-header {
            text-align:center;
            margin:2.5rem 0 1.5rem 0;
            font-size:2.3rem;
            font-weight:700;
            letter-spacing:2px;
            color:#e60023;
        }
        .admin-logout {
            float:right;
            margin-right:2rem;
            background: #fff;
            color: #e60023;
            border: 1.5px solid #e60023;
            border-radius: 22px;
            padding: 0.45rem 1.3rem;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, color 0.2s, border 0.2s;
            text-decoration: none;
        }
        .admin-logout:hover {
            background: #e60023;
            color: #fff;
        }
        .delete-btn {
            background: #e60023;
            color: #fff;
            border: none;
            border-radius: 22px;
            padding: 0.45rem 1.3rem;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, box-shadow 0.2s, transform 0.1s;
        }
        .delete-btn:hover {
            background: #ad081b;
            transform: scale(1.07);
        }
        .admin-menu {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 2.5rem;
        }
        .admin-menu a, .admin-menu button {
            background: #fff;
            color: #e60023;
            border: 1.5px solid #e60023;
            border-radius: 22px;
            padding: 0.45rem 1.3rem;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s, color 0.2s, border 0.2s;
        }
        .admin-menu a.active, .admin-menu a:hover, .admin-menu button:hover {
            background: #e60023;
            color: #fff;
        }
        .admin-form-block {
            max-width: 370px;
            margin: 2.5rem auto 1.5rem auto;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 16px rgba(230,0,35,0.07);
            padding: 2.2rem 2.2rem 1.5rem 2.2rem;
        }
        .admin-form-block h2 {
            color: #e60023;
            margin-bottom: 1.2rem;
            font-size: 1.4rem;
        }
        .admin-form-block label {
            font-weight: 600;
            color: #444;
        }
        .admin-form-block input {
            width: 100%;
            padding: 10px;
            margin-bottom: 18px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1.05rem;
        }
        .admin-form-block button {
            width: 100%;
            margin-top: 0.5rem;
        }
        .admin-gallery-title {
            text-align:center;
            font-size:1.5rem;
            color:#e60023;
            margin-bottom:1.5rem;
            margin-top:1.5rem;
            letter-spacing:1px;
        }
        .error {
            color: #e60023;
            margin-top: 1rem;
            font-weight: bold;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="container">
    <h1 class="admin-header">PixNest Admin Panel</h1>
    <div class="admin-menu">
        <a href="admin.php" class="<?= !$showRegister ? 'active' : '' ?>">Login</a>
        <a href="admin.php?register" class="<?= $showRegister ? 'active' : '' ?>">New Admin?</a>
    </div>
    <?php if (!isset($_SESSION['is_admin'])): ?>
        <?php if ($showRegister): ?>
            <form method="post" class="admin-form-block">
                <h2>Register New Admin</h2>
                <label>Username:</label>
                <input type="text" name="username" required>
                <label>Password:</label>
                <input type="password" name="password" required>
                <button type="submit" name="admin_register">Register</button>
            </form>
        <?php else: ?>
            <form method="post" class="admin-form-block">
                <h2>Admin Login</h2>
                <label>Username:</label>
                <input type="text" name="username" required>
                <label>Password:</label>
                <input type="password" name="password" required>
                <button type="submit">Login</button>
            </form>
        <?php endif; ?>
        <?php if ($adminError): ?>
            <p class="error"> <?= htmlspecialchars($adminError) ?> </p>
        <?php endif; ?>
    <?php else: ?>
        <a href="?admin_logout" class="admin-logout">Logout</a>
        <h2 class="admin-gallery-title">All Uploaded Images</h2>
        <?php if ($images): ?>
            <div class="masonry">
            <?php foreach ($images as $img): ?>
                <?php if (!file_exists($img)) continue; ?>
                <div class="gallery-item">
                    <div class="card">
                        <img src="<?= htmlspecialchars($img) ?>" alt="Uploaded">
                        <div class="card-actions">
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="delete_image" value="<?= htmlspecialchars($img) ?>">
                                <button type="submit" class="delete-btn" onclick="return confirm('Delete this image?')">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="text-align:center;">No images found.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
