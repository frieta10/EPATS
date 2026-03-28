<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$db = getDB();

// Delete RSVP
if (!empty($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $db->prepare('DELETE FROM rsvp_responses WHERE id = ?')->execute([$id]);
    header('Location: ' . BASE_URL . '/admin/rsvp.php?deleted=1');
    exit;
}

// Filter
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$where  = '1=1';
$params = [];
if ($filter !== 'all') {
    $where   .= ' AND r.attending = ?';
    $params[] = $filter;
}
if ($search) {
    $where   .= ' AND (r.name LIKE ? OR r.email LIKE ? OR r.city LIKE ? OR r.country LIKE ?)';
    $s        = "%$search%";
    $params   = array_merge($params, [$s, $s, $s, $s]);
}

$rsvps = $db->prepare("
    SELECT r.*,
           (SELECT COUNT(*) FROM time_capsule t WHERE t.rsvp_id = r.id) AS has_capsule
    FROM rsvp_responses r
    WHERE $where
    ORDER BY r.created_at DESC
");
$rsvps->execute($params);
$rows = $rsvps->fetchAll();

$counts = $db->query("
    SELECT attending, COUNT(*) as cnt FROM rsvp_responses GROUP BY attending
")->fetchAll(PDO::FETCH_KEY_PAIR);
$total = array_sum($counts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RSVP Responses — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="admin-main">
<?php include __DIR__ . '/partials/topbar.php'; ?>
<div class="admin-content">
    <div class="page-header">
        <div><h1 class="page-title">RSVP Responses</h1><p class="page-subtitle"><?= $total ?> total responses</p></div>
        <a href="?export=csv" class="btn btn-outline"><i class="fas fa-file-csv"></i> Export CSV</a>
    </div>

    <?php if (!empty($_GET['deleted'])): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> RSVP deleted.</div><?php endif; ?>

    <!-- Filters -->
    <div class="filter-bar">
        <form method="GET" class="search-form">
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search name, email, location…">
            <button type="submit" class="btn"><i class="fas fa-search"></i></button>
        </form>
        <div class="filter-tabs">
            <?php foreach (['all' => 'All', 'yes' => 'Attending', 'no' => 'Declining', 'maybe' => 'Maybe'] as $key => $label): ?>
            <a href="?filter=<?= $key ?><?= $search ? '&q=' . urlencode($search) : '' ?>"
               class="filter-tab <?= $filter === $key ? 'filter-tab--active' : '' ?>">
                <?= $label ?>
                <span class="filter-count">
                    <?= $key === 'all' ? $total : ($counts[$key] ?? 0) ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php
    // CSV Export
    if (!empty($_GET['export']) && $_GET['export'] === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="rsvp_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Name','Email','Phone','Attending','Plus One','Plus One Name','Dietary','Message','City','Country','Submitted']);
        foreach ($rows as $row) {
            fputcsv($out, [$row['name'],$row['email'],$row['phone'],$row['attending'],$row['plus_one']?'Yes':'No',$row['plus_one_name'],$row['dietary_requirements'],$row['message'],$row['city'],$row['country'],date('d M Y H:i', strtotime($row['created_at']))]);
        }
        fclose($out);
        exit;
    }
    ?>

    <div class="table-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th><th>Name</th><th>Status</th><th>Contact</th>
                        <th>Location</th><th>Dietary</th><th>Capsule</th><th>Date</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td>
                            <strong><?= e($r['name']) ?></strong>
                            <?php if ($r['plus_one']): ?>
                                <span class="badge badge-plus">+1<?= $r['plus_one_name'] ? ' ' . e($r['plus_one_name']) : '' ?></span>
                            <?php endif; ?>
                            <?php if ($r['message']): ?>
                                <p class="row-message">"<?= e(mb_substr($r['message'], 0, 80)) . (mb_strlen($r['message']) > 80 ? '…' : '') ?>"</p>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?= $r['attending'] ?>"><?= ucfirst($r['attending']) ?></span></td>
                        <td>
                            <?= e($r['email'] ?: '—') ?><br>
                            <small><?= e($r['phone'] ?: '') ?></small>
                        </td>
                        <td><?= e(trim(($r['city'] ? $r['city'] . ', ' : '') . $r['country'], ', ') ?: '—') ?></td>
                        <td><?= e($r['dietary_requirements'] ?: '—') ?></td>
                        <td>
                            <?php if ($r['has_capsule']): ?>
                            <a href="<?= BASE_URL ?>/admin/wishes.php?rsvp=<?= $r['id'] ?>" class="gold" title="View wish"><i class="fas fa-lock"></i></a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                        <td>
                            <?php if ($r['qr_token']): ?>
                            <a href="<?= BASE_URL ?>/guest-portal.php?token=<?= $r['qr_token'] ?>" target="_blank" class="btn-icon-sm" title="View portal"><i class="fas fa-qrcode"></i></a>
                            <?php endif; ?>
                            <a href="?delete=<?= $r['id'] ?>&filter=<?= $filter ?>" class="btn-icon-sm btn-danger" title="Delete" onclick="return confirm('Delete this RSVP?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?><tr><td colspan="9" class="empty-row"><i class="fas fa-inbox"></i> No RSVPs found</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
</body>
</html>
