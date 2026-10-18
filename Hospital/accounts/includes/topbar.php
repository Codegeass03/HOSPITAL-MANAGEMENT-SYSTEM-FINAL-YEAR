<header class="topbar">
  <input type="search" placeholder="Search patients, records, or staff...">
  <div class="user-info">
    <span>Thu, Sep 25, 2025, 12:25 PM</span>
    <i class="fa fa-bell"></i>
    <img src="assets/images/avatar.png" alt="User" class="avatar">
  </div>
</header>
<header class="topbar">
  <input type="search" placeholder="Search patients, records, or staff...">
  <div class="user-info">
    <span>Thu, Sep 25, 2025, 12:25 PM</span>
    <i class="fa fa-bell"></i>
    <img src="assets/images/avatar.png" alt="User" class="avatar">
  </div>
</header>
<script>
// Apply saved theme on page load
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    
    // Apply compact mode if enabled
    if (localStorage.getItem('compactMode') === 'true') {
        document.body.classList.add('compact-mode');
    }
    
    // Apply sidebar state
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        document.body.classList.add('sidebar-collapsed');
    }
});
</script>