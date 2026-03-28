<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$s  = getSettings();
$db = getDB();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'settings';

    if ($action === 'settings') {
        $fields = [
            'ceremony_type','couple_name_1','couple_name_2','couple_surname_1','couple_surname_2',
            'tagline','event_date','event_time','event_timezone','venue_name','venue_address',
            'rsvp_phone_1','rsvp_phone_2','rsvp_deadline','time_capsule_unlock',
            'color_bg','color_accent','color_text','custom_message',
            'music_url','music_autoplay','show_map','show_time_capsule','show_guest_garden'
        ];
        $stmt = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($fields as $field) {
            $val = $_POST[$field] ?? '';
            $stmt->execute([$field, $val]);
        }
        $success = 'Settings saved successfully!';
    }

    if ($action === 'upload_photo') {
        $type = $_POST['photo_type'] ?? '';
        if (!empty($_FILES['photo']) && in_array($type, ['cover_photo','couple_photo'])) {
            $filename = handleUpload($_FILES['photo'], $type);
            if ($filename) {
                $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')
                   ->execute([$type, $filename]);
                // Track in gallery
                $db->prepare('INSERT INTO gallery (filename, original_name, caption, is_cover, is_couple_photo) VALUES (?, ?, ?, ?, ?)')
                   ->execute([$filename, $_FILES['photo']['name'], ucwords(str_replace('_', ' ', $type)),
                              $type === 'cover_photo' ? 1 : 0, $type === 'couple_photo' ? 1 : 0]);
                $success = 'Photo uploaded!';
            } else {
                $error = 'Upload failed. Ensure file is JPG/PNG/GIF/WebP under 10MB.';
            }
        }
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $user    = $db->prepare('SELECT * FROM admin_users WHERE username = ?');
        $user->execute([$_SESSION['admin_user']]);
        $u = $user->fetch();
        if ($u && password_verify($current, $u['password_hash'])) {
            if ($new && $new === $confirm && strlen($new) >= 6) {
                $db->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')
                   ->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
                $success = 'Password changed!';
            } else {
                $error = 'Passwords do not match or too short (min 6 chars).';
            }
        } else {
            $error = 'Current password incorrect.';
        }
    }

    // Refresh settings
    $s = getSettings();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings — Admin Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;1,400&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="admin-main">
<?php include __DIR__ . '/partials/topbar.php'; ?>
<div class="admin-content">
    <div class="page-header">
        <div><h1 class="page-title">Invitation Settings</h1><p class="page-subtitle">Customise every detail of your e-invitation</p></div>
        <a href="<?= BASE_URL ?>/" target="_blank" class="btn btn-outline"><i class="fas fa-eye"></i> Preview</a>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-triangle-exclamation"></i> <?= e($error) ?></div><?php endif; ?>

    <!-- Tabs -->
    <div class="settings-tabs">
        <button class="tab-btn tab-btn--active" data-tab="general">General</button>
        <button class="tab-btn" data-tab="event">Event Details</button>
        <button class="tab-btn" data-tab="appearance">Appearance</button>
        <button class="tab-btn" data-tab="photos">Photos</button>
        <button class="tab-btn" data-tab="features">Features</button>
        <button class="tab-btn" data-tab="security">Security</button>
    </div>

    <form method="POST" class="settings-form">
        <input type="hidden" name="action" value="settings">

        <!-- General Tab -->
        <div class="tab-content tab-content--active" id="tab-general">
            <div class="settings-section">
                <h3 class="settings-section-title"><i class="fas fa-heart"></i> Couple Details</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Event Type</label>
                        <select name="ceremony_type">
                            <?php foreach (['Wedding','Engagement','Anniversary','Birthday','Baby Shower'] as $t): ?>
                            <option value="<?= $t ?>" <?= $s['ceremony_type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Name 1 (First)</label>
                        <input type="text" name="couple_name_1" value="<?= e($s['couple_name_1']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Name 1 (Surname)</label>
                        <input type="text" name="couple_surname_1" value="<?= e($s['couple_surname_1'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Name 2 (First)</label>
                        <input type="text" name="couple_name_2" value="<?= e($s['couple_name_2']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Name 2 (Surname)</label>
                        <input type="text" name="couple_surname_2" value="<?= e($s['couple_surname_2'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group full">
                    <label>Tagline / Subtitle</label>
                    <input type="text" name="tagline" value="<?= e($s['tagline']) ?>">
                </div>
                <div class="form-group full">
                    <label>Personal Message / Love Story (shown below the invitation)</label>
                    <textarea name="custom_message" rows="4"><?= e($s['custom_message']) ?></textarea>
                </div>
            </div>
        </div>

        <!-- Event Tab -->
        <div class="tab-content" id="tab-event">
            <div class="settings-section">
                <h3 class="settings-section-title"><i class="fas fa-calendar-days"></i> Event Details</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Event Date</label>
                        <input type="date" name="event_date" value="<?= e($s['event_date']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Event Time</label>
                        <input type="text" name="event_time" value="<?= e($s['event_time']) ?>" placeholder="10:00 AM">
                    </div>
                    <div class="form-group">
                        <label>Timezone</label>
                        <input type="text" name="event_timezone" value="<?= e($s['event_timezone']) ?>" placeholder="GMT">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Venue Name</label>
                        <input type="text" name="venue_name" value="<?= e($s['venue_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Venue Address</label>
                        <input type="text" name="venue_address" value="<?= e($s['venue_address']) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>RSVP Phone 1</label>
                        <input type="tel" name="rsvp_phone_1" value="<?= e($s['rsvp_phone_1']) ?>">
                    </div>
                    <div class="form-group">
                        <label>RSVP Phone 2</label>
                        <input type="tel" name="rsvp_phone_2" value="<?= e($s['rsvp_phone_2']) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>RSVP Deadline</label>
                        <input type="date" name="rsvp_deadline" value="<?= e($s['rsvp_deadline']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Time Capsule Unlock Date</label>
                        <input type="date" name="time_capsule_unlock" value="<?= e($s['time_capsule_unlock']) ?>">
                        <small>Wishes become visible to you on this date</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Appearance Tab -->
        <div class="tab-content" id="tab-appearance">
            <div class="settings-section">
                <h3 class="settings-section-title"><i class="fas fa-palette"></i> Colours</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Background Colour</label>
                        <div class="color-input-wrap">
                            <input type="color" name="color_bg" value="<?= e($s['color_bg']) ?>">
                            <input type="text" id="colorBgText" value="<?= e($s['color_bg']) ?>" class="color-text">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Accent / Gold Colour</label>
                        <div class="color-input-wrap">
                            <input type="color" name="color_accent" value="<?= e($s['color_accent']) ?>">
                            <input type="text" id="colorAccentText" value="<?= e($s['color_accent']) ?>" class="color-text">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Text Colour</label>
                        <div class="color-input-wrap">
                            <input type="color" name="color_text" value="<?= e($s['color_text']) ?>">
                            <input type="text" id="colorTextText" value="<?= e($s['color_text']) ?>" class="color-text">
                        </div>
                    </div>
                </div>
            </div>
            <div class="settings-section">
                <h3 class="settings-section-title"><i class="fas fa-music"></i> Background Music</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Music File URL (MP3/OGG)</label>
                        <input type="url" name="music_url" value="<?= e($s['music_url']) ?>" placeholder="https://…/song.mp3">
                    </div>
                    <div class="form-group">
                        <label>Autoplay</label>
                        <select name="music_autoplay">
                            <option value="0" <?= $s['music_autoplay'] === '0' ? 'selected' : '' ?>>No (manual toggle)</option>
                            <option value="1" <?= $s['music_autoplay'] === '1' ? 'selected' : '' ?>>Yes</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Photos Tab handled separately below -->
        <div class="tab-content" id="tab-photos">
            <p class="tab-note">Photo uploads use a separate form — please save settings above first, then upload photos.</p>
        </div>

        <!-- Features Tab -->
        <div class="tab-content" id="tab-features">
            <div class="settings-section">
                <h3 class="settings-section-title"><i class="fas fa-star"></i> Unique Features</h3>
                <div class="toggle-row">
                    <div class="toggle-info">
                        <strong>Guest Journey Map</strong>
                        <small>Show an interactive world map of where guests are travelling from</small>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="show_map" value="1" <?= $s['show_map'] === '1' ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="toggle-row">
                    <div class="toggle-info">
                        <strong>Time Capsule Wishes</strong>
                        <small>Guests leave sealed messages revealed only on the wedding day</small>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="show_time_capsule" value="1" <?= $s['show_time_capsule'] === '1' ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="toggle-row">
                    <div class="toggle-info">
                        <strong>Guest Garden</strong>
                        <small>Each confirmed RSVP adds an animated flower to the garden display</small>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="show_guest_garden" value="1" <?= $s['show_guest_garden'] === '1' ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Security Tab -->
        <div class="tab-content" id="tab-security">
            <p class="tab-note">Change your admin password below.</p>
        </div>

        <div class="form-actions" id="saveActions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save Settings</button>
        </div>
    </form>

    <!-- Photo Upload (separate forms) -->
    <div id="tab-photos-upload" style="display:none">
        <div class="settings-section">
            <h3 class="settings-section-title"><i class="fas fa-image"></i> Cover / Hero Photo</h3>
            <?php if ($s['cover_photo']): ?>
            <img src="<?= e(getImageUrl($s['cover_photo'])) ?>" class="settings-photo-preview" alt="Cover">
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data" class="upload-form">
                <input type="hidden" name="action" value="upload_photo">
                <input type="hidden" name="photo_type" value="cover_photo">
                <div class="file-drop" id="coverDrop">
                    <i class="fas fa-image"></i>
                    <span>Click or drag your cover photo here</span>
                    <input type="file" name="photo" accept="image/*">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload Cover Photo</button>
            </form>
        </div>
        <div class="settings-section">
            <h3 class="settings-section-title"><i class="fas fa-camera"></i> Couple Photo</h3>
            <?php if ($s['couple_photo']): ?>
            <img src="<?= e(getImageUrl($s['couple_photo'])) ?>" class="settings-photo-preview" alt="Couple">
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data" class="upload-form">
                <input type="hidden" name="action" value="upload_photo">
                <input type="hidden" name="photo_type" value="couple_photo">
                <div class="file-drop">
                    <i class="fas fa-camera"></i>
                    <span>Click or drag your couple photo here</span>
                    <input type="file" name="photo" accept="image/*">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload Couple Photo</button>
            </form>
        </div>
    </div>

    <!-- Password Change (separate form) -->
    <div id="tab-security-form" style="display:none">
        <form method="POST" class="settings-form">
            <input type="hidden" name="action" value="change_password">
            <div class="settings-section">
                <h3 class="settings-section-title"><i class="fas fa-shield-halved"></i> Change Password</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Change Password</button>
            </div>
        </form>
    </div>
</div>
</div>

<script src="<?= BASE_URL ?>/assets/js/admin.js"></script>
<script>
// Settings tabs
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('tab-btn--active'));
        btn.classList.add('tab-btn--active');
        const t = btn.dataset.tab;

        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('tab-content--active'));
        const mainTab = document.getElementById('tab-' + t);
        if (mainTab) mainTab.classList.add('tab-content--active');

        // Show/hide special areas
        document.getElementById('tab-photos-upload').style.display  = t === 'photos'   ? 'block' : 'none';
        document.getElementById('tab-security-form').style.display  = t === 'security' ? 'block' : 'none';
        document.getElementById('saveActions').style.display         = !['photos','security'].includes(t) ? 'flex' : 'none';
    });
});

// Sync color inputs
document.querySelectorAll('input[type="color"]').forEach(input => {
    const textInput = input.nextElementSibling;
    input.addEventListener('input', () => { if(textInput) textInput.value = input.value; });
    if(textInput) textInput.addEventListener('input', () => { input.value = textInput.value; });
});
</script>
</body>
</html>
