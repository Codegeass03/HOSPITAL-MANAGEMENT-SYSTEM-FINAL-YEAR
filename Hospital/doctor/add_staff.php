<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Staff - MediCare HMS</title>
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
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
            min-height: 100vh;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            text-decoration: none;
            font-size: 0.875rem;
            margin-bottom: 1rem;
            transition: color 0.2s;
        }

        .back-button:hover {
            color: #0f172a;
        }

        .page-header h1 {
            font-size: 1.875rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .page-header p {
            color: #64748b;
            font-size: 0.875rem;
        }

        /* Form Container */
        .form-container {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            max-width: 1200px;
        }

        /* Tab Navigation */
        .tab-navigation {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
            padding: 0 1.5rem;
            gap: 2rem;
        }

        .tab-button {
            padding: 1rem 0;
            background: none;
            border: none;
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            position: relative;
            transition: color 0.2s;
        }

        .tab-button:hover {
            color: #0f172a;
        }

        .tab-button.active {
            color: #3b82f6;
        }

        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 2px;
            background: #3b82f6;
        }

        .tab-button.completed::before {
            content: '✓';
            display: inline-block;
            margin-right: 0.5rem;
            color: #10b981;
            font-weight: 600;
        }

        /* Tab Content */
        .tab-content {
            display: none;
            padding: 2rem 1.5rem;
        }

        .tab-content.active {
            display: block;
        }

        .tab-section-header {
            margin-bottom: 1.5rem;
        }

        .tab-section-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .tab-section-header p {
            color: #64748b;
            font-size: 0.875rem;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
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
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .required {
            color: #ef4444;
        }

        .form-input, .form-select, .form-textarea {
            padding: 0.625rem 0.875rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.2s;
            font-family: inherit;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* Role Selection Cards */
        .role-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .role-card {
            border: 2px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }

        .role-card:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
        }

        .role-card input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .role-card input[type="radio"]:checked + .role-content {
            border-color: #3b82f6;
        }

        .role-card.selected {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        .role-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }

        .role-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
        }

        .role-card.selected .role-icon {
            background: #3b82f6;
            color: white;
        }

        .role-title {
            font-weight: 600;
            color: #0f172a;
        }

        .role-description {
            font-size: 0.75rem;
            color: #64748b;
            line-height: 1.4;
        }

        /* Dynamic Field Section */
        .dynamic-section {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .dynamic-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .dynamic-section-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #0f172a;
        }

        .add-more-btn {
            padding: 0.5rem 1rem;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            color: #3b82f6;
            font-size: 0.875rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .add-more-btn:hover {
            background: #f8fafc;
            border-color: #3b82f6;
        }

        /* Navigation Buttons */
        .form-navigation {
            display: flex;
            justify-content: space-between;
            padding: 1.5rem;
            border-top: 1px solid #e2e8f0;
        }

        .btn {
            padding: 0.625rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
        }

        .btn-secondary {
            background: white;
            border: 1px solid #e2e8f0;
            color: #64748b;
        }

        .btn-secondary:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-grid, .role-grid {
                grid-template-columns: 1fr;
            }

            .main-content {
                margin-left: 0;
            }

            .tab-navigation {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>

<div class="main-content" style="padding-left: 150px;">
    <div class="page-header">
        <a href="manage_users.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Back to Staff Management
        </a>
        <h1>Add New Staff</h1>
        <p>Create a new staff member profile</p>
    </div>

    <form id="staffForm" action="process_multi_step_staff.php" method="POST">
        <div class="form-container">
            <!-- Tab Navigation -->
            <div class="tab-navigation">
                <button type="button" class="tab-button active" data-tab="personal">
                    Personal Info
                </button>
                <button type="button" class="tab-button" data-tab="professional">
                    Professional
                </button>
                <button type="button" class="tab-button" data-tab="employment">
                    Employment
                </button>
                <button type="button" class="tab-button" data-tab="access">
                    Access & Roles
                </button>
            </div>

            <!-- Personal Info Tab -->
            <div class="tab-content active" id="personal-tab">
                <div class="tab-section-header">
                    <h2>Personal Information</h2>
                    <p>Enter the staff member's basic personal details</p>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Date of Birth <span class="required">*</span></label>
                        <input type="date" name="date_of_birth" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Gender <span class="required">*</span></label>
                        <select name="gender" class="form-select" required>
                            <option value="">Select gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone Number <span class="required">*</span></label>
                        <input type="tel" name="phone" class="form-input" placeholder="+254..." required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address <span class="required">*</span></label>
                        <input type="email" name="email" class="form-input" required>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-textarea" placeholder="Street address"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">National ID/Passport</label>
                        <input type="text" name="id_number" class="form-input">
                    </div>
                </div>
            </div>

            <!-- Professional Info Tab -->
            <div class="tab-content" id="professional-tab">
                <div class="tab-section-header">
                    <h2>Professional Information</h2>
                    <p>Enter the staff member's professional qualifications and credentials</p>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user-md"></i>
                            Profession
                        </label>
                        <input type="text" name="profession" class="form-input" placeholder="e.g. Doctor, Nurse, etc.">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-stethoscope"></i>
                            Specialization
                        </label>
                        <input type="text" name="specialization" class="form-input" placeholder="e.g. Cardiology, Pediatrics, etc.">
                    </div>
                </div>

                <!-- Qualifications Section -->
                <div class="dynamic-section">
                    <div class="dynamic-section-header">
                        <h3>Qualifications & Degrees</h3>
                        <button type="button" class="add-more-btn" onclick="addQualification()">
                            <i class="fas fa-plus"></i>
                            Add More
                        </button>
                    </div>

                    <div id="qualifications-container">
                        <div class="form-grid qualification-entry">
                            <div class="form-group">
                                <label class="form-label">Degree/Certification</label>
                                <input type="text" name="degree[]" class="form-input" placeholder="e.g. MD, RN, etc.">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Institution</label>
                                <input type="text" name="institution[]" class="form-input" placeholder="University/College name">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Year Completed</label>
                                <input type="text" name="year_completed[]" class="form-input" placeholder="YYYY">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Licenses Section -->
                <div class="dynamic-section">
                    <div class="dynamic-section-header">
                        <h3>Licenses & Certifications</h3>
                        <button type="button" class="add-more-btn" onclick="addLicense()">
                            <i class="fas fa-plus"></i>
                            Add More
                        </button>
                    </div>

                    <div id="licenses-container">
                        <div class="form-grid license-entry">
                            <div class="form-group">
                                <label class="form-label">License Type</label>
                                <input type="text" name="license_type[]" class="form-input" placeholder="e.g. Medical License">
                            </div>

                            <div class="form-group">
                                <label class="form-label">License Number</label>
                                <input type="text" name="license_number[]" class="form-input" placeholder="Enter license number">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Issue Date</label>
                                <input type="date" name="license_issue_date[]" class="form-input">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Expiry Date</label>
                                <input type="date" name="license_expiry_date[]" class="form-input">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employment Tab -->
            <div class="tab-content" id="employment-tab">
                <div class="tab-section-header">
                    <h2>Employment Details</h2>
                    <p>Enter employment and contract information</p>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Employee Number</label>
                        <input type="text" name="employee_number" class="form-input" placeholder="Auto-generated if empty">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Department <span class="required">*</span></label>
                        <select name="department" class="form-select" required>
                            <option value="">Select department</option>
                            <option value="cardiology">General Care</option>
                            <option value="cardiology">Cardiology</option>
                            <option value="neurology">Neurology</option>
                            <option value="emergency">Emergency</option>
                            <option value="orthopedics">Orthopedics</option>
                              <option value="orthopedics">Endocrinology</option>
                            <option value="pediatrics">Pediatrics</option>
                            <option value="pharmacy">Pharmacy</option>
                            <option value="laboratory">Laboratory</option>
                            <option value="finance">Finance</option>
                            <option value="operations">Operations</option>
                            <option value="hr">Human Resources</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Job Title <span class="required">*</span></label>
                        <input type="text" name="job_title" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Hire Date <span class="required">*</span></label>
                        <input type="date" name="hire_date" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Employment Type</label>
                        <select name="employment_type" class="form-select">
                            <option value="full-time">Full-time</option>
                            <option value="part-time">Part-time</option>
                            <option value="contract">Contract</option>
                            <option value="temporary">Temporary</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Salary</label>
                        <input type="number" name="salary" class="form-input" step="0.01" placeholder="0.00">
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Work Schedule</label>
                        <textarea name="work_schedule" class="form-textarea" placeholder="e.g., Monday-Friday, 8:00 AM - 5:00 PM"></textarea>
                    </div>
                </div>
            </div>

            <!-- Access & Roles Tab -->
            <div class="tab-content" id="access-tab">
                <div class="tab-section-header">
                    <h2>Access & Roles</h2>
                    <p>Set up system access and role permissions</p>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Username <span class="required">*</span></label>
                        <input type="text" name="username" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email for Access <span class="required">*</span></label>
                        <input type="email" name="access_email" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Temporary Password <span class="required">*</span></label>
                        <input type="password" name="password" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm Password <span class="required">*</span></label>
                        <input type="password" name="confirm_password" class="form-input" required>
                    </div>
                </div>

                <!-- System Role Selection -->
                <div style="margin-top: 2rem;">
                    <label class="form-label" style="margin-bottom: 1rem; display: block;">
                        System Role <span class="required">*</span>
                    </label>
                    <div class="role-grid">
                        <div class="role-card" onclick="selectRole(this, 'Super Admin')">
                            <input type="radio" name="role" value="Super Admin" required>
                            <div class="role-content">
                                <div class="role-header">
                                    <div class="role-icon">
                                        <i class="fas fa-crown"></i>
                                    </div>
                                    <div class="role-title">Administrator</div>
                                </div>
                                <div class="role-description">
                                    Full access to all system features and settings
                                </div>
                            </div>
                        </div>

                        <div class="role-card" onclick="selectRole(this, 'Admin')">
                            <input type="radio" name="role" value="Admin" required>
                            <div class="role-content">
                                <div class="role-header">
                                    <div class="role-icon">
                                        <i class="fas fa-user-shield"></i>
                                    </div>
                                    <div class="role-title">Manager</div>
                                </div>
                                <div class="role-description">
                                    Access to manage staff, schedules, and reports
                                </div>
                            </div>
                        </div>

                        <div class="role-card" onclick="selectRole(this, 'Doctor')">
                            <input type="radio" name="role" value="Doctor" required>
                            <div class="role-content">
                                <div class="role-header">
                                    <div class="role-icon">
                                        <i class="fas fa-user-md"></i>
                                    </div>
                                    <div class="role-title">Doctor</div>
                                </div>
                                <div class="role-description">
                                    Access to patient records, appointments, and prescriptions
                                </div>
                            </div>
                        </div>

                        <div class="role-card" onclick="selectRole(this, 'Nurse')">
                            <input type="radio" name="role" value="Nurse" required>
                            <div class="role-content">
                                <div class="role-header">
                                    <div class="role-icon">
                                        <i class="fas fa-user-nurse"></i>
                                    </div>
                                    <div class="role-title">Nurse</div>
                                </div>
                                <div class="role-description">
                                    Access to patient care, vitals, and treatment plans
                                </div>
                            </div>
                        </div>

                        <div class="role-card" onclick="selectRole(this, 'Receptionist')">
                            <input type="radio" name="role" value="Receptionist" required>
                            <div class="role-content">
                                <div class="role-header">
                                    <div class="role-icon">
                                        <i class="fas fa-desktop"></i>
                                    </div>
                                    <div class="role-title">Receptionist</div>
                                </div>
                                <div class="role-description">
                                    Access to appointments, patient registration, and billing
                                </div>
                            </div>
                        </div>

                        <div class="role-card" onclick="selectRole(this, 'Pharmacist')">
                            <input type="radio" name="role" value="Pharmacist" required>
                            <div class="role-content">
                                <div class="role-header">
                                    <div class="role-icon">
                                        <i class="fas fa-pills"></i>
                                    </div>
                                    <div class="role-title">Staff (Pharmacists, Technicians, Accountants etc..)</div>
                                </div>
                                <div class="role-description">
                                    Basic access to assigned modules only
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Navigation -->
            <div class="form-navigation">
                <button type="button" class="btn btn-secondary" id="prevBtn" onclick="changeTab(-1)">
                    <i class="fas fa-arrow-left"></i>
                    Previous
                </button>
                <div style="display: flex; gap: 0.75rem;">
                    <a href="manage_users.php" class="btn btn-secondary">
                        Cancel
                    </a>
                    <button type="button" class="btn btn-primary" id="nextBtn" onclick="changeTab(1)">
                        Next
                        <i class="fas fa-arrow-right"></i>
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn" style="display: none;">
                        <i class="fas fa-check"></i>
                        Create Staff Member
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let currentTab = 0;
const tabs = ['personal', 'professional', 'employment', 'access'];
const completedTabs = new Set();

// Initialize
showTab(currentTab);

function showTab(n) {
    const tabContents = document.querySelectorAll('.tab-content');
    const tabButtons = document.querySelectorAll('.tab-button');
    
    // Hide all tabs
    tabContents.forEach(tab => tab.classList.remove('active'));
    tabButtons.forEach(btn => btn.classList.remove('active'));
    
    // Show current tab
    document.getElementById(tabs[n] + '-tab').classList.add('active');
    tabButtons[n].classList.add('active');
    
    // Update navigation buttons
    document.getElementById('prevBtn').style.display = n === 0 ? 'none' : 'inline-flex';
    document.getElementById('nextBtn').style.display = n === tabs.length - 1 ? 'none' : 'inline-flex';
    document.getElementById('submitBtn').style.display = n === tabs.length - 1 ? 'inline-flex' : 'none';
    
    currentTab = n;
}

function changeTab(direction) {
    const newTab = currentTab + direction;
    
    if (newTab < 0 || newTab >= tabs.length) return;
    
    // Mark current tab as completed when moving forward
    if (direction > 0) {
        const tabButtons = document.querySelectorAll('.tab-button');
        if (!completedTabs.has(currentTab)) {
            completedTabs.add(currentTab);
            tabButtons[currentTab].classList.add('completed');
        }
    }
    
    showTab(newTab);
}

// Allow clicking on tabs
document.querySelectorAll('.tab-button').forEach((button, index) => {
    button.addEventListener('click', () => {
        showTab(index);
    });
});

// Role selection
function selectRole(card, role) {
    document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    card.querySelector('input[type="radio"]').checked = true;
}

// Add qualification entry
function addQualification() {
    const container = document.getElementById('qualifications-container');
    const newEntry = document.createElement('div');
    newEntry.className = 'form-grid qualification-entry';
    newEntry.style.marginTop = '1rem';
    newEntry.innerHTML = `
        <div class="form-group">
            <label class="form-label">Degree/Certification</label>
            <input type="text" name="degree[]" class="form-input" placeholder="e.g. MD, RN, etc.">
        </div>
        <div class="form-group">
            <label class="form-label">Institution</label>
            <input type="text" name="institution[]" class="form-input" placeholder="University/College name">
        </div>
        <div class="form-group">
            <label class="form-label">Year Completed</label>
            <input type="text" name="year_completed[]" class="form-input" placeholder="YYYY">
        </div>
    `;
    container.appendChild(newEntry);
}

// Add license entry
function addLicense() {
    const container = document.getElementById('licenses-container');
    const newEntry = document.createElement('div');
    newEntry.className = 'form-grid license-entry';
    newEntry.style.marginTop = '1rem';
    newEntry.innerHTML = `
        <div class="form-group">
            <label class="form-label">License Type</label>
            <input type="text" name="license_type[]" class="form-input" placeholder="e.g. Medical License">
        </div>
        <div class="form-group">
            <label class="form-label">License Number</label>
            <input type="text" name="license_number[]" class="form-input" placeholder="Enter license number">
        </div>
        <div class="form-group">
            <label class="form-label">Issue Date</label>
            <input type="date" name="license_issue_date[]" class="form-input">
        </div>
        <div class="form-group">
            <label class="form-label">Expiry Date</label>
            <input type="date" name="license_expiry_date[]" class="form-input">
        </div>
    `;
    container.appendChild(newEntry);
}

// Form validation before submission
document.getElementById('staffForm').addEventListener('submit', function(e) {
    const password = document.querySelector('input[name="password"]').value;
    const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
    }
});
</script>

</body>
</html>
<?php include "includes/footer.php"; ?>