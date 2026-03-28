<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$db = getDB();
$success = $error = '';

// Add guest
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $table = trim($_POST['table_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    if ($name) {
        $token = generateToken(16);
        $db->prepare('INSERT INTO guests (name, email, phone, invite_token, table_number, notes) VALUES (?, ?, ?, ?, ?, ?)')
           ->execute([$name, $email, $phone, $token, $table, $notes]);
        $success = 'Guest added! Their personal invite link is: ' . BASE_URL . '/?token=' . $token;
    } else {
        $error = 'Name is required.';
    }
}

// Delete
if (!empty($_GET['delete'])) {
    $db->prepare('DELETE FROM guests WHERE id = ?')->execute([(int)$_GET['delete']]);
    header('Location: ' . BASE_URL . '/admin/guests.php');
    exit;
}

// Bulk import CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
    if (!empty($_FILES['csv']) && $_FILES['csv']['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($_FILES['csv']['tmp_name'], 'r');
        $header = fgetcsv($handle); // skip header
        $count  = 0;
        $stmt   = $db->prepare('INSERT INTO guests (name, email, phone, invite_token) VALUES (?, ?, ?, ?) ON CONFLICT (invite_token) DO NOTHING');
        while (($row = fgetcsv($handle)) !== false) {
            $n = trim($row[0] ?? '');
            $e = trim($row[1] ?? '');
            $p = trim($row[2] ?? '');
            if ($n) {
                $stmt->execute([$n, $e, $p, generateToken(16)]);
                $count++;
            }
        }
        fclose($handle);
        $success = "Imported $count guests.";
    }
}

$guests = $db->query("
    SELECT g.*,
           r.attending,
           r.qr_token,
           r.created_at AS rsvp_at
    FROM guests g
    LEFT JOIN rsvp_responses r ON r.guest_id = g.id
    ORDER BY g.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Guest List — Admin</title>
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
        <div><h1 class="page-title">Guest List</h1><p class="page-subtitle"><?= count($guests) ?> guests</p></div>
        <button class="btn btn-primary" onclick="document.getElementById('addGuestModal').style.display='flex'"><i class="fas fa-plus"></i> Add Guest</button>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-triangle-exclamation"></i> <?= e($error) ?></div><?php endif; ?>

    <!-- Bulk import -->
    <div class="import-bar">
        <form method="POST" enctype="multipart/form-data" class="import-form">
            <input type="hidden" name="action" value="import">
            <label class="btn btn-outline"><i class="fas fa-file-csv"></i> Import CSV <input type="file" name="csv" accept=".csv" onchange="this.form.submit()" style="display:none"></label>
        </form>
        <a href="#" onclick="downloadSampleCsv()" class="btn btn-outline btn-sm"><i class="fas fa-download"></i> Sample CSV</a>
    </div>

    <div class="table-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>#</th><th>Name</th><th>Email / Phone</th><th>Table</th><th>RSVP Status</th><th>Invite Link</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($guests as $i => $g): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= e($g['name']) ?></strong><?= $g['notes'] ? '<p class="row-message">' . e($g['notes']) . '</p>' : '' ?></td>
                        <td><?= e($g['email'] ?: '—') ?><br><small><?= e($g['phone'] ?: '') ?></small></td>
                        <td><?= e($g['table_number'] ?: '—') ?></td>
                        <td>
                            <?php if ($g['attending']): ?>
                                <span class="badge badge-<?= $g['attending'] ?>"><?= ucfirst($g['attending']) ?></span>
                            <?php else: ?>
                                <span class="badge badge-pending">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="link-copy">
                                <input type="text" value="<?= BASE_URL ?>/?token=<?= $g['invite_token'] ?>" readonly class="link-input">
                                <button class="btn-icon-sm" onclick="copyText(this)" title="Copy"><i class="fas fa-copy"></i></button>
                            </div>
                        </td>
                        <td>
                            <?php if ($g['qr_token']): ?>
                            <a href="<?= BASE_URL ?>/guest-portal.php?token=<?= $g['qr_token'] ?>" target="_blank" class="btn-icon-sm" title="Portal"><i class="fas fa-qrcode"></i></a>
                            <?php endif; ?>
                            <a href="?delete=<?= $g['id'] ?>" class="btn-icon-sm btn-danger" onclick="return confirm('Remove guest?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$guests): ?><tr><td colspan="7" class="empty-row">No guests yet. Add your first guest!</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- Add Guest Modal -->
<div id="addGuestModal" class="modal-overlay" style="display:none" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Add Guest</h3>
            <button onclick="document.getElementById('addGuestModal').style.display='none'" class="modal-close">&times;</button>
        </div>
        <form method="POST" class="modal-form">
            <input type="hidden" name="action" value="add">
            <div class="form-group"><label>Full Name *</label><input type="text" name="name" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email"></div>
            <div class="form-group"><label>Phone</label><input type="tel" name="phone"></div>
            <div class="form-group"><label>Table Number</label><input type="text" name="table_number" placeholder="e.g. Table 5"></div>
            <div class="form-group"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Guest</button>
        </form>
    </div>
</div>

<script>
function copyText(btn) {
    const inp = btn.previousElementSibling;
    inp.select(); document.execCommand('copy');
    btn.innerHTML = '<i class="fas fa-check"></i>';
    setTimeout(() => btn.innerHTML = '<i class="fas fa-copy"></i>', 2000);
}
function downloadSampleCsv() {
    const a = document.createElement('a');
    a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent('Name,Email,Phone\nJohn Doe,john@email.com,+1234567890\nJane Smith,jane@email.com,');
    a.download = 'guest_sample.csv'; a.click();
}
</script>
</body>
</html>
