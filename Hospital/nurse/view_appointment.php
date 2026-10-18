<?php
session_start();
include "includes/db_connect.php";
// include "includes/sidebar.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}

// Check if appointment ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Appointment ID is required.";
    header("Location: appointment_scheduling.php");
    exit();
}

$appointment_id = $_GET['id'];

// Fetch appointment details
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
        p.patient_id,
        p.date_of_birth,
        p.gender,
        p.phone,
        p.email,
        p.address,
        CONCAT(d.first_name, ' ', d.last_name) AS doctor_name,
        d.phone AS doctor_phone,
        d.email AS doctor_email,
        d.specialization
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users d ON a.doctor_id = d.id
    WHERE a.id = ?
");

$stmt->execute([$appointment_id]);
$appointment = $stmt->fetch();

if (!$appointment) {
    $_SESSION['error'] = "Appointment not found.";
    header("Location: appointment_scheduling.php");
    exit();
}

// Get messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : null;
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Appointment - MediCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content {
            margin-left: 260px;
            padding: 2rem;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .page-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0;
            color: #334155;
            font-size: 1.875rem;
            font-weight: 600;
        }
        
        .action-btns {
            display: flex;
            gap: 0.75rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .btn-secondary {
            background: white;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .appointment-details {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .detail-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        
        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .info-grid {
            display: grid;
            gap: 1rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 500;
            color: #374151;
            font-size: 0.875rem;
        }
        
        .info-value {
            color: #6b7280;
            font-size: 0.875rem;
            text-align: right;
            max-width: 60%;
        }
        
        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: capitalize;
        }
        
        .status-scheduled { background: #dbeafe; color: #1e40af; }
        .status-confirmed { background: #d1fae5; color: #065f46; }
        .status-completed { background: #e0e7ff; color: #4338ca; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-no_show { background: #fef3c7; color: #92400e; }
        
        .patient-info, .doctor-info {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .patient-name, .doctor-name {
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.25rem;
        }
        
        .patient-details, .doctor-details {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 0.75rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .timeline-item:last-child {
            margin-bottom: 0;
        }
        
        .timeline-marker {
            position: absolute;
            left: -2rem;
            top: 0.25rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background: #3b82f6;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #3b82f6;
        }
        
        .timeline-content {
            background: white;
            padding: 1rem;
            border-radius: 0.5rem;
            border: 1px solid #e5e7eb;
        }
        
        .timeline-title {
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.25rem;
        }
        
        .timeline-description {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        
        .timeline-time {
            font-size: 0.75rem;
            color: #9ca3af;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .notes-section {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>
        
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-eye"></i>
                View Appointment
            </h1>
            <div class="action-btns">
                <a href="appointment_scheduling.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Appointments
                </a>
                <a href="edit_appointment.php?id=<?php echo $appointment_id; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i>
                    Edit Appointment
                </a>
                <?php if ($appointment['status'] == 'scheduled' || $appointment['status'] == 'confirmed'): ?>
                <a href="cancel_appointment.php?id=<?php echo $appointment_id; ?>" 
                   class="btn btn-danger"
                   onclick="return confirm('Are you sure you want to cancel this appointment?');">
                    <i class="fas fa-times"></i>
                    Cancel Appointment
                </a>
                <?php endif; ?>
                <?php if ($appointment['status'] == 'scheduled'): ?>
                <a href="confirm_appointment.php?id=<?php echo $appointment_id; ?>" 
                   class="btn btn-success"
                   onclick="return confirm('Mark this appointment as confirmed?');">
                    <i class="fas fa-check"></i>
                    Confirm
                </a>
                <?php endif; ?>
                <?php if ($appointment['status'] == 'confirmed'): ?>
                <a href="complete_appointment.php?id=<?php echo $appointment_id; ?>" 
                   class="btn btn-warning"
                   onclick="return confirm('Mark this appointment as completed?');">
                    <i class="fas fa-clipboard-check"></i>
                    Mark Complete
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="appointment-details">
            <div class="detail-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-info-circle"></i>
                        Appointment Information
                    </h2>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Appointment Number</span>
                            <span class="info-value"><?php echo htmlspecialchars($appointment['appointment_number']); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Status</span>
                            <span class="info-value">
                                <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Date & Time</span>
                            <span class="info-value">
                                <?php echo date('F j, Y', strtotime($appointment['appointment_date'])); ?><br>
                                <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Appointment Type</span>
                            <span class="info-value"><?php echo ucfirst($appointment['appointment_type']); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Duration</span>
                            <span class="info-value"><?php echo $appointment['duration'] ? $appointment['duration'] . ' minutes' : 'Not specified'; ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Reason for Visit</span>
                            <span class="info-value"><?php echo htmlspecialchars($appointment['reason']); ?></span>
                        </div>
                        
                        <?php if ($appointment['notes']): ?>
                        <div class="info-item">
                            <span class="info-label">Additional Notes</span>
                            <span class="info-value"><?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="info-item">
                            <span class="info-label">Created On</span>
                            <span class="info-value">
                                <?php echo date('M j, Y g:i A', strtotime($appointment['created_at'])); ?>
                            </span>
                        </div>
                        
                        <?php if ($appointment['updated_at'] && $appointment['updated_at'] != $appointment['created_at']): ?>
                        <div class="info-item">
                            <span class="info-label">Last Updated</span>
                            <span class="info-value">
                                <?php echo date('M j, Y g:i A', strtotime($appointment['updated_at'])); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div>
                <div class="detail-card" style="margin-bottom: 1.5rem;">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-user-injured"></i>
                            Patient Information
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="patient-info">
                            <div class="patient-name"><?php echo htmlspecialchars($appointment['patient_name']); ?></div>
                            <div class="patient-details">
                                <strong>Patient ID:</strong> <?php echo htmlspecialchars($appointment['patient_id']); ?><br>
                                <strong>Date of Birth:</strong> <?php echo date('M j, Y', strtotime($appointment['date_of_birth'])); ?><br>
                                <strong>Gender:</strong> <?php echo ucfirst($appointment['gender']); ?><br>
                                <strong>Phone:</strong> <?php echo htmlspecialchars($appointment['phone']); ?><br>
                                <?php if ($appointment['email']): ?>
                                <strong>Email:</strong> <?php echo htmlspecialchars($appointment['email']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="detail-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-user-md"></i>
                            Doctor Information
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="doctor-info">
                            <div class="doctor-name">Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></div>
                            <div class="doctor-details">
                                <?php if ($appointment['specialization']): ?>
                                <strong>Specialization:</strong> <?php echo htmlspecialchars($appointment['specialization']); ?><br>
                                <?php endif; ?>
                                <?php if ($appointment['doctor_phone']): ?>
                                <strong>Phone:</strong> <?php echo htmlspecialchars($appointment['doctor_phone']); ?><br>
                                <?php endif; ?>
                                <?php if ($appointment['doctor_email']): ?>
                                <strong>Email:</strong> <?php echo htmlspecialchars($appointment['doctor_email']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="notes-section">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-history"></i>
                    Appointment History
                </h2>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Appointment Created</div>
                            <div class="timeline-description">Appointment was scheduled in the system</div>
                            <div class="timeline-time"><?php echo date('M j, Y g:i A', strtotime($appointment['created_at'])); ?></div>
                        </div>
                    </div>
                    
                    <?php if ($appointment['status'] == 'confirmed'): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Appointment Confirmed</div>
                            <div class="timeline-description">Patient confirmed their attendance</div>
                            <div class="timeline-time">Status updated to confirmed</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($appointment['status'] == 'completed'): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Appointment Completed</div>
                            <div class="timeline-description">Appointment was successfully completed</div>
                            <div class="timeline-time">Status updated to completed</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($appointment['status'] == 'cancelled'): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Appointment Cancelled</div>
                            <div class="timeline-description">
                                <?php echo $appointment['cancellation_reason'] ? 'Reason: ' . htmlspecialchars($appointment['cancellation_reason']) : 'Appointment was cancelled'; ?>
                            </div>
                            <div class="timeline-time">Status updated to cancelled</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($appointment['updated_at'] && $appointment['updated_at'] != $appointment['created_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Appointment Updated</div>
                            <div class="timeline-description">Appointment details were modified</div>
                            <div class="timeline-time"><?php echo date('M j, Y g:i A', strtotime($appointment['updated_at'])); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Add confirmation for status changes
        document.addEventListener('DOMContentLoaded', function() {
            const actionLinks = document.querySelectorAll('a[href*="confirm_appointment"], a[href*="complete_appointment"], a[href*="cancel_appointment"]');
            actionLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!confirm(this.getAttribute('onclick')?.replace("return confirm('", "").replace("');", "") || 'Are you sure?')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>
<?php include "includes/footer.php"; ?>

<aside class="sidebar">
  <div class="sidebar-header">
    <div class="logo">
      <div class="logo-icon">
        <i class="fas fa-heartbeat"></i>
      </div>
      <div class="logo-text">
        <h2>AuraCare HMS</h2>
        <small>EQUITY AFIA MEDICAL CENTER</small>
      </div>
    </div>
    <button class="sidebar-toggle" id="sidebarToggle">
      <i class="fas fa-bars"></i>
    </button>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section">
      <div class="section-label">Main Menu</div>
      <ul class="sidebar-menu">
        <li>
          <a href="dashboard.php" class="menu-item">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
            <!-- <span class="menu-badge">New</span> -->
          </a>
        </li>
      </ul>
    </div>

    

    <div class="nav-section">
      <div class="section-label">Patient Care</div>
      <ul class="sidebar-menu"> 
        <li>
          <a href="add_new_patients.php" class="menu-item">
            <i class="fas fa-user-injured"></i>
            <span>Patient Management</span>
          </a>
        </li>
        
        <li class="has-submenu">
          <a href="#" class="menu-item submenu-toggle">
            <i class="fas fa-calendar-check"></i>
            <span>Appointments</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
          </a>
          <ul class="submenu">
            <li><a href="appointment_scheduling.php"><i class="fas fa-plus-circle"></i>Schedule Appointment</a></li>
            <li><a href="calendar_view.php"><i class="fas fa-calendar-week"></i>Calendar View</a></li>
            <li><a href="appointment_requests.php"><i class="fas fa-clock"></i>Requests & Approvals</a></li>
          </ul>
        </li>

        <li class="has-submenu">
          <a href="#" class="menu-item submenu-toggle">
            <i class="fas fa-file-medical"></i>
            <span>EHR System</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
          </a>
          <ul class="submenu">
            <!-- <li><a href="ehr_dashboard.php"><i class="fas fa-tachometer-alt"></i>EHR Dashboard</a></li>
            <li><a href="patient_search.php"><i class="fas fa-search"></i>Patient Search</a></li>
            <li><a href="clinical_notes.php"><i class="fas fa-file-medical-alt"></i>Clinical Notes</a></li>
            <li><a href="lab_orders.php"><i class="fas fa-vial"></i>Lab Orders</a></li>
            <li><a href="prescriptions.php"><i class="fas fa-prescription"></i>Medication Orders</a></li>
            <li><a href="medical_history.php"><i class="fas fa-history"></i>Medical History</a></li> -->
            <li><a href="vitals_history.php"><i class="fas fa-heartbeat"></i>Vitals History</a></li>
          </ul>
        </li>
      </ul>
    </div>

    <!-- <div class="nav-section">
      <div class="section-label">Clinical Modules</div>
      <ul class="sidebar-menu">
        <li>
          <a href="pharmacy.php" class="menu-item">
            <i class="fas fa-capsules"></i>
            <span>Pharmacy</span>
          </a>
        </li>
        
        <li>
          <a href="lab_module.php" class="menu-item">
            <i class="fas fa-vials"></i>
            <span>Laboratory</span>
          </a>
        </li>

        <li>
          <a href="billing_dashboard.php" class="menu-item">
            <i class="fas fa-money-bill-wave"></i>
            <span>Billing & Finance</span>
          </a>
        </li>
        
        <li>
          <a href="wards.php" class="menu-item">
            <i class="fas fa-bed"></i>
            <span>Ward & Bed Management</span>
          </a>
        </li>
      </ul>
    </div>

    <div class="nav-section">
      <div class="section-label">Operations</div>
      <ul class="sidebar-menu">
        <li class="has-submenu">
          <a href="#" class="menu-item submenu-toggle">
            <i class="fas fa-warehouse"></i>
            <span>Inventory & Supply</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
          </a>
          <ul class="submenu">
            <li><a href="supplier.php"><i class="fas fa-truck"></i>Manage Suppliers</a></li>
            <li><a href="inventorylist.php"><i class="fas fa-boxes"></i>Manage Inventory</a></li>
          </ul>
        </li>
        
        <li class="has-submenu">
          <a href="#" class="menu-item submenu-toggle">
            <i class="fas fa-chart-line"></i>
            <span>Reports & Analytics</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
          </a>
          <ul class="submenu">
            <li><a href="purchase_report.php"><i class="fas fa-shopping-cart"></i>PURCHASE REPORT</a></li>
            <li><a href="bills.php"><i class="fas fa-receipt"></i>SALES REPORT</a></li>
            <li><a href="inventory.php"><i class="fas fa-box-open"></i>STOCK REPORT</a></li>
            <li><a href="supplier_report.php"><i class="fas fa-truck-loading"></i>SUPPLIERS REPORT</a></li>
            <li><a href="return_product_list.php"><i class="fas fa-undo-alt"></i>RETURNS PRODUCTS REPORT</a></li>
            <li><a href="expiry_report.php"><i class="fas fa-calendar-times"></i>EXPIRED PRODUCTS REPORT</a></li>
          </ul>
        </li>
      </ul>
    </div> -->

    <div class="nav-section">
      <div class="section-label">System</div>
      <ul class="sidebar-menu">
        <li>
          <a href="settings.php" class="menu-item">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
          </a>
        </li>
        <li>
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
        <span class="user-name">Nurse Station</span>
        <span class="user-role">Nurses</span>
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
  width: 290px;
  height: 100vh;
  background: linear-gradient(180deg, #0c4da2 0%, #07377c 100%);
  color: white;
  overflow-y: auto;
  overflow-x: hidden;
  box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
  z-index: 800;
  transition: all 0.3s ease;
  display: flex;
  flex-direction: column;
}

/* Compact Sidebar */
.sidebar.compact {
  width: 90px;
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
  gap: 1.5rem;
}

.logo-icon {
  width: 40px;
  height: 45px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  flex-shrink: 0;
}

.logo-text h2 {
  margin: 0;
  font-size: 1.6rem;
  font-weight: 900;
  color: white;
  line-height: 1.5;
  letter-spacing: 0.8px;
}

.logo-text small {
  display: block;
  font-size: 1.2rem;
  color: rgba(255, 255, 255, 0.9);
  font-weight: 500;
  margin-top: 0.8rem;
  letter-spacing: 0.3px;
}

.sidebar-toggle {
  background: rgba(255, 255, 255, 0.1);
  border: none;
  color: white;
  width: 35px;
  height: 35px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 1rem;
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
  margin-bottom: 1rem;
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
  background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
  color: white;
  padding: 0.2rem 0.6rem;
  border-radius: 12px;
  font-size: 0.7rem;
  font-weight: 600;
  min-width: 20px;
  text-align: center;
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
  padding: 1.5rem;
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
  width: 45px;
  height: 45px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  flex-shrink: 0;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.user-info {
  display: flex;
  flex-direction: column;
  flex: 1;
}

.user-name {
  font-size: 0.9rem;
  font-weight: 600;
  color: white;
  margin-bottom: 0.1rem;
}

.user-role {
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.7);
}

/* Main Content Adjustment */
.main-content {
  margin-left: 300px;
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

/* Enhanced hover effects */
.menu-item::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  height: 100%;
  width: 0;
  background: linear-gradient(90deg, rgba(255,255,255,0.1), transparent);
  transition: width 0.3s ease;
}

.menu-item:hover::before {
  width: 100%;
}

/* Status indicators */
.menu-item.active::after {
  content: '';
  position: absolute;
  right: 1rem;
  top: 50%;
  transform: translateY(-50%);
  width: 6px;
  height: 6px;
  background: #51cf66;
  border-radius: 50%;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0% { opacity: 1; }
  50% { opacity: 0.5; }
  100% { opacity: 1; }
}
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

    // Add smooth loading animation
    setTimeout(() => {
        sidebar.style.opacity = '1';
        sidebar.style.transform = 'translateX(0)';
    }, 100);
});

// Mobile menu toggle function
function toggleMobileMenu() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.mobile-overlay');
    
    sidebar.classList.toggle('mobile-open');
    overlay.classList.toggle('active');
}

// Initialize sidebar with fade-in effect
window.addEventListener('load', function() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.style.opacity = '0';
    sidebar.style.transform = 'translateX(-20px)';
    
    setTimeout(() => {
        sidebar.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        sidebar.style.opacity = '1';
        sidebar.style.transform = 'translateX(0)';
    }, 300);
});
</script>