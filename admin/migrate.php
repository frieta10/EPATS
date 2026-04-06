<?php
// Database migration for multi-event support
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
        // Step 1: Create events table
        $db->exec("CREATE TABLE IF NOT EXISTS events (
            id SERIAL PRIMARY KEY,
            name VARCHAR(200) NOT NULL,
            slug VARCHAR(100) UNIQUE NOT NULL,
            ceremony_type VARCHAR(50) DEFAULT 'Wedding',
            couple_name_1 VARCHAR(100),
            couple_name_2 VARCHAR(100),
            event_date DATE,
            is_active BOOLEAN DEFAULT FALSE,
            is_published BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Step 2: Add event_id columns to existing tables
        $db->exec("ALTER TABLE guests ADD COLUMN IF NOT EXISTS event_id INTEGER REFERENCES events(id) ON DELETE CASCADE");
        $db->exec("ALTER TABLE rsvp_responses ADD COLUMN IF NOT EXISTS event_id INTEGER REFERENCES events(id) ON DELETE CASCADE");
        $db->exec("ALTER TABLE time_capsule ADD COLUMN IF NOT EXISTS event_id INTEGER REFERENCES events(id) ON DELETE CASCADE");
        $db->exec("ALTER TABLE gallery ADD COLUMN IF NOT EXISTS event_id INTEGER REFERENCES events(id) ON DELETE CASCADE");
        $db->exec("ALTER TABLE map_pins ADD COLUMN IF NOT EXISTS event_id INTEGER REFERENCES events(id) ON DELETE CASCADE");
        
        // Step 3: Create event_settings table
        $db->exec("CREATE TABLE IF NOT EXISTS event_settings (
            id SERIAL PRIMARY KEY,
            event_id INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(event_id, setting_key)
        )");
        
        // Step 4: Migrate existing settings to first event
        $settings = $db->query("SELECT * FROM settings")->fetchAll();
        
        if (!empty($settings)) {
            // Get values for event creation
            $getSetting = function($key) use ($settings) {
                foreach ($settings as $s) {
                    if ($s['setting_key'] === $key) return $s['setting_value'];
                }
                return '';
            };
            
            $couple1 = $getSetting('couple_name_1') ?: 'Partner 1';
            $couple2 = $getSetting('couple_name_2') ?: 'Partner 2';
            $eventName = $couple1 . ' & ' . $couple2;
            $ceremonyType = $getSetting('ceremony_type') ?: 'Wedding';
            $eventDate = $getSetting('event_date') ?: null;
            
            // Create first event
            $stmt = $db->prepare("INSERT INTO events (name, slug, ceremony_type, couple_name_1, couple_name_2, event_date, is_active, is_published) VALUES (?, ?, ?, ?, ?, ?, TRUE, TRUE) RETURNING id");
            $stmt->execute([$eventName, 'event-1', $ceremonyType, $couple1, $couple2, $eventDate]);
            $eventId = $stmt->fetchColumn();
            
            // Update slug to be unique
            $db->prepare("UPDATE events SET slug = ? WHERE id = ?")->execute(['event-' . $eventId, $eventId]);
            
            // Copy settings to event_settings
            $insertSetting = $db->prepare("INSERT INTO event_settings (event_id, setting_key, setting_value) VALUES (?, ?, ?) ON CONFLICT (event_id, setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value");
            foreach ($settings as $s) {
                $insertSetting->execute([$eventId, $s['setting_key'], $s['setting_value']]);
            }
            
            // Update existing data to link to first event
            $db->prepare("UPDATE guests SET event_id = ? WHERE event_id IS NULL")->execute([$eventId]);
            $db->prepare("UPDATE rsvp_responses SET event_id = ? WHERE event_id IS NULL")->execute([$eventId]);
            $db->prepare("UPDATE time_capsule SET event_id = ? WHERE event_id IS NULL")->execute([$eventId]);
            $db->prepare("UPDATE gallery SET event_id = ? WHERE event_id IS NULL")->execute([$eventId]);
            $db->prepare("UPDATE map_pins SET event_id = ? WHERE event_id IS NULL")->execute([$eventId]);
            
            $success = "Migration completed! Created event '{$eventName}' with all your existing data.";
        } else {
            // No existing settings - just create empty structure
            $success = 'Migration completed! Events structure ready. Create your first event to get started.';
        }
        
        // Step 5: Create indexes
        $db->exec("CREATE INDEX IF NOT EXISTS idx_guests_event ON guests(event_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_rsvp_event ON rsvp_responses(event_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_gallery_event ON gallery(event_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_event_settings_event ON event_settings(event_id)");
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
    
    <?php if (empty($success) && empty($error)): ?>
        <form method="POST">
            <button type="submit" class="btn">Run Migration</button>
        </form>
    <?php endif; ?>
</body>
</html>
