<?php
// ============================================================
// E-Invitation Platform Configuration
// Supports both local (Laragon) and Vercel deployments
// ============================================================

// ── Database (PostgreSQL) ─────────────────────────────────
// Supports Vercel native POSTGRES_* vars (auto-injected by Vercel Storage)
// OR manual DB_* vars set in Vercel env settings
define('DB_HOST',    getenv('POSTGRES_HOST')     ?: getenv('DB_HOST')     ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT')            ?: '5432');
define('DB_USER',    getenv('POSTGRES_USER')      ?: getenv('DB_USER')     ?: 'postgres');
define('DB_PASS',    getenv('POSTGRES_PASSWORD')  ?: getenv('DB_PASS')     ?: '');
define('DB_NAME',    getenv('POSTGRES_DATABASE')  ?: getenv('DB_NAME')     ?: 'e_invitation');
define('DB_SSLMODE', getenv('DB_SSLMODE')         ?: 'require');
// Full connection URL (Vercel Postgres injects this automatically)
define('POSTGRES_URL_NON_POOLING', getenv('POSTGRES_URL_NON_POOLING') ?: '');

// ── Base URL ──────────────────────────────────────────────
// Vercel sets VERCEL_URL automatically (without protocol)
$_vercelUrl = getenv('VERCEL_URL');
$_appUrl    = getenv('APP_URL');

if ($_appUrl) {
    // Explicit override takes priority
    define('BASE_URL', rtrim($_appUrl, '/'));
} elseif ($_vercelUrl) {
    // Running on Vercel
    define('BASE_URL', 'https://' . $_vercelUrl);
} else {
    // Local development (Laragon)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script   = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    $baseDir  = '';
    $parts    = explode('/', trim($script, '/'));
    foreach ($parts as $part) {
        if (empty($part)) continue;
        $baseDir .= '/' . $part;
        if (in_array(strtolower($part), ['epats', 'e-invitation'])) break;
    }
    define('BASE_URL', $protocol . '://' . $host . $baseDir);
}

// ── Upload paths ──────────────────────────────────────────
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('UPLOAD_URL',  BASE_URL . '/uploads/');
define('ADMIN_PATH',  __DIR__ . '/admin/');

// ── Vercel detection ──────────────────────────────────────
define('IS_VERCEL', (bool) getenv('VERCEL'));

// ============================================================
// Load helpers
// ============================================================
require_once __DIR__ . '/includes/cloudinary.php';
require_once __DIR__ . '/includes/db_session.php';

// ============================================================
// Database Connection (singleton)
// ============================================================
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            // Prefer full connection URL when Vercel Storage injects it
            if (POSTGRES_URL_NON_POOLING) {
                $parsed = parse_url(POSTGRES_URL_NON_POOLING);
                $dsn = sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s;sslmode=require',
                    $parsed['host'],
                    $parsed['port'] ?? 5432,
                    ltrim($parsed['path'] ?? '/neondb', '/')
                );
                $user = $parsed['user'] ?? DB_USER;
                $pass = $parsed['pass'] ?? DB_PASS;
            } else {
                $dsn  = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=%s', DB_HOST, DB_PORT, DB_NAME, DB_SSLMODE);
                $user = DB_USER;
                $pass = DB_PASS;
            }
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec("SET client_encoding TO 'UTF8'");
        } catch (PDOException $e) {
            http_response_code(500);
            die('<div style="font-family:sans-serif;padding:40px;text-align:center;"><h2>Database Error</h2><p>Could not connect to the database. Please check your environment variables or run <code>setup.php</code> first.</p><pre>' . htmlspecialchars($e->getMessage()) . '</pre></div>');
        }
    }
    return $pdo;
}

// ============================================================
// Settings helpers
// ============================================================
function getSettings(): array {
    static $cache = null;
    if ($cache === null) {
        try {
            // Check if we're in multi-event mode (event_settings table exists)
            $db = getDB();
            $check = $db->query("SELECT 1 FROM pg_tables WHERE tablename = 'event_settings'");
            if ($check && $check->fetch()) {
                // Multi-event mode: get settings for current event
                $eventId = getCurrentEventId();
                $rows = $db->prepare('SELECT setting_key, setting_value FROM event_settings WHERE event_id = ?');
                $rows->execute([$eventId]);
                $cache = array_column($rows->fetchAll(), 'setting_value', 'setting_key');
            } else {
                // Legacy mode: global settings
                $rows  = $db->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
                $cache = array_column($rows, 'setting_value', 'setting_key');
            }
        } catch (Exception $e) {
            $cache = [];
        }
    }
    return $cache;
}

// ============================================================
// Multi-Event helpers
// ============================================================
function getCurrentEventId(): int {
    startDbSession();
    if (!empty($_SESSION['current_event_id'])) {
        return (int) $_SESSION['current_event_id'];
    }
    // Default to first active event
    try {
        $db = getDB();
        $id = $db->query("SELECT id FROM events WHERE is_active = TRUE ORDER BY id LIMIT 1")->fetchColumn();
        if ($id) {
            $_SESSION['current_event_id'] = (int) $id;
            return (int) $id;
        }
    } catch (Exception $e) {
        // Table might not exist yet
    }
    return 0;
}

function setCurrentEventId(int $eventId): void {
    startDbSession();
    $_SESSION['current_event_id'] = $eventId;
}

function getEvents(): array {
    try {
        return getDB()->query("SELECT * FROM events ORDER BY created_at DESC")->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getEvent(int $id): ?array {
    try {
        $stmt = getDB()->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function createEvent(string $name, string $slug, string $ceremonyType = 'Wedding'): int {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO events (name, slug, ceremony_type) VALUES (?, ?, ?) RETURNING id");
    $stmt->execute([$name, $slug, $ceremonyType]);
    return (int) $stmt->fetchColumn();
}

function deleteEvent(int $eventId): void {
    $db = getDB();
    // Delete event (cascades to related data)
    $db->prepare("DELETE FROM events WHERE id = ?")->execute([$eventId]);
    // Clear current event if it was this one
    if (getCurrentEventId() === $eventId) {
        unset($_SESSION['current_event_id']);
    }
}

function setting(string $key, string $default = ''): string {
    return getSettings()[$key] ?? $default;
}

// ============================================================
// Token generator
// ============================================================
function generateToken(int $length = 32): string {
    return bin2hex(random_bytes($length));
}

// ============================================================
// HTML sanitiser
// ============================================================
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// ============================================================
// Admin session guard
// ============================================================
function requireAdmin(): void {
    startDbSession();
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
}

// ============================================================
// Image URL resolver
// Works with both legacy filenames and full Cloudinary URLs
// ============================================================
function getImageUrl(string $value): string {
    if (empty($value)) return '';
    if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
        return $value; // Already a full URL (Cloudinary)
    }
    return UPLOAD_URL . $value; // Legacy local filename
}

// ============================================================
// Upload helper
// Uses Cloudinary when env vars are present, local disk otherwise
// ============================================================
function handleUpload(array $file, string $prefix = 'img'): ?string {
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 10 * 1024 * 1024; // 10 MB

    if ($file['error'] !== UPLOAD_ERR_OK)        return null;
    if (!in_array($file['type'], $allowed))       return null;
    if ($file['size'] > $maxSize)                 return null;

    // ── Cloudinary (Vercel / production) ──────────────────
    if (cloudinaryEnabled()) {
        $url = uploadToCloudinary($file['tmp_name'], 'epats/' . $prefix);
        return $url; // Returns full https:// URL or null
    }

    // ── Local disk (Laragon / development) ────────────────
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest     = UPLOAD_PATH . $filename;

    if (!is_dir(UPLOAD_PATH)) mkdir(UPLOAD_PATH, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $dest)) return null;

    return $filename;
}

// ============================================================
// Date formatters
// ============================================================
function formatDate(string $date): string {
    $ts = strtotime($date);
    return $ts ? date('d F Y', $ts) : $date;
}

function formatDateShort(string $date): string {
    $ts = strtotime($date);
    return $ts ? date('d M Y', $ts) : $date;
}
