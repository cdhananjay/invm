<?php
/**
 * Database Configuration File
 * Contains database connection settings and helper functions
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'inventory_db');

// Application settings
define('APP_NAME', 'StockArch');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Create database connection
function getDbConnection() {
    static $conn = null;

    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        $conn->set_charset("utf8mb4");
    }

    return $conn;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Require login - redirect to login page if not authenticated
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Get current user ID
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user data
 * @return array|null
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    return $user;
}

/**
 * Redirect if already logged in
 */
function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        header("Location: dashboard.php");
        exit;
    }
}

/**
 * Sanitize input data
 * @param string $data
 * @return string
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Generate a secure random string
 * @param int $length
 * @return string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Handle file upload
 * @param array $file $_FILES array element
 * @param string $targetDir Target directory
 * @return array ['success' => bool, 'path' => string, 'error' => string]
 */
function handleFileUpload($file, $targetDir = UPLOAD_DIR) {
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $maxSize = MAX_FILE_SIZE;

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        ];
        $errorMsg = $errorMessages[$file['error']] ?? 'Unknown upload error';
        return ['success' => false, 'path' => '', 'error' => $errorMsg];
    }

    // Validate file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        return ['success' => false, 'path' => '', 'error' => 'Invalid file type. Only JPG, PNG, GIF, WebP allowed'];
    }

    // Validate file size
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'path' => '', 'error' => 'File size exceeds 10MB limit'];
    }

    // Generate unique filename
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $targetPath = $targetDir . $filename;

    // Ensure target directory exists and is writable
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0755, true)) {
            return ['success' => false, 'path' => '', 'error' => 'Cannot create uploads directory'];
        }
    }

    // Check if directory is writable
    if (!is_writable($targetDir)) {
        return ['success' => false, 'path' => '', 'error' => 'Uploads directory is not writable. Check permissions.'];
    }

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'path' => 'uploads/' . $filename, 'error' => ''];
    }

    return ['success' => false, 'path' => '', 'error' => 'Failed to save uploaded file. Check directory permissions.'];
}

/**
 * Format currency
 * @param float $amount
 * @return string
 */
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

/**
 * Format large numbers with K suffix
 * @param float $number
 * @return string
 */
function formatLargeNumber($number) {
    if ($number >= 1000000) {
        return '₹' . number_format($number / 100000, 1) . 'L';
    } elseif ($number >= 1000) {
        return '₹' . number_format($number / 1000, 1) . 'k';
    }
    return '₹' . number_format($number, 2);
}
?>
