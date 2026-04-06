<?php
require_once __DIR__ . '/../config.php';
requireAdmin();

$db = getDB();
$success = $error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = trim($_POST['event_name'] ?? '');
        $slug = preg_replace('/[^a-z0-9-]/', '-', strtolower(trim($_POST['event_slug'] ?? '')));
        $type = $_POST['ceremony_type'] ?? 'Wedding';
        
        if (empty($name)) {
            $error = 'Event name is required.';
        } elseif (empty($slug)) {
            $error = 'Event URL slug is required.';
        } else {
            try {
                $id = createEvent($name, $slug, $type);
                // Copy default settings to new event
                $defaultSettings = getSettings();
                $stmt = $db->prepare('INSERT INTO event_settings (event_id, setting_key, setting_value) VALUES (?, ?, ?)');
                foreach ($defaultSettings as $key => $value) {
                    $stmt->execute([$id, $key, $value]);
                }
                $success = 'Event created successfully!';
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'unique') !== false || strpos($e->getMessage(), 'duplicate') !== false) {
                    $error = 'Event slug already exists. Please choose a different one.';
                } else {
                    $error = 'Error creating event: ' . $e->getMessage();
                }
            }
        }
    }
    
    if ($action === 'delete') {
        $id = (int) ($_POST['event_id'] ?? 0);
        if ($id > 0) {
            deleteEvent($id);
            $success = 'Event deleted successfully!';
        }
    }
    
    if ($action === 'switch') {
        $id = (int) ($_POST['event_id'] ?? 0);
        if ($id > 0) {
            setCurrentEventId($id);
            // Update is_active
            $db->prepare("UPDATE events SET is_active = (id = ?)")->execute([$id]);
            $success = 'Switched to selected event!';
        }
    }
}

$events = [];
$currentEventId = 0;
try {
    $events = getEvents();
    $currentEventId = getCurrentEventId();
} catch (Exception $e) {
    // Table doesn't exist yet - show empty state
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Events — Admin Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;1,400&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/admin.css">
<style>
.events-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 24px;
    margin-top: 24px;
}
.event-card {
    background: var(--card-bg, rgba(255,255,255,0.03));
    border: 1px solid var(--border-color, rgba(255,255,255,0.08));
    border-radius: 16px;
    padding: 24px;
    position: relative;
    transition: all 0.3s ease;
}
.event-card:hover {
    transform: translateY(-4px);
    border-color: var(--accent-color, #C9A84C);
}
.event-card.active {
    border-color: var(--accent-color, #C9A84C);
    box-shadow: 0 0 20px rgba(201, 168, 76, 0.15);
}
.event-card.active::before {
    content: 'CURRENT';
    position: absolute;
    top: -1px;
    right: 20px;
    background: var(--accent-color, #C9A84C);
    color: #000;
    font-size: 10px;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 0 0 8px 8px;
    letter-spacing: 1px;
}
.event-type {
    display: inline-block;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--accent-color, #C9A84C);
    margin-bottom: 8px;
}
.event-name {
    font-size: 20px;
    font-weight: 500;
    margin-bottom: 8px;
    color: #fff;
}
.event-couple {
    font-size: 14px;
    color: rgba(255,255,255,0.6);
    margin-bottom: 16px;
}
.event-meta {
    display: flex;
    gap: 16px;
    font-size: 13px;
    color: rgba(255,255,255,0.5);
    margin-bottom: 20px;
}
.event-meta i {
    margin-right: 6px;
    color: var(--accent-color, #C9A84C);
}
.event-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.event-actions .btn {
    flex: 1;
    min-width: 80px;
    padding: 10px 16px;
    font-size: 12px;
}

/* Create form modal */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.8);
    backdrop-filter: blur(8px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 20px;
}
.modal-overlay.active {
    display: flex;
}
.modal-content {
    background: var(--card-bg, #1a0b15);
    border: 1px solid var(--border-color, rgba(255,255,255,0.1));
    border-radius: 20px;
    width: 100%;
    max-width: 480px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 32px;
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}
.modal-title {
    font-size: 22px;
    font-weight: 500;
}
.modal-close {
    background: none;
    border: none;
    color: rgba(255,255,255,0.5);
    font-size: 24px;
    cursor: pointer;
}
.modal-close:hover {
    color: #fff;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: rgba(255,255,255,0.5);
}
.empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    color: var(--accent-color, #C9A84C);
    opacity: 0.5;
}
.empty-state h3 {
    font-size: 18px;
    margin-bottom: 8px;
    color: #fff;
}

.btn-create {
    background: linear-gradient(135deg, var(--accent-color, #C9A84C), #e8c76a);
    color: #000;
    border: none;
    padding: 14px 28px;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}
.btn-create:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(201, 168, 76, 0.3);
}

.page-header-actions {
    display: flex;
    gap: 12px;
}
</style>
</head>
<body class="admin-body">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="admin-main">
<?php include __DIR__ . '/partials/topbar.php'; ?>
<div class="admin-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">My Events</h1>
            <p class="page-subtitle">Create and manage all your e-invitations</p>
        </div>
        <div class="page-header-actions">
            <button class="btn-create" onclick="openModal()">
                <i class="fas fa-plus"></i> Create New Event
            </button>
        </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-triangle-exclamation"></i> <?= e($error) ?></div><?php endif; ?>

    <?php if (empty($events)): ?>
        <div class="empty-state">
            <i class="fas fa-calendar-star"></i>
            <h3>No events yet</h3>
            <p>Create your first e-invitation to get started</p>
        </div>
    <?php else: ?>
        <div class="events-grid">
            <?php foreach ($events as $event): 
                $isActive = $event['id'] == $currentEventId;
                // Get stats
                $guestCount = $db->prepare("SELECT COUNT(*) FROM guests WHERE event_id = ?")->execute([$event['id']]) ? $db->prepare("SELECT COUNT(*) FROM guests WHERE event_id = ?")->fetchColumn() : 0;
                $rsvpCount = $db->prepare("SELECT COUNT(*) FROM rsvp_responses WHERE event_id = ?")->execute([$event['id']]) ? $db->prepare("SELECT COUNT(*) FROM rsvp_responses WHERE event_id = ?")->fetchColumn() : 0;
            ?>
            <div class="event-card <?= $isActive ? 'active' : '' ?>">
                <div class="event-type"><?= e($event['ceremony_type']) ?></div>
                <h3 class="event-name"><?= e($event['name']) ?></h3>
                <div class="event-couple">
                    <?= e($event['couple_name_1'] ?? 'Partner 1') ?> & <?= e($event['couple_name_2'] ?? 'Partner 2') ?>
                </div>
                <div class="event-meta">
                    <span><i class="fas fa-calendar"></i> <?= $event['event_date'] ? formatDateShort($event['event_date']) : 'No date' ?></span>
                    <span><i class="fas fa-users"></i> <?= $rsvpCount ?> RSVPs</span>
                </div>
                <div class="event-actions">
                    <?php if (!$isActive): ?>
                    <form method="POST" style="flex:1">
                        <input type="hidden" name="action" value="switch">
                        <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                        <button type="submit" class="btn btn-outline" style="width:100%">
                            <i class="fas fa-check"></i> Switch
                        </button>
                    </form>
                    <?php endif; ?>
                    <a href="settings.php?event_id=<?= $event['id'] ?>" class="btn btn-primary" style="flex:<?= $isActive ? '2' : '1' ?>">
                        <i class="fas fa-pen"></i> Edit
                    </a>
                    <form method="POST" onsubmit="return confirm('Delete this event? All data will be lost.')" style="flex:0">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                        <button type="submit" class="btn btn-danger" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
</div>

<!-- Create Event Modal -->
<div class="modal-overlay" id="createModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Create New Event</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label>Event Name</label>
                <input type="text" name="event_name" placeholder="e.g., Jenil & Abram Wedding" required>
            </div>
            
            <div class="form-group">
                <label>URL Slug</label>
                <input type="text" name="event_slug" placeholder="e.g., jenil-abram-wedding" required 
                       pattern="[a-z0-9-]+" title="Only lowercase letters, numbers, and hyphens">
                <small>This will be used in the URL: /event/your-slug</small>
            </div>
            
            <div class="form-group">
                <label>Event Type</label>
                <select name="ceremony_type">
                    <option value="Wedding">Wedding</option>
                    <option value="Engagement">Engagement</option>
                    <option value="Anniversary">Anniversary</option>
                    <option value="Birthday">Birthday</option>
                    <option value="Baby Shower">Baby Shower</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="form-actions" style="margin-top: 24px;">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Event
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('createModal').classList.add('active');
}
function closeModal() {
    document.getElementById('createModal').classList.remove('active');
}
// Close on overlay click
document.getElementById('createModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
// Auto-generate slug from name
document.querySelector('input[name="event_name"]').addEventListener('blur', function() {
    const slugInput = document.querySelector('input[name="event_slug"]');
    if (!slugInput.value && this.value) {
        slugInput.value = this.value.toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
    }
});
</script>
</body>
</html>
