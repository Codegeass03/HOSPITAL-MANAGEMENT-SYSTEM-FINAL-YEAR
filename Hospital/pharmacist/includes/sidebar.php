<aside class="sidebar">
  <div class="logo">
    <h2>AuraCare HMS</h2>
    <!-- <small>ADMIN CONTROL PANEL</small> -->
  </div>
  <nav>
    <ul class="sidebar-menu">
      <li class="active">
        <a href="dashboard.php">
          <i class="fas fa-home"></i>
          <span>Dashboard</span>
        </a>
      </li>
      
      <li class="has-submenu">
        <a href="#" class="submenu-toggle">
          <i class="fas fa-users"></i>
          <span>Staff Management</span>
          <i class="fas fa-chevron-down dropdown-arrow"></i>
        </a>
        <ul class="submenu">
          <li><a href="manage_users.php">Employee List</a></li>
          <li><a href="departments.php">Departments</a></li>
          <li><a href="doctors_schedule.php">Doctor Schedule</a></li>
          <li><a href="services.php">Services</a></li>
        </ul>
      </li>
      
      <li>
        <a href="add_new_patients.php">
          <i class="fas fa-user-injured"></i>
          <span>Patient Management</span>
        </a>
      </li>
      
      <li class="has-submenu">
        <a href="#" class="submenu-toggle">
          <i class="fas fa-calendar-check"></i>
          <span>Appointments</span>
          <i class="fas fa-chevron-down dropdown-arrow"></i>
        </a>
        <ul class="submenu">
          <li><a href="appointment_scheduling.php">Schedule Appointment</a></li>
          <li><a href="calendar_view.php">Calendar View</a></li>
          <li><a href="appointment_requests.php">Requests & Approvals</a></li>
        </ul>
      </li>

      <li class="has-submenu">
        <a href="#" class="submenu-toggle">
          <i class="fas fa-file-medical"></i>
          <span>EHR System</span>
          <i class="fas fa-chevron-down dropdown-arrow"></i>
        </a>
        <ul class="submenu">
          <li><a href="ehr_dashboard.php">EHR Dashboard</a></li>
          <li><a href="patient_search.php">Patient Search</a></li>
          <li><a href="clinical_notes.php">Clinical Notes</a></li>
          <li><a href="lab_orders.php">Lab Orders</a></li>
          <li><a href="prescriptions.php">Medication Orders</a></li>
          <li><a href="medical_history.php">Medical History</a></li>
          <li><a href="vitals_history.php">Vitals History</a></li>
        </ul>
      </li>
      
      <li>
        <a href="pharmacy.php">
          <i class="fas fa-capsules"></i>
          <span>Pharmacy</span>
        </a>
      </li>
      
      <li>
        <a href="lab_module.php">
          <i class="fas fa-vials"></i>
          <span>Laboratory</span>
        </a>
      </li>

      <li>
        <a href="billing_dashboard.php">
          <i class="fas fa-money-bill-wave"></i>
          <span>Billing & Finance</span>
        </a>
      </li>
      
      <li class="has-submenu">
        <a href="#" class="submenu-toggle">
          <i class="fas fa-warehouse"></i>
          <span>Inventory & Supply</span>
          <i class="fas fa-chevron-down dropdown-arrow"></i>
        </a>
        <ul class="submenu">
          <li><a href="supplier.php">Manage Suppliers</a></li>
          <li><a href="inventorylist.php">Manage Inventory</a></li>
        </ul>
      </li>
      
      <li class="has-submenu">
        <a href="#" class="submenu-toggle">
          <i class="fas fa-chart-line"></i>
          <span>Reports & Analytics</span>
          <i class="fas fa-chevron-down dropdown-arrow"></i>
        </a>
        <ul class="submenu">
          <li><a href="purchase_report.php">PURCHASE REPORT</a></li>
          <li><a href="bills.php">SALES REPORT</a></li>
          <li><a href="inventory.php">STOCK REPORT</a></li>
          <li><a href="supplier_report.php">SUPPLIERS REPORT</a></li>
          <li><a href="return_product_list.php">RETURNS PRODUCTS REPORT</a></li>
          <li><a href="expiry_report.php">EXPIRED PRODUCTS REPORT</a></li>
        </ul>
      </li>
      
      <li>
        <a href="wards.php">
          <i class="fas fa-bed"></i>
          <span>Ward & Bed Management</span>
        </a>
      </li>
     
       <div class="nav-section">
      <div class="section-label">System</div>
      <ul class="sidebar-menu">
        <li>
          <a href="settings.php" class="menu-item">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
          </a>
        </li>
          <a href="logout.php" class="menu-item logout-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
          </a>
        </li>
      </ul>
    </div>
  </nav>

  <div class="sidebar-footer">
    <div class="user-profile">
      <div class="user-avatar">
        <i class="fas fa-user-shield"></i>
      </div>
      <div class="user-info">
        <span class="user-name">Administrator</span>
        <span class="user-role">Super Admin</span>
      </div>
    </div>
  </div>
</aside>

<style>
/* Sidebar Base Styles */
.sidebar {
  position: fixed;
  left: 0;
  top: 0;
  width: 280px;
  height: 100vh;
  background: linear-gradient(180deg, #0c4da2 0%, #07377c 100%);
  color: white;
  overflow-y: auto;
  overflow-x: hidden;
  box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
  z-index: 1000;
  transition: all 0.3s ease;
  display: flex;
  flex-direction: column;
}

/* Compact Sidebar */
.sidebar.compact {
  width: 80px;
}

.sidebar.compact .logo-text,
.sidebar.compact .section-label,
.sidebar.compact .menu-item span,
.sidebar.compact .user-info,
.sidebar.compact .menu-badge,
.sidebar.compact .dropdown-arrow {
  display: none !important;
}

.sidebar.compact .submenu {
  display: none !important;
}

/* Custom Scrollbar */
.sidebar::-webkit-scrollbar {
  width: 6px;
}

.sidebar::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 3px;
  transition: background 0.3s ease;
}

.sidebar::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.5);
}

/* Sidebar Header */
.sidebar-header {
  padding: 1.5rem 1.5rem 1rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(255, 255, 255, 0.05);
  position: sticky;
  top: 0;
  z-index: 10;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.logo {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.logo-icon {
  width: 40px;
  height: 40px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.logo-text h2 {
  margin: 0;
  font-size: 1.3rem;
  font-weight: 700;
  color: white;
  line-height: 1.2;
}

.logo-text small {
  display: block;
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.8);
  font-weight: 400;
  margin-top: 0.1rem;
}

.sidebar-toggle {
  background: rgba(255, 255, 255, 0.1);
  border: none;
  color: white;
  width: 32px;
  height: 32px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 0.9rem;
}

.sidebar-toggle:hover {
  background: rgba(255, 255, 255, 0.2);
  transform: rotate(90deg);
}

/* Navigation */
.sidebar-nav {
  flex: 1;
  padding: 1rem 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.nav-section {
  margin-bottom: 0.5rem;
}

.section-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.6);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 0.5rem 1.5rem 0.25rem;
  margin-bottom: 0.25rem;
}

/* Menu List */
.sidebar-menu {
  list-style: none;
  padding: 0;
  margin: 0;
}

.sidebar-menu li {
  margin: 0;
  position: relative;
}

/* Menu Item Styles */
.menu-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.875rem 1.5rem;
  color: rgba(255, 255, 255, 0.9);
  text-decoration: none;
  transition: all 0.3s ease;
  font-size: 0.9rem;
  font-weight: 500;
  border-left: 3px solid transparent;
  position: relative;
}

.menu-item:hover {
  background: rgba(255, 255, 255, 0.12);
  border-left-color: #4dabf7;
  color: white;
  padding-left: 1.75rem;
}

.menu-item.active {
  background: rgba(255, 255, 255, 0.15);
  border-left-color: #51cf66;
  color: white;
  font-weight: 600;
}

.menu-item i:first-child {
  font-size: 1.1rem;
  width: 20px;
  text-align: center;
  flex-shrink: 0;
}

.menu-item span {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.menu-badge {
  background: rgba(255, 255, 255, 0.2);
  color: white;
  padding: 0.2rem 0.5rem;
  border-radius: 12px;
  font-size: 0.7rem;
  font-weight: 600;
}

/* Submenu Styles */
.has-submenu > .menu-item {
  position: relative;
}

.dropdown-arrow {
  transition: transform 0.3s ease;
  font-size: 0.8rem;
  color: rgba(255, 255, 255, 0.7);
}

.has-submenu.active .dropdown-arrow {
  transform: rotate(180deg);
  color: white;
}

.submenu {
  list-style: none;
  padding: 0;
  margin: 0;
  background: rgba(0, 0, 0, 0.2);
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.has-submenu.active .submenu {
  max-height: 500px;
}

.submenu li a {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1.5rem 0.75rem 3.5rem;
  color: rgba(255, 255, 255, 0.8);
  text-decoration: none;
  transition: all 0.3s ease;
  font-size: 0.85rem;
  border-left: 3px solid transparent;
}

.submenu li a:hover {
  background: rgba(255, 255, 255, 0.08);
  border-left-color: #ffd43b;
  color: white;
  padding-left: 4rem;
}

.submenu li a i {
  font-size: 0.9rem;
  width: 16px;
  text-align: center;
}

/* Logout Item */
.logout-item {
  color: #ff6b6b !important;
}

.logout-item:hover {
  background: rgba(255, 107, 107, 0.15) !important;
  border-left-color: #ff6b6b !important;
  color: #ff6b6b !important;
}

/* Sidebar Footer */
.sidebar-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(255, 255, 255, 0.05);
  margin-top: auto;
}

.user-profile {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.user-avatar {
  width: 40px;
  height: 40px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  flex-shrink: 0;
}

.user-info {
  display: flex;
  flex-direction: column;
}

.user-name {
  font-size: 0.9rem;
  font-weight: 600;
  color: white;
}

.user-role {
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.7);
}

/* Main Content Adjustment */
.main-content {
  margin-left: 280px;
  min-height: 100vh;
  padding: 2rem;
  background-color: #f8f9fa;
  transition: margin-left 0.3s ease;
}

.main-content.expanded {
  margin-left: 80px;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .sidebar {
    width: 80px;
    transform: translateX(-100%);
  }
  
  .sidebar.mobile-open {
    transform: translateX(0);
  }
  
  .main-content {
    margin-left: 0;
  }
  
  .mobile-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
  }
  
  .mobile-overlay.active {
    display: block;
  }
}

@media (max-width: 768px) {
  .main-content {
    padding: 1rem;
  }
}

/* Animation for menu items */
@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateX(-10px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.sidebar-menu li {
  animation: slideIn 0.3s ease forwards;
}

.sidebar-menu li:nth-child(1) { animation-delay: 0.05s; }
.sidebar-menu li:nth-child(2) { animation-delay: 0.1s; }
.sidebar-menu li:nth-child(3) { animation-delay: 0.15s; }
.sidebar-menu li:nth-child(4) { animation-delay: 0.2s; }
.sidebar-menu li:nth-child(5) { animation-delay: 0.25s; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mainContent = document.querySelector('.main-content');
    const currentPath = window.location.pathname.split('/').pop();
    const mobileOverlay = document.createElement('div');
    
    mobileOverlay.className = 'mobile-overlay';
    document.body.appendChild(mobileOverlay);

    // Toggle sidebar compact mode
    sidebarToggle.addEventListener('click', function() {
        sidebar.classList.toggle('compact');
        mainContent.classList.toggle('expanded');
        
        // Update toggle icon
        const icon = this.querySelector('i');
        if (sidebar.classList.contains('compact')) {
            icon.className = 'fas fa-chevron-right';
        } else {
            icon.className = 'fas fa-bars';
        }
    });

    // Mobile overlay click to close sidebar
    mobileOverlay.addEventListener('click', function() {
        sidebar.classList.remove('mobile-open');
        this.classList.remove('active');
    });

    // Handle dynamic 'active' class on page load
    document.querySelectorAll('.menu-item').forEach(item => {
        const href = item.getAttribute('href');
        if (href && href !== '#' && currentPath === href.split('/').pop()) {
            // Remove active class from all items
            document.querySelectorAll('.menu-item').forEach(i => i.classList.remove('active'));
            document.querySelectorAll('.has-submenu').forEach(i => i.classList.remove('active'));
            
            // Add active class to current item
            item.classList.add('active');
            
            // If it's in a submenu, activate parent
            const parentSubmenu = item.closest('.has-submenu');
            if (parentSubmenu) {
                parentSubmenu.classList.add('active');
            }
        }
    });

    // Handle Submenu Toggle
    document.querySelectorAll('.submenu-toggle').forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const parentLi = this.closest('.has-submenu');
            
            // Close other submenus
            document.querySelectorAll('.has-submenu.active').forEach(item => {
                if (item !== parentLi) {
                    item.classList.remove('active');
                }
            });
            
            // Toggle current submenu
            parentLi.classList.toggle('active');
        });
    });

    // Close submenus when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024 && !sidebar.contains(e.target)) {
            document.querySelectorAll('.has-submenu.active').forEach(item => {
                item.classList.remove('active');
            });
        }
    });

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            sidebar.classList.remove('mobile-open');
            mobileOverlay.classList.remove('active');
        }
    });
});

// Mobile menu toggle function (call this from your main content if needed)
function toggleMobileMenu() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.mobile-overlay');
    
    sidebar.classList.toggle('mobile-open');
    overlay.classList.toggle('active');
}
</script>
<style>
/* Sidebar Base Styles */
.sidebar {
  position: fixed;
  left: 0;
  top: 0;
  width: 260px;
  height: 100vh;
  background: linear-gradient(180deg, #07377c 0%, #002c74 100%); 
  color: white;
  overflow-y: auto;
  overflow-x: hidden;
  box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
  z-index: 1000;
}

/* Custom Scrollbar */
.sidebar::-webkit-scrollbar {
  width: 6px;
}

.sidebar::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
}

.sidebar::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.4);
  border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.6);
}

/* Logo Section */
.sidebar .logo {
  padding: 1.5rem 1.25rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.2);
  background: #07377c;
  position: sticky;
  top: 0;
  z-index: 10;
}

.sidebar .logo h2 {
  margin: 0;
  font-size: 1.4rem;
  font-weight: 700;
  color: white;
  letter-spacing: 0.5px;
}

.sidebar .logo small {
  display: block;
  margin-top: 0.25rem;
  font-size: 0.8rem;
  color: rgba(255, 255, 255, 0.9);
  font-weight: 400;
}

/* Navigation */
.sidebar nav {
  padding: 0;
}

/* Menu List */
.sidebar-menu {
  list-style: none;
  padding: 0;
  margin: 0;
}

.sidebar-menu li {
  margin: 0;
  position: relative;
}

/* Menu Link Styles */
.sidebar-menu li a {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1.0rem 1.25rem;
  color: white;
  text-decoration: none;
  transition: all 0.2s ease;
  font-size: 0.95rem;
  font-weight: 500;
  border-left: 4px solid transparent;
}

.sidebar-menu li a:hover {
  background: rgba(255, 255, 255, 0.15);
  border-left-color: white;
  padding-left: 1.5rem;
}

.sidebar-menu li.active a {
  background: rgba(255, 255, 255, 0.2);
  border-left-color: #f7b44d;
  font-weight: 600;
}

.sidebar-menu li a i {
  font-size: 1.2rem;
  width: 25px;
  text-align: center;
  flex-shrink: 0;
}

.sidebar-menu li a span {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Submenu Styles */
.sidebar-menu .has-submenu > a {
  position: relative;
}

.dropdown-arrow {
  transition: transform 0.3s ease;
  font-size: 0.8rem;
  margin-left: auto;
}

.has-submenu.active .dropdown-arrow {
  transform: rotate(180deg);
}

.submenu {
  list-style: none;
  padding: 0;
  margin: 0;
  background: rgba(0, 0, 0, 0.25);
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.4s ease-out;
}

.has-submenu.active .submenu {
  max-height: 500px;
}

.submenu li a {
  padding: 0.75rem 1.25rem 0.75rem 3.5rem;
  font-size: 0.9rem;
  border-left: none;
}

.submenu li a:hover {
  background: rgba(255, 255, 255, 0.1);
  padding-left: 4rem;
  color: #f7b44d;
}

/* Global Styles */
body {
  margin: 0;
  padding: 0;
}

.main-content {
  margin-left: 260px;
  min-height: 100vh;
  padding: 2rem;
  background-color: #f8f9fa;
}

/* Responsive Design */
@media (max-width: 768px) {
  .sidebar {
    width: 70px;
  }
  
  .sidebar .logo h2,
  .sidebar .logo small {
    display: none;
  }
  
  .sidebar-menu li a span {
    display: none;
  }
  
  .sidebar-menu li a {
    justify-content: center;
    padding: 1rem 0;
  }
  
  .sidebar-menu li a i {
    margin: 0;
    width: auto;
  }
  
  .dropdown-arrow {
    display: none;
  }
  
  .submenu {
    display: none;
  }
  
  .main-content {
    margin-left: 70px;
  }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const submenuToggles = document.querySelectorAll('.submenu-toggle');
    const currentPath = window.location.pathname.split('/').pop();

    // Handle dynamic 'active' class on page load
    document.querySelectorAll('.sidebar-menu a').forEach(link => {
        const linkPath = link.getAttribute('href').split('/').pop();

        if (linkPath === currentPath && currentPath !== '#') {
            document.querySelectorAll('.sidebar-menu li').forEach(li => li.classList.remove('active'));
            
            let currentLi = link.closest('li');
            currentLi.classList.add('active');

            let parentSubmenu = currentLi.closest('.has-submenu');
            if (parentSubmenu) {
                parentSubmenu.classList.add('active');
            }
        }
    });

    // Handle Submenu Toggle functionality
    submenuToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const parentLi = this.closest('.has-submenu'); 
            
            document.querySelectorAll('.has-submenu.active').forEach(item => {
                if (item !== parentLi) { 
                    item.classList.remove('active');
                }
            });
            
            parentLi.classList.toggle('active');
        });
    });

    // Prevent immediate parent submenu from closing when a sub-item is clicked
    document.querySelectorAll('.submenu a').forEach(subLink => {
        subLink.addEventListener('click', function(e) {
            // Navigation will happen, state handled on page load
        });
    });
});
</script>