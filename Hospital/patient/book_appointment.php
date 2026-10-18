<?php
session_start();
include "includes/db_connect.php";


// Check if user is logged in and is a patient
if (!isset($_SESSION['patient_id']) || $_SESSION['role'] !== 'Patient') {
    header("Location: login.php");
    exit;
}

// Get patient information
$patient_stmt = $pdo->prepare("
    SELECT p.*, u.email 
    FROM patients p
    JOIN users u ON u.id = ?
    WHERE p.patient_id = (SELECT patient_id FROM patients WHERE id = ?)
    LIMIT 1
");
$patient_stmt->execute([$_SESSION['user_id'], $_SESSION['patient_id']]);
$patient = $patient_stmt->fetch();

if (!$patient) {
    $_SESSION['error'] = "Patient record not found.";
    header("Location: dashboard.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $doctor_id = !empty($_POST['doctor_id']) ? $_POST['doctor_id'] : null;
    $preferred_date = $_POST['preferred_date'];
    $preferred_time = $_POST['preferred_time'];
    $appointment_type = $_POST['appointment_type'];
    $urgency = $_POST['urgency'];
    $notes = $_POST['notes'];
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO appointment_requests 
            (patient_id, doctor_id, preferred_date, preferred_time, type, urgency, notes, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->execute([
            $patient['id'],
            $doctor_id,
            $preferred_date,
            $preferred_time,
            $appointment_type,
            $urgency,
            $notes
        ]);
        
        $_SESSION['success'] = "Your appointment request has been submitted successfully. We will review it and get back to you soon.";
        header("Location: my_appointments.php");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error submitting request: " . $e->getMessage();
    }
}

// Get available doctors
$doctors_stmt = $pdo->query("
    SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as name, 
           dp.specialty, sp.department
    FROM users u 
    LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
    LEFT JOIN staff_profiles sp ON u.id = sp.user_id
    WHERE u.role = 'Doctor' AND u.status = 'active'
    ORDER BY u.first_name
");
$doctors = $doctors_stmt->fetchAll();

// Get patient's previous requests
$requests_stmt = $pdo->prepare("
    SELECT ar.*, 
           CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
           CONCAT(p.first_name, ' ', p.last_name) as processed_by_name
    FROM appointment_requests ar
    LEFT JOIN users d ON ar.doctor_id = d.id
    LEFT JOIN users p ON ar.processed_by = p.id
    WHERE ar.patient_id = ?
    ORDER BY ar.created_at DESC
    LIMIT 10
");
$requests_stmt->execute([$patient['id']]);
$previous_requests = $requests_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - MediCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.875rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #64748b;
            font-size: 0.875rem;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert i {
            font-size: 1.25rem;
        }

        /* Content Cards */
        .content-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .card-header p {
            color: #64748b;
            font-size: 0.875rem;
        }

        .card-content {
            padding: 1.5rem;
        }

        @media (max-width: 640px) {
            .card-header, .card-content {
                padding: 1rem;
            }
        }

        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
            font-size: 0.875rem;
        }

        .form-label.required::after {
            content: " *";
            color: #ef4444;
        }

        .form-control {
            width: 100%;
            padding: 0.625rem 0.875rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        select.form-control {
            cursor: pointer;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .form-help {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        /* Info Box */
        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 0.75rem;
        }

        .info-box i {
            color: #3b82f6;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .info-box-content h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 0.25rem;
        }

        .info-box-content p {
            font-size: 0.813rem;
            color: #1e3a8a;
            line-height: 1.5;
        }

        /* Buttons */
        .btn {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            text-decoration: none;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #d1d5db;
            color: #374151;
        }

        .btn-outline:hover {
            background: #f9fafb;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
        }

        @media (max-width: 640px) {
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Request History */
        .request-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .request-item {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1rem;
            transition: all 0.2s;
        }

        .request-item:hover {
            border-color: #cbd5e1;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .request-date {
            font-size: 0.875rem;
            color: #64748b;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .status-approved {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .request-details {
            font-size: 0.875rem;
            color: #374151;
            line-height: 1.6;
        }

        .request-details strong {
            color: #0f172a;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
        }

        .empty-state h3 {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #64748b;
        }

        .empty-state p {
            font-size: 0.875rem;
        }

        /* Mobile Menu */
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: white;
            border: none;
            border-radius: 0.375rem;
            padding: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }

        @media (max-width: 1024px) {
            .mobile-menu-btn {
                display: block;
            }
        }
    </style>
</head>
<body>

<!-- Mobile Menu Button -->
<button class="mobile-menu-btn" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<div class="main-content">
    <div class="page-header">
        <h1>Book Appointment</h1>
        <p>Request an appointment with our healthcare professionals.</p>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Booking Form -->
    <div class="content-card">
        <div class="card-header">
            <h2>Appointment Request Form</h2>
            <p>Fill in the details below to request an appointment.</p>
        </div>
        <div class="card-content">
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <div class="info-box-content">
                    <h4>Important Information</h4>
                    <p>Your appointment request will be reviewed by our staff. You will receive a confirmation once your request has been approved and an appointment time has been assigned. Please provide accurate information to help us serve you better.</p>
                </div>
            </div>

            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Patient Name</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Patient ID</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($patient['patient_id']); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($patient['phone']); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($patient['email'] ?? 'N/A'); ?>" readonly>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Preferred Doctor</label>
                        <select class="form-control" name="doctor_id">
                            <option value="">Any Available Doctor</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>">
                                    Dr. <?php echo htmlspecialchars($doctor['name']); ?>
                                    <?php if ($doctor['specialty']): ?>
                                        - <?php echo htmlspecialchars($doctor['specialty']); ?>
                                    <?php elseif ($doctor['department']): ?>
                                        - <?php echo htmlspecialchars($doctor['department']); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-help">Leave blank if you don't have a preference</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Preferred Date</label>
                        <input type="date" class="form-control" name="preferred_date" 
                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                        <small class="form-help">Select your preferred appointment date</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Preferred Time</label>
                        <select class="form-control" name="preferred_time" required>
                            <option value="">Select time period</option>
                            <option value="Morning">Morning (8:00 AM - 12:00 PM)</option>
                            <option value="Afternoon">Afternoon (12:00 PM - 5:00 PM)</option>
                            <option value="Evening">Evening (5:00 PM - 8:00 PM)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Appointment Type</label>
                        <select class="form-control" name="appointment_type" required>
                            <option value="">Select type</option>
                            <option value="consultation">General Consultation</option>
                            <option value="followup">Follow-up Visit</option>
                            <option value="checkup">Regular Check-up</option>
                            <option value="procedure">Procedure/Treatment</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Urgency Level</label>
                        <select class="form-control" name="urgency" required>
                            <option value="Normal">Normal</option>
                            <option value="Medium">Medium</option>
                            <option value="High">High (Urgent)</option>
                        </select>
                        <small class="form-help">Select "High" for urgent medical concerns</small>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label class="form-label required">Reason for Appointment</label>
                    <textarea class="form-control" name="notes" placeholder="Please describe your symptoms, concerns, or reason for the appointment..." required></textarea>
                    <small class="form-help">Provide as much detail as possible to help us prepare for your visit</small>
                </div>

                <div class="form-actions">
                    <a href="dashboard.php" class="btn btn-outline">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                    <button type="submit" name="submit_request" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Request History -->
    <div class="content-card">
        <div class="card-header">
            <h2>Recent Appointment Requests</h2>
            <p>View your previous appointment requests and their status.</p>
        </div>
        <div class="card-content">
            <?php if (empty($previous_requests)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Previous Requests</h3>
                    <p>You haven't submitted any appointment requests yet.</p>
                </div>
            <?php else: ?>
                <div class="request-list">
                    <?php foreach ($previous_requests as $request): ?>
                        <div class="request-item">
                            <div class="request-header">
                                <span class="request-date">
                                    <i class="fas fa-calendar"></i>
                                    Requested on <?php echo date('M d, Y g:i A', strtotime($request['created_at'])); ?>
                                </span>
                                <span class="status-badge status-<?php echo $request['status']; ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                            </div>
                            <div class="request-details">
                                <p>
                                    <strong>Preferred Date:</strong> <?php echo date('F d, Y', strtotime($request['preferred_date'])); ?> 
                                    (<?php echo $request['preferred_time']; ?>)
                                </p>
                                <p>
                                    <strong>Type:</strong> <?php echo ucfirst($request['type']); ?> | 
                                    <strong>Urgency:</strong> <?php echo $request['urgency']; ?>
                                </p>
                                <?php if ($request['doctor_name']): ?>
                                    <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($request['doctor_name']); ?></p>
                                <?php endif; ?>
                                <?php if ($request['notes']): ?>
                                    <p><strong>Notes:</strong> <?php echo htmlspecialchars($request['notes']); ?></p>
                                <?php endif; ?>
                                <?php if ($request['status'] === 'rejected' && $request['rejection_reason']): ?>
                                    <p style="color: #ef4444;">
                                        <strong>Rejection Reason:</strong> <?php echo htmlspecialchars($request['rejection_reason']); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($request['status'] === 'approved'): ?>
                                    <p style="color: #059669;">
                                        <strong>Status:</strong> Approved by <?php echo htmlspecialchars($request['processed_by_name'] ?? 'Staff'); ?> 
                                        on <?php echo date('M d, Y', strtotime($request['processed_at'])); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Set minimum date to tomorrow
    document.addEventListener('DOMContentLoaded', function() {
        const dateInput = document.querySelector('input[name="preferred_date"]');
        if (dateInput) {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            dateInput.min = tomorrow.toISOString().split('T')[0];
        }
    });

    // Mobile sidebar toggle
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        
        if (sidebar.style.display === 'none' || !sidebar.style.display) {
            sidebar.style.display = 'block';
            mainContent.style.marginLeft = '280px';
        } else {
            sidebar.style.display = 'none';
            mainContent.style.marginLeft = '0';
        }
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        
        if (window.innerWidth <= 1024) {
            sidebar.style.display = 'none';
            mainContent.style.marginLeft = '0';
        } else {
            sidebar.style.display = 'block';
            mainContent.style.marginLeft = '280px';
        }
    });
    
    // Initialize on load
    document.addEventListener('DOMContentLoaded', function() {
        if (window.innerWidth <= 1024) {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                sidebar.style.display = 'none';
            }
        }
    });
</script>

</body>
</html>

<aside class="sidebar">
  <div class="sidebar-header">
    <div class="logo">
      <div class="logo-icon">
        <i class="fas fa-heartbeat"></i>
      </div>
      <div class="logo-text">
        <h2>AuraCare HMS</h2>
      
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
          <a href="patient_profile.php" class="menu-item">
            <i class="fas fa-user-injured"></i>
            <span>User Profile</span>
          </a>
        </li>
        
         <li>
          <a href="patient_medical_records.php" class="menu-item">
            <i class="fas fa-file-medical"></i>
            <span>Electronic Medical Records</span>
          </a>
        </li>

        <li class="has-submenu">
          <a href="#" class="menu-item submenu-toggle">
            <i class="fas fa-calendar-check"></i>
            <span>Appointments</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
          </a>
          <ul class="submenu">
            <li><a href="book_appointment.php"><i class="fas fa-plus-circle"></i>Schedule Appointment</a></li>
            <li><a href="my_appointments.php"><i class="fas fa-calendar"></i>Appointment Status</a></li>
            <li><a href="booking_history.php"><i class="fas fa-calendar-week"></i>Appointment History</a></li>
          </ul>
        </li>

    

    <div class="nav-section">
      <div class="section-label">Operations</div>
      <ul class="sidebar-menu">

        <li>
          <a href="lab_results.php" class="menu-item">
            <i class="fas fa-chart-line"></i>
            <span>Reports</span>
          </a>
        </li>
        
       
      </ul>
    </div>

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
        <span class="user-name">Patient Dashboard</span>
       
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