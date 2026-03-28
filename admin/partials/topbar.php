<header class="admin-topbar">
    <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    <div class="topbar-right">
        <span class="topbar-user"><i class="fas fa-user-circle"></i> <?= e($_SESSION['admin_user'] ?? 'Admin') ?></span>
    </div>
</header>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    document.getElementById('adminSidebar').classList.toggle('sidebar--open');
});
</script>
