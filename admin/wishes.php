<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$db = getDB();
$s  = getSettings();

// Reveal all
if (!empty($_GET['reveal_all'])) {
    $db->exec('UPDATE time_capsule SET is_revealed = 1');
    header('Location: ' . BASE_URL . '/admin/wishes.php?revealed=1');
    exit;
}

// Reveal single
if (!empty($_GET['reveal'])) {
    $db->prepare('UPDATE time_capsule SET is_revealed = 1 WHERE id = ?')->execute([(int)$_GET['reveal']]);
}

// Filter by rsvp
$rsvpFilter = (int)($_GET['rsvp'] ?? 0);

$unlockDate = $s['time_capsule_unlock'] ?? $s['event_date'];
$isUnlocked = strtotime($unlockDate) <= time();

$query = "SELECT tc.*, r.name as rsvp_name, r.email FROM time_capsule tc LEFT JOIN rsvp_responses r ON r.id = tc.rsvp_id";
$params = [];
if ($rsvpFilter) {
    $query .= ' WHERE tc.rsvp_id = ?';
    $params[] = $rsvpFilter;
}
$query .= ' ORDER BY tc.created_at DESC';

$wishes = $db->prepare($query);
$wishes->execute($params);
$rows = $wishes->fetchAll();

$total    = $db->query('SELECT COUNT(*) FROM time_capsule')->fetchColumn();
$revealed = $db->query('SELECT COUNT(*) FROM time_capsule WHERE is_revealed = 1')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Time Capsule — Admin</title>
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
        <div>
            <h1 class="page-title"><i class="fas fa-lock"></i> Time Capsule</h1>
            <p class="page-subtitle"><?= $total ?> wishes — <?= $revealed ?> revealed — unlocks <?= formatDateShort($unlockDate) ?></p>
        </div>
        <?php if (!$isUnlocked): ?>
        <div class="capsule-status capsule-status--locked">
            <i class="fas fa-lock"></i> Sealed until <?= formatDateShort($unlockDate) ?>
        </div>
        <?php else: ?>
        <a href="?reveal_all=1" class="btn btn-primary" onclick="return confirm('Reveal ALL wishes? This cannot be undone.')"><i class="fas fa-envelope-open"></i> Reveal All Wishes</a>
        <?php endif; ?>
    </div>

    <?php if (!empty($_GET['revealed'])): ?><div class="alert alert-success"><i class="fas fa-envelope-open-text"></i> All wishes revealed!</div><?php endif; ?>

    <!-- Capsule Status Banner -->
    <?php if (!$isUnlocked): ?>
    <div class="capsule-banner">
        <div class="capsule-banner-icon"><i class="fas fa-hourglass-half"></i></div>
        <div>
            <strong>The Time Capsule is sealed</strong>
            <p>These messages were written with love and will be revealed to you on <strong><?= formatDateShort($unlockDate) ?></strong>. The countdown adds a magical anticipation to your special day!</p>
        </div>
        <div class="capsule-timer-admin" data-unlock="<?= e($unlockDate) ?>">
            <span id="tc-days">--</span>d <span id="tc-hrs">--</span>h <span id="tc-min">--</span>m
        </div>
    </div>
    <?php endif; ?>

    <!-- Wishes Grid -->
    <div class="wishes-grid">
        <?php foreach ($rows as $wish): ?>
        <div class="wish-card <?= $wish['is_revealed'] ? 'wish-card--revealed' : 'wish-card--sealed' ?>">
            <div class="wish-card-header">
                <div class="wish-author">
                    <div class="wish-avatar"><?= strtoupper(substr($wish['guest_name'], 0, 1)) ?></div>
                    <div>
                        <strong><?= e($wish['guest_name']) ?></strong>
                        <small><?= date('d M Y', strtotime($wish['created_at'])) ?></small>
                    </div>
                </div>
                <span class="wish-status <?= $wish['is_revealed'] ? 'wish-status--open' : 'wish-status--locked' ?>">
                    <i class="fas <?= $wish['is_revealed'] ? 'fa-envelope-open-text' : 'fa-lock' ?>"></i>
                    <?= $wish['is_revealed'] ? 'Revealed' : 'Sealed' ?>
                </span>
            </div>

            <?php if ($wish['is_revealed'] || $isUnlocked): ?>
            <div class="wish-body">
                <blockquote><?= nl2br(e($wish['message'])) ?></blockquote>
                <?php if ($wish['photo_path']): ?>
                <img src="<?= e(getImageUrl($wish['photo_path'])) ?>" class="wish-photo" alt="Wish photo">
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="wish-body wish-body--sealed">
                <div class="sealed-placeholder">
                    <i class="fas fa-wax-seal"></i>
                    <p>Message sealed until <?= formatDateShort($unlockDate) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!$wish['is_revealed'] && $isUnlocked): ?>
            <div class="wish-actions">
                <a href="?reveal=<?= $wish['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-envelope-open"></i> Reveal This Wish</a>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
        <div class="empty-state">
            <i class="fas fa-lock"></i>
            <p>No time capsule wishes yet. They'll appear here once guests submit their RSVPs with a sealed message.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>

<script>
function tcCountdown() {
    const unlock = new Date('<?= $unlockDate ?>');
    const diff = unlock - new Date();
    if (diff > 0) {
        document.getElementById('tc-days') && (document.getElementById('tc-days').textContent = Math.floor(diff/86400000));
        document.getElementById('tc-hrs')  && (document.getElementById('tc-hrs').textContent  = Math.floor(diff%86400000/3600000));
        document.getElementById('tc-min')  && (document.getElementById('tc-min').textContent  = Math.floor(diff%3600000/60000));
    }
}
tcCountdown(); setInterval(tcCountdown, 60000);
</script>
</body>
</html>
