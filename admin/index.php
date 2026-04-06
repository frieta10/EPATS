<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$db = getDB();

// Get current event
$eventId = getCurrentEventId();
$event = getEvent($eventId);

// If no event, redirect to create one
if (!$event) {
    header('Location: ' . BASE_URL . '/admin/events.php');
    exit;
}

$s = getSettings();

// Stats - filtered by event
$statsStmt = $db->prepare("SELECT COUNT(*) FROM rsvp_responses WHERE event_id = ?");
$statsStmt->execute([$eventId]);
$totalRsvp = $statsStmt->fetchColumn() ?: 0;

$statsStmt = $db->prepare("SELECT COUNT(*) FROM rsvp_responses WHERE event_id = ? AND attending='yes'");
$statsStmt->execute([$eventId]);
$attending = $statsStmt->fetchColumn() ?: 0;

$statsStmt = $db->prepare("SELECT COUNT(*) FROM rsvp_responses WHERE event_id = ? AND attending='no'");
$statsStmt->execute([$eventId]);
$declining = $statsStmt->fetchColumn() ?: 0;

$statsStmt = $db->prepare("SELECT COUNT(*) FROM rsvp_responses WHERE event_id = ? AND attending='maybe'");
$statsStmt->execute([$eventId]);
$maybe = $statsStmt->fetchColumn() ?: 0;

$statsStmt = $db->prepare("SELECT COUNT(*) FROM guests WHERE event_id = ?");
$statsStmt->execute([$eventId]);
$totalGuests = $statsStmt->fetchColumn() ?: 0;

$statsStmt = $db->prepare("SELECT COUNT(*) FROM time_capsule WHERE event_id = ?");
$statsStmt->execute([$eventId]);
$capsules = $statsStmt->fetchColumn() ?: 0;

$statsStmt = $db->prepare("SELECT SUM(CASE WHEN plus_one THEN 1 ELSE 0 END) FROM rsvp_responses WHERE event_id = ? AND attending='yes'");
$statsStmt->execute([$eventId]);
$plusOnes = $statsStmt->fetchColumn() ?: 0;

// Recent RSVPs
$recentStmt = $db->prepare("SELECT * FROM rsvp_responses WHERE event_id = ? ORDER BY created_at DESC LIMIT 10");
$recentStmt->execute([$eventId]);
$recent = $recentStmt->fetchAll();

// By country
$countryStmt = $db->prepare("SELECT country, COUNT(*) as cnt FROM rsvp_responses WHERE event_id = ? AND country != '' GROUP BY country ORDER BY cnt DESC LIMIT 8");
$countryStmt->execute([$eventId]);
$byCountry = $countryStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Admin Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;1,400&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">

<?php include __DIR__ . '/partials/sidebar.php'; ?>

<div class="admin-main">
    <?php include __DIR__ . '/partials/topbar.php'; ?>

    <div class="admin-content">
        <!-- Welcome -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Dashboard</h1>
                <p class="page-subtitle"><?= e($s['couple_name_1']) ?> &amp; <?= e($s['couple_name_2']) ?> — <?= e($s['ceremony_type']) ?> · <?= formatDate($s['event_date']) ?></p>
            </div>
            <div style="display:flex;gap:12px">
                <a href="events.php" class="btn btn-outline"><i class="fas fa-calendar-star"></i> Switch Event</a>
                <a href="<?= BASE_URL ?>/?event=<?= e($event['slug']) ?>" target="_blank" class="btn btn-outline"><i class="fas fa-eye"></i> View Invitation</a>
            </div>
        </div>

        <!-- Stat Cards -->
        <div class="stats-grid">
            <div class="stat-card stat-card--gold">
                <div class="stat-icon"><i class="fas fa-envelope-open-text"></i></div>
                <div class="stat-body">
                    <span class="stat-num"><?= $totalRsvp ?></span>
                    <span class="stat-label">Total RSVPs</span>
                </div>
            </div>
            <div class="stat-card stat-card--green">
                <div class="stat-icon"><i class="fas fa-heart"></i></div>
                <div class="stat-body">
                    <span class="stat-num"><?= $attending ?></span>
                    <span class="stat-label">Attending</span>
                </div>
            </div>
            <div class="stat-card stat-card--rose">
                <div class="stat-icon"><i class="fas fa-heart-crack"></i></div>
                <div class="stat-body">
                    <span class="stat-num"><?= $declining ?></span>
                    <span class="stat-label">Declining</span>
                </div>
            </div>
            <div class="stat-card stat-card--blue">
                <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
                <div class="stat-body">
                    <span class="stat-num"><?= $attending + $plusOnes ?></span>
                    <span class="stat-label">Total Seats</span>
                </div>
            </div>
            <div class="stat-card stat-card--purple">
                <div class="stat-icon"><i class="fas fa-lock"></i></div>
                <div class="stat-body">
                    <span class="stat-num"><?= $capsules ?></span>
                    <span class="stat-label">Time Capsule Wishes</span>
                </div>
            </div>
            <div class="stat-card stat-card--amber">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-body">
                    <span class="stat-num"><?= $totalGuests ?></span>
                    <span class="stat-label">Guest List</span>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="charts-row">
            <div class="chart-card">
                <h3 class="chart-title">RSVP Breakdown</h3>
                <canvas id="rsvpChart" height="240"></canvas>
            </div>
            <?php if (!empty($byCountry)): ?>
            <div class="chart-card">
                <h3 class="chart-title">Guests by Country</h3>
                <canvas id="countryChart" height="240"></canvas>
            </div>
            <?php endif; ?>
            <div class="chart-card">
                <h3 class="chart-title">Countdown to Event</h3>
                <div class="admin-countdown" data-date="<?= e($s['event_date']) ?>">
                    <div class="acd-unit"><span id="acd-days">--</span><small>Days</small></div>
                    <div class="acd-unit"><span id="acd-hours">--</span><small>Hours</small></div>
                    <div class="acd-unit"><span id="acd-mins">--</span><small>Mins</small></div>
                </div>
                <div class="event-info-mini">
                    <p><i class="fas fa-calendar-days"></i> <?= formatDate($s['event_date']) ?></p>
                    <p><i class="fas fa-clock"></i> <?= e($s['event_time']) ?> <?= e($s['event_timezone']) ?></p>
                    <p><i class="fas fa-location-dot"></i> <?= e($s['venue_name']) ?></p>
                </div>
            </div>
        </div>

        <!-- Recent RSVPs -->
        <div class="table-card">
            <div class="table-card-header">
                <h3>Recent RSVPs</h3>
                <a href="<?= BASE_URL ?>/admin/rsvp.php" class="btn btn-sm">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th><th>Status</th><th>Email</th><th>Location</th><th>Time Capsule</th><th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $r): ?>
                        <tr>
                            <td><strong><?= e($r['name']) ?></strong><?= $r['plus_one'] ? ' <span class="badge badge-plus">+1</span>' : '' ?></td>
                            <td><span class="badge badge-<?= $r['attending'] ?>"><?= ucfirst($r['attending']) ?></span></td>
                            <td><?= e($r['email'] ?: '—') ?></td>
                            <td><?= e(trim($r['city'] . ', ' . $r['country'], ', ') ?: '—') ?></td>
                            <td>
                                <?php
                                $hasCap = $db->prepare('SELECT id FROM time_capsule WHERE rsvp_id = ? AND event_id = ?');
                                $hasCap->execute([$r['id'], $eventId]);
                                echo $hasCap->fetch() ? '<i class="fas fa-lock gold" title="Has wish"></i>' : '—';
                                ?>
                            </td>
                            <td><?= date('d M Y H:i', strtotime($r['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$recent): ?><tr><td colspan="6" class="empty-row">No RSVPs yet</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Invite Link Copy -->
        <div class="invite-link-card">
            <h3><i class="fas fa-link"></i> Public Invitation Link</h3>
            <div class="copy-wrap">
                <input type="text" id="inviteLink" value="<?= BASE_URL ?>/?event=<?= e($event['slug']) ?>" readonly>
                <button class="btn" onclick="copyLink()"><i class="fas fa-copy"></i> Copy</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const BASE_URL = '<?= BASE_URL ?>';
// RSVP Donut
new Chart(document.getElementById('rsvpChart'), {
    type: 'doughnut',
    data: {
        labels: ['Attending', 'Declining', 'Maybe'],
        datasets: [{
            data: [<?= $attending ?>, <?= $declining ?>, <?= $maybe ?>],
            backgroundColor: ['#C9A84C', '#8B1A4A', '#7B6B8C'],
            borderColor: ['#1a0a14'],
            borderWidth: 3
        }]
    },
    options: {
        plugins: { legend: { labels: { color: '#e0c9a6', font: { family: 'Montserrat' } } } },
        cutout: '65%'
    }
});

<?php if (!empty($byCountry)): ?>
new Chart(document.getElementById('countryChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($byCountry, 'country')) ?>,
        datasets: [{
            label: 'Guests',
            data: <?= json_encode(array_column($byCountry, 'cnt')) ?>,
            backgroundColor: '#C9A84C88',
            borderColor: '#C9A84C',
            borderWidth: 1,
            borderRadius: 6
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color: '#b8a07a' }, grid: { color: '#3a1a2a' } },
            y: { ticks: { color: '#b8a07a', stepSize: 1 }, grid: { color: '#3a1a2a' } }
        }
    }
});
<?php endif; ?>

// Admin countdown
function adminCountdown() {
    const d = new Date('<?= $s['event_date'] ?>');
    const now = new Date();
    const diff = d - now;
    if (diff > 0) {
        document.getElementById('acd-days').textContent  = Math.floor(diff / 86400000);
        document.getElementById('acd-hours').textContent = Math.floor((diff % 86400000) / 3600000);
        document.getElementById('acd-mins').textContent  = Math.floor((diff % 3600000) / 60000);
    }
}
adminCountdown();
setInterval(adminCountdown, 60000);

function copyLink() {
    document.getElementById('inviteLink').select();
    document.execCommand('copy');
    alert('Link copied!');
}
</script>
</body>
</html>
