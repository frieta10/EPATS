<?php
// Database migration for multi-event support
// Run this once to migrate from single-event to multi-event structure

require_once __DIR__ . '/../config.php';
requireAdmin();

$db = getDB();
$success = $error = '';

try {
    // Check if already migrated
    $check = $db->query("SELECT 1 FROM pg_tables WHERE tablename = 'events'");
    if ($check && $check->fetch()) {
        $success = 'Migration already completed! Events table exists.';
    } else {
        // Run migration SQL
        $migrationSql = file_get_contents(__DIR__ . '/setup-multi-event.sql');
        
        // Execute each statement separately (PostgreSQL doesn't support multiple statements in one exec well)
        $statements = array_filter(array_map('trim', explode(';', $migrationSql)));
        
        foreach ($statements as $sql) {
            if (empty($sql) || strpos($sql, '--') === 0) continue;
            try {
                $db->exec($sql);
            } catch (PDOException $e) {
                // Ignore "already exists" errors
                if (strpos($e->getMessage(), 'already exists') === false && 
                    strpos($e->getMessage(), 'Duplicate') === false) {
                    throw $e;
                }
            }
        }
        
        $success = 'Migration completed successfully! Your existing event has been migrated to the new multi-event structure.';
    }
} catch (Exception $e) {
    $error = 'Migration error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Database Migration</title>
<style>
body { font-family: system-ui, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background: #1a0a14; color: #fff; }
.success { background: #1a472a; padding: 20px; border-radius: 8px; margin: 20px 0; }
.error { background: #8B1A4A; padding: 20px; border-radius: 8px; margin: 20px 0; }
.btn { background: #C9A84C; color: #000; padding: 12px 24px; text-decoration: none; border-radius: 8px; display: inline-block; margin-top: 20px; font-weight: 600; }
</style>
</head>
<body>
    <h1>🗄️ Database Migration</h1>
    <p>This migrates your database to support multiple events.</p>
    
    <?php if ($success): ?>
        <div class="success">✅ <?= nl2br(e($success)) ?></div>
        <a href="events.php" class="btn">Go to My Events</a>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="error">❌ <?= nl2br(e($error)) ?></div>
        <a href="index.php" class="btn">Back to Dashboard</a>
    <?php endif; ?>
</body>
</html>
