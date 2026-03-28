<?php
/**
 * One-time setup script — run once to create all database tables.
 * Visit: https://your-site.vercel.app/setup.php
 * Delete or rename this file after setup is complete.
 */

// Load env vars if running locally
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        putenv(trim($k) . '=' . trim($v));
    }
}

define('IS_VERCEL',  (bool) getenv('VERCEL'));
define('DB_HOST',    getenv('POSTGRES_HOST')    ?: getenv('DB_HOST')    ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT')           ?: '5432');
define('DB_USER',    getenv('POSTGRES_USER')     ?: getenv('DB_USER')    ?: 'postgres');
define('DB_PASS',    getenv('POSTGRES_PASSWORD') ?: getenv('DB_PASS')    ?: '');
define('DB_NAME',    getenv('POSTGRES_DATABASE') ?: getenv('DB_NAME')    ?: 'e_invitation');
define('DB_SSLMODE', getenv('DB_SSLMODE')        ?: 'require');
define('POSTGRES_URL_NON_POOLING', getenv('POSTGRES_URL_NON_POOLING') ?: '');

$log = [];
$ok  = true;

try {
    if (POSTGRES_URL_NON_POOLING) {
        $parsed = parse_url(POSTGRES_URL_NON_POOLING);
        $dsn  = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=require',
            $parsed['host'], $parsed['port'] ?? 5432, ltrim($parsed['path'] ?? '/neondb', '/'));
        $dbUser = $parsed['user'] ?? DB_USER;
        $dbPass = $parsed['pass'] ?? DB_PASS;
    } else {
        $dsn    = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=%s', DB_HOST, DB_PORT, DB_NAME, DB_SSLMODE);
        $dbUser = DB_USER;
        $dbPass = DB_PASS;
    }
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("SET client_encoding TO 'UTF8'");
    $log[] = ['ok', 'Connected to PostgreSQL database: ' . DB_NAME];

    // Run SQL file — split on semicolons and execute each statement
    $sql = file_get_contents(__DIR__ . '/database.sql');

    // Remove single-line comments to avoid splitting on ";" in comments
    $sql = preg_replace('/--[^\n]*\n/', "\n", $sql);

    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $count = 0;
    foreach ($statements as $stmt) {
        if ($stmt) {
            $pdo->exec($stmt);
            $count++;
        }
    }
    $log[] = ['ok', "Executed {$count} SQL statements — tables ready"];

    // Set correct admin password hash at runtime
    $correctHash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->prepare(
        "INSERT INTO admin_users (username, password_hash)
         VALUES ('admin', ?)
         ON CONFLICT (username) DO UPDATE SET password_hash = EXCLUDED.password_hash"
    )->execute([$correctHash]);
    $log[] = ['ok', 'Admin credentials set (admin / admin123)'];

    // Create uploads dir for local dev
    if (!IS_VERCEL) {
        $uploadsDir = __DIR__ . '/uploads';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
            $log[] = ['ok', 'uploads/ directory created'];
        }
        $htaccess = $uploadsDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Options -Indexes\n<FilesMatch '\\.php$'>\n  Deny from all\n</FilesMatch>\n");
            $log[] = ['ok', 'uploads/.htaccess written'];
        }
    } else {
        $log[] = ['ok', 'Vercel detected — skipping local uploads directory'];
    }

} catch (PDOException $e) {
    $log[] = ['error', 'Database error: ' . $e->getMessage()];
    $ok = false;
}

// ── Detect base URL for buttons ──────────────────────────────
$vercelUrl = getenv('VERCEL_URL');
$baseUrl   = $vercelUrl ? 'https://' . $vercelUrl : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setup — E-Invitation</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #1a0a14; color: #e8d5bc; font-family: 'Montserrat', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
  .card { background: #240d1c; border: 1px solid rgba(201,168,76,.2); border-radius: 16px; padding: 2.5rem; max-width: 600px; width: 100%; }
  .logo { text-align: center; margin-bottom: 2rem; }
  .logo .icon { font-size: 3rem; }
  .logo h1 { font-size: 1.5rem; color: #F0D080; margin-top: .5rem; }
  .logo p  { font-size: .75rem; letter-spacing: .2em; text-transform: uppercase; color: #9a7e68; margin-top: .25rem; }
  .log-item { display: flex; align-items: flex-start; gap: .75rem; padding: .6rem 0; border-bottom: 1px solid rgba(255,255,255,.04); font-size: .82rem; }
  .log-item:last-child { border: none; }
  .dot { width: 10px; height: 10px; border-radius: 50%; margin-top: 4px; flex-shrink: 0; }
  .dot-ok    { background: #68d391; }
  .dot-error { background: #fc8181; }
  .actions { margin-top: 2rem; display: flex; gap: 1rem; flex-wrap: wrap; }
  .btn { display: inline-flex; align-items: center; gap: .4rem; padding: .6rem 1.4rem; border-radius: 8px; font-size: .8rem; font-weight: 600; cursor: pointer; text-decoration: none; }
  .btn-primary { background: #C9A84C; color: #1a0a14; }
  .btn-outline { border: 1px solid rgba(201,168,76,.3); color: #C9A84C; }
  .status-banner { padding: 1rem 1.25rem; border-radius: 10px; margin-bottom: 1.5rem; font-size: .85rem; display: flex; align-items: center; gap: .75rem; }
  .status-ok    { background: rgba(72,187,120,.1); border: 1px solid rgba(72,187,120,.3); color: #68d391; }
  .status-error { background: rgba(252,129,129,.1); border: 1px solid rgba(252,129,129,.3); color: #fc8181; }
  .creds { background: rgba(201,168,76,.06); border: 1px solid rgba(201,168,76,.2); border-radius: 8px; padding: 1rem 1.25rem; margin-top: 1.5rem; font-size: .82rem; }
  .creds p { margin-bottom: .4rem; color: #9a7e68; }
  .creds strong { color: #F0D080; }
</style>
</head>
<body>
<div class="card">
    <div class="logo">
        <div class="icon">💍</div>
        <h1>E-Invitation Setup</h1>
        <p>PostgreSQL Installer</p>
    </div>

    <div class="status-banner <?= $ok ? 'status-ok' : 'status-error' ?>">
        <?= $ok ? '✓ Setup completed successfully!' : '✗ Setup encountered errors — see below' ?>
    </div>

    <div class="log">
        <?php foreach ($log as [$type, $msg]): ?>
        <div class="log-item">
            <div class="dot dot-<?= $type ?>"></div>
            <span><?= htmlspecialchars($msg) ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($ok): ?>
    <div class="creds">
        <p>Default admin credentials:</p>
        <p><strong>Username:</strong> admin</p>
        <p><strong>Password:</strong> admin123</p>
        <p style="margin-top:.75rem;font-size:.72rem;color:#fc8181;">⚠ Change your password after first login!</p>
    </div>
    <?php endif; ?>

    <div class="actions">
        <?php if ($ok): ?>
        <a href="<?= $baseUrl ?>/" class="btn btn-primary">🎉 View Invitation</a>
        <a href="<?= $baseUrl ?>/admin/" class="btn btn-outline">🔒 Admin Portal</a>
        <?php else: ?>
        <a href="setup.php" class="btn btn-primary">↺ Retry Setup</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
