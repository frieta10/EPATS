<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$db = getDB();
$success = $error = '';

// Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_FILES['photos'])) {
        $files   = $_FILES['photos'];
        $count   = count($files['name']);
        $caption = trim($_POST['caption'] ?? '');
        $uploaded = 0;
        for ($i = 0; $i < $count; $i++) {
            $file = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];
            $fn = handleUpload($file, 'gallery');
            if ($fn) {
                $db->prepare('INSERT INTO gallery (filename, original_name, caption) VALUES (?, ?, ?)')
                   ->execute([$fn, $file['name'], $caption]);
                $uploaded++;
            }
        }
        $success = "Uploaded $uploaded photo(s).";
    }
}

// Delete
if (!empty($_GET['delete'])) {
    $row = $db->prepare('SELECT filename FROM gallery WHERE id = ?');
    $row->execute([(int)$_GET['delete']]);
    $g = $row->fetch();
    if ($g) {
        @unlink(UPLOAD_PATH . $g['filename']);
        $db->prepare('DELETE FROM gallery WHERE id = ?')->execute([(int)$_GET['delete']]);
    }
    header('Location: ' . BASE_URL . '/admin/gallery.php');
    exit;
}

$photos = $db->query('SELECT * FROM gallery ORDER BY created_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gallery — Admin</title>
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
        <div><h1 class="page-title">Photo Gallery</h1><p class="page-subtitle"><?= count($photos) ?> photos</p></div>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($success) ?></div><?php endif; ?>

    <!-- Upload Zone -->
    <div class="upload-zone" id="uploadZone">
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <div class="drop-area" id="dropArea">
                <i class="fas fa-cloud-arrow-up"></i>
                <p>Drag & drop photos here or <strong>click to browse</strong></p>
                <small>JPG, PNG, GIF, WebP — up to 10MB each</small>
                <input type="file" name="photos[]" id="photoInput" multiple accept="image/*" style="display:none">
            </div>
            <div id="uploadPreviewGrid" class="upload-preview-grid"></div>
            <div class="form-group" style="max-width:400px;margin-top:1rem">
                <label>Caption (optional, applied to all)</label>
                <input type="text" name="caption" placeholder="e.g. Engagement Session">
            </div>
            <button type="submit" class="btn btn-primary" id="uploadBtn" style="display:none">
                <i class="fas fa-upload"></i> Upload Photos
            </button>
        </form>
    </div>

    <!-- Gallery Grid -->
    <div class="gallery-grid" id="galleryGrid">
        <?php foreach ($photos as $photo): ?>
        <div class="gallery-item">
            <img src="<?= e(getImageUrl($photo['filename'])) ?>" alt="<?= e($photo['caption'] ?: '') ?>" loading="lazy">
            <div class="gallery-overlay">
                <p><?= e($photo['caption'] ?: $photo['original_name']) ?></p>
                <div class="gallery-actions">
                    <a href="<?= e(getImageUrl($photo['filename'])) ?>" target="_blank" class="btn-icon-sm"><i class="fas fa-expand"></i></a>
                    <a href="?delete=<?= $photo['id'] ?>" class="btn-icon-sm btn-danger" onclick="return confirm('Delete photo?')"><i class="fas fa-trash"></i></a>
                </div>
            </div>
            <?php if ($photo['is_cover']): ?><span class="gallery-badge">Cover</span><?php endif; ?>
            <?php if ($photo['is_couple_photo']): ?><span class="gallery-badge gallery-badge--purple">Couple</span><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (!$photos): ?>
        <div class="empty-state"><i class="fas fa-images"></i><p>No photos uploaded yet.</p></div>
        <?php endif; ?>
    </div>
</div>
</div>

<script>
const dropArea = document.getElementById('dropArea');
const photoInput = document.getElementById('photoInput');
const previewGrid = document.getElementById('uploadPreviewGrid');
const uploadBtn = document.getElementById('uploadBtn');

dropArea.addEventListener('click', () => photoInput.click());
dropArea.addEventListener('dragover', e => { e.preventDefault(); dropArea.classList.add('drag-over'); });
dropArea.addEventListener('dragleave', () => dropArea.classList.remove('drag-over'));
dropArea.addEventListener('drop', e => {
    e.preventDefault(); dropArea.classList.remove('drag-over');
    photoInput.files = e.dataTransfer.files;
    showPreviews(e.dataTransfer.files);
});
photoInput.addEventListener('change', () => showPreviews(photoInput.files));

function showPreviews(files) {
    previewGrid.innerHTML = '';
    uploadBtn.style.display = files.length ? 'inline-flex' : 'none';
    Array.from(files).forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            const div = document.createElement('div');
            div.className = 'upload-preview-item';
            div.innerHTML = `<img src="${e.target.result}"><span>${file.name}</span>`;
            previewGrid.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
}
</script>
</body>
</html>
