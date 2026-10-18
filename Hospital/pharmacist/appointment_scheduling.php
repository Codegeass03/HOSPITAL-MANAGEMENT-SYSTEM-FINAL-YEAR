<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}

// Get messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : null;
unset($_SESSION['success'], $_SESSION['error']);

// Fetch all patients for dropdown
$patients_stmt = $pdo->query("SELECT id, patient_id, CONCAT(first_name, ' ', last_name) AS name FROM patients WHERE status = 'active' ORDER BY first_name");
$patients = $patients_stmt->fetchAll();

// Fetch all doctors for dropdown
$doctors_stmt = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM users WHERE role = 'Doctor' AND status = 'active' ORDER BY first_name");
$doctors = $doctors_stmt->fetchAll();

// Fetch consultation services from services table
$services_stmt = $pdo->prepare("
    SELECT id, name, price, description 
    FROM services 
    WHERE category = 'Consultation' AND is_active = 1 
    ORDER BY price ASC
");
$services_stmt->execute();
$consultation_services = $services_stmt->fetchAll();

// Fetch upcoming appointments with service details
$appointments_stmt = $pdo->query("
    SELECT 
        a.id,
        a.appointment_number,
        a.appointment_date,
        a.appointment_time,
        a.appointment_type,
        a.service_id,
        a.consultation_fee,
        a.status,
        a.reason,
        CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
        p.patient_id,
        p.phone AS patient_phone,
        CONCAT(d.first_name, ' ', d.last_name) AS doctor_name,
        s.name as service_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users d ON a.doctor_id = d.id
    LEFT JOIN services s ON a.service_id = s.id
    WHERE a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date, a.appointment_time
    LIMIT 50
");
$appointments = $appointments_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Scheduling - MediCare HMS</title>
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
        
        .page-subtitle {
            color: #64748b;
            font-size: 0.875rem;
            margin: 0.5rem 0 0 0;
        }
        
        .add-btn {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
        }
        
        .add-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-icon.scheduled { background: #dbeafe; color: #1e40af; }
        .stat-icon.confirmed { background: #d1fae5; color: #065f46; }
        .stat-icon.completed { background: #e0e7ff; color: #4338ca; }
        .stat-icon.cancelled { background: #fee2e2; color: #991b1b; }
        
        .stat-content h3 {
            margin: 0;
            font-size: 1.875rem;
            font-weight: 700;
            color: #111827;
        }
        
        .stat-content p {
            margin: 0.25rem 0 0 0;
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .appointments-section {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #111827;
            margin: 0;
        }
        
        .filter-tabs {
            display: flex;
            gap: 0.5rem;
        }
        
        .filter-tab {
            padding: 0.5rem 1rem;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .filter-tab.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f9fafb;
        }
        
        th {
            padding: 0.875rem 1rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.875rem;
            color: #374151;
        }
        
        tr:hover {
            background: #f9fafb;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
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
        
        .action-btns {
            display: flex;
            gap: 0.5rem;
        }
        
        .action-btn {
            padding: 0.375rem 0.75rem;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: #374151;
        }
        
        .action-btn:hover {
            background: #f3f4f6;
        }
        
        .action-btn.vitals {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            border-color: #8b5cf6;
        }
        
        .action-btn.vitals:hover {
            background: linear-gradient(135deg, #7c3aed, #6d28d9);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 0.75rem;
            width: 60%;
            max-width: 1800px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #111827;
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #6b7280;
            cursor: pointer;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .form-input, .form-select, .form-textarea {
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }
        
        .btn-secondary {
            padding: 0.75rem 1.5rem;
            border: 1px solid #d1d5db;
            background: white;
            color: #374151;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
        }
        
        .btn-primary {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
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
        
        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .patient-info-card {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid #8b5cf6;
        }
        
        .patient-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.5rem;
        }
        
        .patient-details {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .bmi-calculator {
            background: #f0f9ff;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-top: 1rem;
            border: 1px solid #e0f2fe;
        }
        
        .bmi-result {
            font-weight: 600;
            color: #0369a1;
            margin-top: 0.5rem;
        }
        
        .fee-display {
            padding: 0.75rem;
            background: #f0f9ff;
            border: 1px solid #e0f2fe;
            border-radius: 0.5rem;
            font-weight: 600;
            color: #0369a1;
            margin-top: 0.5rem;
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
            <div>
                <h1 class="page-title">
                    <i class="fas fa-calendar-check"></i>
                    Appointment Scheduling
                </h1>
                <p class="page-subtitle">Manage patient appointments and schedules</p>
            </div>
            <button class="add-btn" onclick="openModal()">
                <i class="fas fa-plus"></i>
                Schedule Appointment
            </button>
        </div>
        
        <div class="stats-grid">
            <?php
            $stats = [
                'scheduled' => ['icon' => 'calendar', 'label' => 'Scheduled', 'class' => 'scheduled'],
                'confirmed' => ['icon' => 'check-circle', 'label' => 'Confirmed', 'class' => 'confirmed'],
                'completed' => ['icon' => 'clipboard-check', 'label' => 'Completed', 'class' => 'completed'],
                'cancelled' => ['icon' => 'times-circle', 'label' => 'Cancelled', 'class' => 'cancelled']
            ];
            
            foreach ($stats as $status => $info) {
                $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE status = ? AND appointment_date >= CURDATE()");
                $count_stmt->execute([$status]);
                $count = $count_stmt->fetchColumn();
            ?>
            <div class="stat-card">
                <div class="stat-icon <?php echo $info['class']; ?>">
                    <i class="fas fa-<?php echo $info['icon']; ?>"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $count; ?></h3>
                    <p><?php echo $info['label']; ?> Appointments</p>
                </div>
            </div>
            <?php } ?>
        </div>
        
        <div class="appointments-section">
            <div class="section-header">
                <h2 class="section-title">Upcoming Appointments</h2>
                <div class="filter-tabs">
                    <button class="filter-tab active">All</button>
                    <button class="filter-tab">Today</button>
                    <button class="filter-tab">This Week</button>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Appointment #</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Date & Time</th>
                        <th>Type</th>
                        <th>Fee</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $apt): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($apt['appointment_number']); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($apt['patient_name']); ?></strong><br>
                            <small style="color: #6b7280;"><?php echo htmlspecialchars($apt['patient_id']); ?></small>
                        </td>
                        <td>Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?></td>
                        <td>
                            <?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?><br>
                            <small style="color: #6b7280;"><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></small>
                        </td>
                        <td>
                            <?php 
                            if (!empty($apt['service_name'])) {
                                echo htmlspecialchars($apt['service_name']);
                            } else {
                                echo ucfirst(str_replace('_', ' ', $apt['appointment_type']));
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($apt['consultation_fee'] > 0): ?>
                                Ksh <?php echo number_format($apt['consultation_fee'], 2); ?>
                            <?php else: ?>
                                <span style="color: #6b7280;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $apt['status']; ?>">
                                <?php echo ucfirst($apt['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-btns">
                                <a href="view_appointment.php?id=<?php echo $apt['id']; ?>" class="action-btn">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="edit_appointment.php?id=<?php echo $apt['id']; ?>" class="action-btn">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <button class="action-btn vitals" onclick="openVitalsModal(<?php echo $apt['id']; ?>, '<?php echo htmlspecialchars($apt['patient_name']); ?>', '<?php echo htmlspecialchars($apt['patient_id']); ?>')">
                                    <i class="fas fa-heartbeat"></i> Vitals
                                </button>
                                <?php if ($apt['status'] == 'scheduled' || $apt['status'] == 'confirmed'): ?>
                                <a href="cancel_appointment.php?id=<?php echo $apt['id']; ?>" 
                                   class="action-btn" 
                                   onclick="return confirm('Cancel this appointment?');">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Schedule Appointment Modal -->
    <div class="modal" id="scheduleModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Schedule New Appointment</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="process_schedule_appointment.php">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Patient *</label>
                            <select name="patient_id" class="form-select" required>
                                <option value="">Select Patient</option>
                                <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>">
                                    <?php echo htmlspecialchars($patient['name'] . ' (' . $patient['patient_id'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Doctor *</label>
                            <select name="doctor_id" class="form-select" required>
                                <option value="">Select Doctor</option>
                                <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>">
                                    Dr. <?php echo htmlspecialchars($doctor['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Appointment Date *</label>
                            <input type="date" name="appointment_date" class="form-input" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Appointment Time *</label>
                            <input type="time" name="appointment_time" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Appointment Type *</label>
                            <select name="appointment_type" class="form-select" required onchange="updateConsultationFee(this)">
                                <option value="">Select Consultation Type</option>
                                <?php foreach ($consultation_services as $service): ?>
                                <option value="<?php echo htmlspecialchars($service['name']); ?>" 
                                        data-fee="<?php echo $service['price']; ?>"
                                        data-service-id="<?php echo $service['id']; ?>">
                                    <?php echo htmlspecialchars($service['name']); ?> - Ksh <?php echo number_format($service['price'], 2); ?>
                                    <?php if (!empty($service['description'])): ?>
                                        (<?php echo htmlspecialchars($service['description']); ?>)
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                                <!-- Add non-consultation types -->
                                <option value="procedure" data-fee="0">Procedure (Fee varies)</option>
                                <option value="checkup" data-fee="0">Routine Check-up (Fee varies)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Duration (minutes)</label>
                            <input type="number" name="duration" class="form-input" 
                                   value="30" min="15" step="15">
                        </div>
                        
                        <!-- Hidden fields for service ID and consultation fee -->
                        <input type="hidden" name="service_id" id="serviceIdInput" value="">
                        <input type="hidden" name="consultation_fee" id="consultationFeeInput" value="0">
                        
                        <!-- Consultation fee display -->
                        <div class="form-group full-width" id="consultationFeeGroup" style="display: none;">
                            <label class="form-label">Consultation Fee</label>
                            <div class="fee-display">
                                Ksh <span id="consultationFeeDisplay">0.00</span>
                            </div>
                            <small style="color: #6b7280; margin-top: 0.25rem;">
                                This fee will be automatically added to the patient's bill.
                            </small>
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label">Reason for Visit *</label>
                            <textarea name="reason" class="form-textarea" 
                                      placeholder="Enter reason for appointment" required></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-textarea" 
                                      placeholder="Additional notes or special instructions"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Schedule Appointment</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Record Vitals Modal -->
    <div class="modal" id="vitalsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Record Patient Vitals</h2>
                <button class="modal-close" onclick="closeVitalsModal()">&times;</button>
            </div>
            <form method="POST" action="process_record_vitals.php">
                <input type="hidden" name="appointment_id" id="vitals_id">
                <div class="modal-body">
                    <div class="patient-info-card">
                        <div class="patient-name" id="vitals_patient_name"></div>
                        <div class="patient-details" id="vitals_patient_details"></div>
                    </div>
                    
                    <div class="vitals-grid">
                        <div class="form-group">
                            <label class="form-label">Blood Pressure (Systolic)</label>
                            <input type="number" name="blood_pressure_systolic" class="form-input" placeholder="e.g., 120" min="50" max="250">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Blood Pressure (Diastolic)</label>
                            <input type="number" name="blood_pressure_diastolic" class="form-input" placeholder="e.g., 80" min="30" max="150">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Heart Rate (bpm)</label>
                            <input type="number" name="heart_rate" class="form-input" placeholder="e.g., 72">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Temperature (°C)</label>
                            <input type="number" step="0.1" name="temperature" class="form-input" placeholder="e.g., 36.6">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Respiratory Rate</label>
                            <input type="number" name="respiratory_rate" class="form-input" placeholder="e.g., 16">
                        </div>
                        <div class="form-group">
                            <label class="form-label">SpO2 (%)</label>
                            <input type="number" name="spo2" class="form-input" placeholder="e.g., 98" min="0" max="100">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Weight (kg)</label>
                            <input type="number" step="0.1" name="weight" class="form-input" placeholder="e.g., 70.5" id="weight_input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Height (cm)</label>
                            <input type="number" step="0.1" name="height" class="form-input" placeholder="e.g., 175.0" id="height_input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Pain Level (0-10)</label>
                            <input type="number" name="pain_level" class="form-input" placeholder="0-10" min="0" max="10">
                        </div>
                    </div>
                    
                    <div class="bmi-calculator">
                        <div style="font-weight: 600; margin-bottom: 0.5rem;">BMI Calculator</div>
                        <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem;">
                            Enter weight and height to calculate BMI
                        </div>
                        <div class="bmi-result" id="bmi_result">BMI: --</div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label">Additional Notes</label>
                        <textarea name="notes" class="form-textarea" placeholder="Any additional observations or notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeVitalsModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Save Vitals</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openModal() {
            document.getElementById('scheduleModal').classList.add('show');
        }
        
        function closeModal() {
            document.getElementById('scheduleModal').classList.remove('show');
        }
        
        function openVitalsModal(appointmentId, patientName, patientId) {
            document.getElementById('vitals_id').value = appointmentId;
            document.getElementById('vitals_patient_name').textContent = patientName;
            document.getElementById('vitals_patient_details').textContent = `Patient ID: ${patientId}`;
            document.getElementById('vitalsModal').classList.add('show');
        }
        
        function closeVitalsModal() {
            document.getElementById('vitalsModal').classList.remove('show');
        }
        
        function updateConsultationFee(select) {
            const feeGroup = document.getElementById('consultationFeeGroup');
            const feeDisplay = document.getElementById('consultationFeeDisplay');
            const feeInput = document.getElementById('consultationFeeInput');
            const serviceIdInput = document.getElementById('serviceIdInput');
            
            if (select.value) {
                const selectedOption = select.options[select.selectedIndex];
                const fee = selectedOption.getAttribute('data-fee');
                const serviceId = selectedOption.getAttribute('data-service-id');
                
                feeDisplay.textContent = parseFloat(fee).toFixed(2);
                feeInput.value = fee;
                serviceIdInput.value = serviceId || '';
                feeGroup.style.display = 'block';
            } else {
                feeGroup.style.display = 'none';
                feeInput.value = '0';
                serviceIdInput.value = '';
            }
        }
        
        // Close modal when clicking outside
        document.getElementById('scheduleModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        document.getElementById('vitalsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeVitalsModal();
            }
        });
        
        // BMI Calculator
        function calculateBMI() {
            const weight = parseFloat(document.getElementById('weight_input').value);
            const height = parseFloat(document.getElementById('height_input').value) / 100; // convert to meters
            
            if (weight && height) {
                const bmi = weight / (height * height);
                let category = '';
                
                if (bmi < 18.5) category = 'Underweight';
                else if (bmi < 25) category = 'Normal weight';
                else if (bmi < 30) category = 'Overweight';
                else category = 'Obese';
                
                document.getElementById('bmi_result').textContent = `BMI: ${bmi.toFixed(1)} (${category})`;
            } else {
                document.getElementById('bmi_result').textContent = 'BMI: --';
            }
        }
        
        document.getElementById('weight_input').addEventListener('input', calculateBMI);
        document.getElementById('height_input').addEventListener('input', calculateBMI);
    </script>
</body>
</html>
<?php include "includes/footer.php"; ?>