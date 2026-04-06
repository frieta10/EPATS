<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$nav = [
    ['index.php',    'fas fa-gauge-high',      'Dashboard'],
    ['events.php',   'fas fa-calendar-star',   'My Events'],
    ['guests.php',   'fas fa-users',            'Guest List'],
    ['rsvp.php',     'fas fa-envelope-open',    'RSVP Responses'],
    ['wishes.php',   'fas fa-lock',             'Time Capsule'],
    ['gallery.php',  'fas fa-images',           'Photo Gallery'],
];
?>
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-logo">
        <i class="fas fa-heart"></i>
        <span>E-Invitation</span>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($nav as [$page, $icon, $label]): ?>
        <a href="<?= BASE_URL ?>/admin/<?= $page ?>"
           class="nav-item <?= $currentPage === $page ? 'nav-item--active' : '' ?>">
            <i class="<?= $icon ?>"></i>
            <span><?= $label ?></span>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="<?= BASE_URL ?>/" target="_blank" class="nav-item">
            <i class="fas fa-external-link"></i><span>View Live</span>
        </a>
        <a href="<?= BASE_URL ?>/admin/logout.php" class="nav-item nav-item--danger">
            <i class="fas fa-right-from-bracket"></i><span>Logout</span>
        </a>
    </div>
</aside>
