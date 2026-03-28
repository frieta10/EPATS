<?php
// ============================================================
// E-Invitation Platform Configuration
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'e_invitation');

// Detect base URL automatically
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script   = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
// Walk up to find E-Invitation root
$baseDir  = '';
$parts    = explode('/', trim($script, '/'));
foreach ($parts as $part) {
    $baseDir .= '/' . $part;
    if (strtolower($part) === 'e-invitation') break;
}
define('BASE_URL', $protocol . '://' . $host . $baseDir);
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');
define('ADMIN_PATH', __DIR__ . '/admin/');

// ============================================================
// Database Connection (singleton)
// ============================================================
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            die('<div style="font-family:sans-serif;padding:40px;text-align:center;"><h2>Database Error</h2><p>Could not connect to the database. Please run <code>database.sql</code> first.</p><pre>' . htmlspecialchars($e->getMessage()) . '</pre></div>');
        }
    }
    return $pdo;
}

// ============================================================
// Get all settings as key => value array
// ============================================================
function getSettings(): array {
    static $cache = null;
    if ($cache === null) {
        try {
            $rows  = getDB()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
            $cache = array_column($rows, 'setting_value', 'setting_key');
        } catch (Exception $e) {
            $cache = [];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string {
    $s = getSettings();
    return $s[$key] ?? $default;
}

// ============================================================
// Generate cryptographically secure token
// ============================================================
function generateToken(int $length = 32): string {
    return bin2hex(random_bytes($length));
}

// ============================================================
// Sanitize HTML output
// ============================================================
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// ============================================================
// Admin session check
// ============================================================
function requireAdmin(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
}

// ============================================================
// Upload helper
// ============================================================
function handleUpload(array $file, string $prefix = 'img'): ?string {
    $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize  = 10 * 1024 * 1024; // 10 MB

    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if (!in_array($file['type'], $allowed))  return null;
    if ($file['size'] > $maxSize)            return null;

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
    $dest     = UPLOAD_PATH . $filename;

    if (!is_dir(UPLOAD_PATH)) mkdir(UPLOAD_PATH, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $dest)) return null;

    return $filename;
}

// ============================================================
// Format date nicely
// ============================================================
function formatDate(string $date): string {
    $ts = strtotime($date);
    return $ts ? date('d F Y', $ts) : $date;
}

function formatDateShort(string $date): string {
    $ts = strtotime($date);
    return $ts ? date('d M Y', $ts) : $date;
}
