<?php
require_once __DIR__ . '/config.php';
$s = getSettings();

$token = preg_replace('/[^a-f0-9]/', '', $_GET['token'] ?? '');
if (!$token) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

$stmt = getDB()->prepare('SELECT * FROM rsvp_responses WHERE qr_token = ?');
$stmt->execute([$token]);
$rsvp = $stmt->fetch();

if (!$rsvp) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

// Time capsule status
$tc = getDB()->prepare('SELECT * FROM time_capsule WHERE rsvp_id = ?');
$tc->execute([$rsvp['id']]);
$capsule = $tc->fetch();

$unlockDate = $s['time_capsule_unlock'] ?? $s['event_date'];
$isUnlocked = strtotime($unlockDate) <= time();

$eventDate = $s['event_date'] ?? '2024-09-30';
$eventTs   = strtotime($eventDate);
$eventDay  = date('d', $eventTs);
$eventMon  = strtoupper(date('M', $eventTs));
$eventYear = date('Y', $eventTs);

$qrData    = BASE_URL . '/guest-portal.php?token=' . $token;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Your Invitation — <?= e($s['couple_name_1']) ?> &amp; <?= e($s['couple_name_2']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=Montserrat:wght@300;400;500;600&family=Great+Vibes&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/portal.css">
</head>
<body class="portal-body">
<canvas id="particleCanvas"></canvas>

<div class="portal-container">
    <!-- Header -->
    <div class="portal-header reveal-up">
        <div class="portal-names-mini"><?= e($s['couple_name_1']) ?> &amp; <?= e($s['couple_name_2']) ?></div>
        <p class="portal-tagline"><?= e($s['ceremony_type']) ?> Celebration</p>
    </div>

    <!-- Greeting Card -->
    <div class="portal-greeting-card reveal-up">
        <div class="portal-badge portal-badge--<?= $rsvp['attending'] ?>">
            <?php if ($rsvp['attending'] === 'yes'): ?>
                <i class="fas fa-heart"></i> Attending
            <?php elseif ($rsvp['attending'] === 'no'): ?>
                <i class="fas fa-heart-crack"></i> Regretfully Declining
            <?php else: ?>
                <i class="fas fa-circle-question"></i> Pending Response
            <?php endif; ?>
        </div>
        <h1 class="portal-welcome">Welcome, <?= e($rsvp['name']) ?>!</h1>
        <p class="portal-sub">
            <?php if ($rsvp['attending'] === 'yes'): ?>
                We're thrilled you'll be joining us on our special day.
            <?php else: ?>
                We're sorry you can't make it, but we appreciate your kind wishes.
            <?php endif; ?>
        </p>
    </div>

    <!-- Event Details -->
    <div class="portal-details reveal-up">
        <div class="portal-detail-item">
            <i class="fas fa-calendar-days"></i>
            <div>
                <span class="detail-label">Date</span>
                <span class="detail-value"><?= $eventDay ?> <?= $eventMon ?> <?= $eventYear ?></span>
            </div>
        </div>
        <div class="portal-detail-item">
            <i class="fas fa-clock"></i>
            <div>
                <span class="detail-label">Time</span>
                <span class="detail-value"><?= e($s['event_time']) ?> <?= e($s['event_timezone']) ?></span>
            </div>
        </div>
        <div class="portal-detail-item">
            <i class="fas fa-location-dot"></i>
            <div>
                <span class="detail-label">Venue</span>
                <span class="detail-value"><?= e($s['venue_name']) ?></span>
                <span class="detail-sub"><?= e($s['venue_address']) ?></span>
            </div>
        </div>
        <?php if ($rsvp['plus_one'] && $rsvp['plus_one_name']): ?>
        <div class="portal-detail-item">
            <i class="fas fa-user-plus"></i>
            <div>
                <span class="detail-label">Plus One</span>
                <span class="detail-value"><?= e($rsvp['plus_one_name']) ?></span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- QR Code Section -->
    <?php if ($rsvp['attending'] === 'yes'): ?>
    <div class="portal-qr-section reveal-up">
        <h3><i class="fas fa-qrcode"></i> Your Event Pass</h3>
        <p>Present this QR code at the entrance for a seamless check-in experience.</p>
        <div class="qr-wrapper">
            <div id="guestQR" class="qr-display"></div>
            <div class="qr-glow"></div>
        </div>
        <div class="qr-meta">
            <span class="qr-name"><?= e($rsvp['name']) ?></span>
            <?php if ($rsvp['plus_one']): ?><span class="qr-plus">+1 Guest</span><?php endif; ?>
        </div>
        <button class="btn-download-qr" id="downloadQR">
            <i class="fas fa-download"></i> Download QR Code
        </button>
    </div>
    <?php endif; ?>

    <!-- Time Capsule Section -->
    <?php if ($capsule): ?>
    <div class="portal-capsule reveal-up">
        <div class="capsule-header">
            <div class="capsule-icon">
                <?php if ($isUnlocked): ?>
                    <i class="fas fa-envelope-open-text capsule-open"></i>
                <?php else: ?>
                    <i class="fas fa-lock capsule-locked"></i>
                <?php endif; ?>
            </div>
            <div>
                <h3>Your Time Capsule Wish</h3>
                <?php if ($isUnlocked): ?>
                    <p>Your message has been revealed to the couple!</p>
                <?php else: ?>
                    <p>Your wish is sealed until <strong><?= formatDateShort($unlockDate) ?></strong></p>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($isUnlocked): ?>
        <div class="capsule-message revealed">
            <blockquote><?= nl2br(e($capsule['message'])) ?></blockquote>
            <?php if ($capsule['photo_path']): ?>
            <img src="<?= UPLOAD_URL . e($capsule['photo_path']) ?>" class="capsule-photo" alt="Capsule photo">
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="capsule-sealed">
            <div class="capsule-seal-anim">
                <i class="fas fa-wax-seal"></i>
            </div>
            <p class="capsule-countdown" data-unlock="<?= e($unlockDate) ?>">
                Unlocking in <strong id="capsuleTimer">…</strong>
            </p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Add to Calendar -->
    <div class="portal-calendar reveal-up">
        <h3><i class="fas fa-calendar-plus"></i> Add to Your Calendar</h3>
        <div class="cal-buttons">
            <?php
            $calTitle  = urlencode($s['couple_name_1'] . ' & ' . $s['couple_name_2'] . ' ' . $s['ceremony_type']);
            $calDetails = urlencode($s['tagline'] . ' at ' . $s['venue_name'] . ', ' . $s['venue_address']);
            $calLoc    = urlencode($s['venue_name'] . ', ' . $s['venue_address']);
            $calDate   = date('Ymd', $eventTs);
            $gcUrl     = "https://calendar.google.com/calendar/render?action=TEMPLATE&text={$calTitle}&dates={$calDate}/{$calDate}&details={$calDetails}&location={$calLoc}";
            ?>
            <a href="<?= $gcUrl ?>" target="_blank" class="cal-btn cal-btn--google">
                <i class="fab fa-google"></i> Google
            </a>
            <a href="#" class="cal-btn cal-btn--apple" id="appleCalBtn"
               data-title="<?= e($s['couple_name_1'] . ' & ' . $s['couple_name_2'] . ' ' . $s['ceremony_type']) ?>"
               data-date="<?= e($eventDate) ?>"
               data-venue="<?= e($s['venue_name'] . ', ' . $s['venue_address']) ?>">
                <i class="fab fa-apple"></i> Apple
            </a>
            <a href="#" class="cal-btn cal-btn--outlook" id="outlookCalBtn">
                <i class="fab fa-microsoft"></i> Outlook
            </a>
        </div>
    </div>

    <div class="portal-footer">
        <a href="<?= BASE_URL ?>/" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Invitation</a>
        <p class="portal-footer-names"><?= e($s['couple_name_1']) ?> &amp; <?= e($s['couple_name_2']) ?></p>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
const BASE_URL = '<?= BASE_URL ?>';
const QR_DATA  = '<?= $qrData ?>';
const UNLOCK_DATE = '<?= $unlockDate ?>';
</script>
<script src="<?= BASE_URL ?>/assets/js/portal.js"></script>
</body>
</html>
