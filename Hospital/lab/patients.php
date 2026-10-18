<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";
// Note: The original PHP was for 'staff' and had 'professional', 'employment', 'access' tabs.
// This redesign renames them to 'medical', 'insurance', 'consent' for a 'patient' form.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Patient - AuraCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* BASE STYLES from original file (kept for context) */
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

        /* --- Header Styling (matches screenshots) --- */
        .page-header {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            color: #64748b;
            text-decoration: none;
            font-size: 1.5rem; /* Larger icon */
            margin-right: 1.5rem;
            transition: color 0.2s;
        }

        .header-text {
            /* Container for H1 and P */
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
        
        /* Form Container - remove shadow/border to blend with the tab area */
        .form-container {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            max-width: 1200px;
            /* Changed to remove padding so content can fill the width */
        }

        /* --- Tab Navigation (Matches screenshots) --- */
        .tab-navigation {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
            padding: 0 1.5rem;
            gap: 2rem;
        }

        .tab-button {
            padding: 0.75rem 0; /* Adjusted padding */
            background: none;
            border: none;
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            position: relative;
            transition: color 0.2s;
        }

        .tab-button.active {
            color: #0f172a; /* Darker color for active tab text */
            font-weight: 600;
        }

        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 2px;
            background: #3b82f6; /* Blue indicator line */
        }

        /* --- Tab Content Styling (Matches screenshots' padding) --- */
        .tab-content {
            display: none;
            padding: 2rem 1.5rem; 
        }

        .tab-content.active {
            display: block;
        }

        /* --- Section Header Styling (Matches screenshots) --- */
        .tab-section-header {
            margin-top: 2rem; /* Separator between main sections */
            margin-bottom: 1rem;
        }

        .tab-section-header:first-of-type {
             margin-top: 0; /* No top margin for the first header */
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

        /* --- Form Grid (Adapted from original to match screenshots) --- */
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        /* 3-column layout for names, D.O.B, gender etc. */
        .form-grid-3-col {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* 2-column layout for contact/emergency info */
        .form-grid-2-col {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Standard form elements */
        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .form-input, .form-select, .form-textarea {
            padding: 0.75rem 0.875rem; /* Slightly more vertical padding */
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 0.9rem; /* Slightly larger font */
            color: #1f2937;
            transition: all 0.2s;
            font-family: inherit;
        }

        .form-textarea {
            resize: vertical;
            min-height: 50px; /* Shorter min-height for address field */
        }

        /* --- Preferred Contact Method (Matches screenshot radio button style) --- */
        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-top: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .radio-label {
            font-size: 0.9rem;
            font-weight: 400;
            color: #1f2937;
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .radio-label input[type="radio"] {
            margin-right: 0.5rem;
            accent-color: #3b82f6; /* Blue check mark/dot */
        }

        /* Payment Method Cards */
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .payment-method-card {
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
        }

        .payment-method-card:hover {
            border-color: #3b82f6;
        }

        .payment-method-card.selected {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }

        .payment-method-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }

        .payment-method-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
        }

        .insurance-icon { background-color: #10b981; }
        .mpesa-icon { background-color: #06c755; }
        .cash-icon { background-color: #6b7280; }
        .card-icon { background-color: #3b82f6; }

        .payment-method-title {
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }

        .payment-method-description {
            font-size: 0.875rem;
            color: #64748b;
            margin: 0;
        }

        /* Insurance Details - Hidden by default */
        .insurance-details {
            display: none;
            background: #f8fafc;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-top: 1rem;
            border: 1px solid #e2e8f0;
        }

        .insurance-details.active {
            display: block;
        }

        /* M-Pesa Details */
        .mpesa-details {
            display: none;
            background: #f0fdf4;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-top: 1rem;
            border: 1px solid #dcfce7;
        }

        .mpesa-details.active {
            display: block;
        }

        /* Card Details */
        .card-details {
            display: none;
            background: #f0f9ff;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-top: 1rem;
            border: 1px solid #e0f2fe;
        }

        .card-details.active {
            display: block;
        }

        /* Card Input with Icons */
        .card-input-container {
            position: relative;
        }

        .card-icon-input {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
        }

        /* Cash Details */
        .cash-details {
            display: none;
            background: #f8fafc;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-top: 1rem;
            border: 1px solid #e2e8f0;
            text-align: center;
        }

        .cash-details.active {
            display: block;
        }

        .cash-icon-large {
            font-size: 3rem;
            color: #6b7280;
            margin-bottom: 1rem;
        }

        /* --- Profile Photo Styling (Matches screenshot) --- */
        .profile-photo-upload {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            padding-top: 1rem;
        }

        .upload-area {
            display: flex;
            align-items: center;
            gap: 1rem;
            border: 1px dashed #e2e8f0;
            padding: 1rem;
            border-radius: 0.5rem;
        }

        .upload-icon {
            font-size: 2rem;
            color: #3b82f6;
        }

        .upload-text button {
            background: none;
            border: 1px solid #3b82f6;
            color: #3b82f6;
            padding: 0.4rem 1rem;
            border-radius: 0.375rem;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
        }

        .upload-text button:hover {
            background: #eff6ff;
        }

        .upload-info {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        /* --- Sticky Form Navigation Footer (Matches screenshot) --- */
        .form-navigation {
            position: sticky;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #e2e8f0;
            padding: 1rem 2rem;
            display: flex;
            justify-content: flex-end; /* Align buttons to the right */
            gap: 0.75rem;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
            margin-left: -250px; /* Counteract main-content margin */
            width: calc(100% + 250px);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-secondary {
            background: white;
            border: 1px solid #e2e8f0;
            color: #64748b;
        }

        .btn-primary {
            background-color: #0f172a; /* Darker primary button color from screenshots */
            color: white;
        }

        .btn-primary:hover {
            background-color: #1e293b;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1024px) {
            .form-grid-3-col {
                grid-template-columns: repeat(2, 1fr);
            }
            .payment-methods {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding-bottom: 6rem; /* Space for fixed footer */
            }
            .form-grid-3-col, .form-grid-2-col {
                grid-template-columns: 1fr;
            }
            .payment-methods {
                grid-template-columns: 1fr;
            }
            .form-navigation {
                width: 100%;
                margin-left: 0;
                padding: 1rem;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>

<div class="main-content" style="padding-left: 250px; padding-right: 150px;">
    <div class="page-header">
        <a href="#" onclick="history.back(); return false;" class="back-button">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="header-text">
            <h1>Add Patient</h1>
            <p>Register a new patient to the system.</p>
        </div>
    </div>

    <form id="patientForm" action="process_new_patient.php" method="POST" enctype="multipart/form-data">
        <div class="form-container">
            <div class="tab-navigation">
                <button type="button" class="tab-button active" data-tab="personal">
                    Personal Information
                </button>
                <button type="button" class="tab-button" data-tab="medical">
                    Medical Information
                </button>
                <button type="button" class="tab-button" data-tab="insurance">
                    Insurance & Billing
                </button>
            </div>

            <!-- Personal Information Tab -->
            <div class="tab-content active" id="personal-tab">
                <!-- ... (personal information content remains the same) ... -->
                <div class="tab-section-header">
                    <h2>Personal Information</h2>
                    <p>Enter the patient's personal details.</p>
                </div>

                <div class="form-grid-3-col">
                    <div class="form-group">
                        <label class="form-label">First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" class="form-input" placeholder="Enter first name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Middle Name (Optional)</label>
                        <input type="text" name="middle_name" class="form-input" placeholder="Enter middle name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" class="form-input" placeholder="Enter last name" required>
                    </div>
                </div>

                <div class="form-grid-3-col">
                    <div class="form-group">
                        <label class="form-label">Date of Birth <span class="required">*</span></label>
                        <input type="date" name="date_of_birth" class="form-input" value="2025-10-06" required>
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
                        <label class="form-label">Marital Status</label>
                        <select name="marital_status" class="form-select">
                            <option value="">Select status</option>
                            <option value="single">Single</option>
                            <option value="married">Married</option>
                            <option value="divorced">Divorced</option>
                            <option value="widowed">Widowed</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-input form-textarea" placeholder="Enter address"></textarea>
                </div>

                <div class="tab-section-header">
                    <h2>Contact Information</h2>
                </div>

                <div class="form-grid-3-col">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input" placeholder="Enter email address" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number <span class="required">*</span></label>
                        <input type="tel" name="phone" class="form-input" placeholder="Enter phone number" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Preferred Contact Method</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="preferred_contact" value="Phone" checked> Phone
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="preferred_contact" value="Email"> Email
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="preferred_contact" value="SMS"> SMS
                        </label>
                    </div>
                </div>

                <div class="tab-section-header">
                    <h2>Emergency Contact</h2>
                </div>

                <div class="form-grid-2-col">
                    <div class="form-group">
                        <label class="form-label">Contact Name</label>
                        <input type="text" name="emergency_name" class="form-input" placeholder="Enter emergency contact name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Relationship</label>
                        <input type="text" name="relationship" class="form-input" placeholder="Enter relationship">
                    </div>
                </div>

                <div class="form-grid-2-col">
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="emergency_phone" class="form-input" placeholder="Enter emergency contact phone">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email (Optional)</label>
                        <input type="email" name="emergency_email" class="form-input" placeholder="Enter emergency contact email">
                    </div>
                </div>
            </div>

            <!-- Medical Information Tab -->
            <div class="tab-content" id="medical-tab">
                <!-- ... (medical information content remains the same) ... -->
                <div class="tab-section-header">
                    <h2>Medical Information</h2>
                    <p>Enter the patient's medical history and details.</p>
                </div>

                <div class="form-grid-3-col">
                    <div class="form-group">
                        <label class="form-label">Blood Type</label>
                        <select name="blood_type" class="form-select">
                            <option value="">Select blood type</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Height (cm)</label>
                        <input type="number" name="height" class="form-input" placeholder="Enter height">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Weight (kg)</label>
                        <input type="number" name="weight" class="form-input" placeholder="Enter weight">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Allergies</label>
                    <textarea name="allergies" class="form-input form-textarea" placeholder="List any allergies (medications, food, etc.)"></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Current Medications</label>
                    <textarea name="current_medications" class="form-input form-textarea" placeholder="List any current medications"></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Chronic Conditions</label>
                    <textarea name="chronic_conditions" class="form-input form-textarea" placeholder="List any chronic conditions"></textarea>
                </div>

                <div class="tab-section-header">
                    <h2>Medical History</h2>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Past Surgeries</label>
                    <textarea name="past_surgeries" class="form-input form-textarea" placeholder="List any past surgeries with dates"></textarea>
                </div>

                <div class="tab-section-header">
                    <h2>Family Medical History</h2>
                </div>

                <div class="form-grid-2-col" style="margin-bottom: 0.5rem;">
                    <div class="form-group">
                        <label class="radio-label">
                            <input type="checkbox" name="family_history[]" value="Diabetes"> Diabetes
                        </label>
                        <label class="radio-label">
                            <input type="checkbox" name="family_history[]" value="Hypertension"> Hypertension
                        </label>
                        <label class="radio-label">
                            <input type="checkbox" name="family_history[]" value="Asthma"> Asthma
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="radio-label">
                            <input type="checkbox" name="family_history[]" value="Heart Disease"> Heart Disease
                        </label>
                        <label class="radio-label">
                            <input type="checkbox" name="family_history[]" value="Cancer"> Cancer
                        </label>
                        <label class="radio-label">
                            <input type="checkbox" name="family_history[]" value="Mental Health Conditions"> Mental Health Conditions
                        </label>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Additional Family History Notes</label>
                    <textarea name="additional_family_notes" class="form-input form-textarea" placeholder="Enter any additional family medical history"></textarea>
                </div>

                <div class="tab-section-header">
                    <h2>Lifestyle Information</h2>
                </div>

                <div class="form-grid-3-col">
                    <div class="form-group">
                        <label class="form-label">Smoking Status</label>
                        <select name="smoking_status" class="form-select">
                            <option value="">Select status</option>
                            <option value="never">Never Smoked</option>
                            <option value="former">Former Smoker</option>
                            <option value="current">Current Smoker</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Alcohol Consumption</label>
                        <select name="alcohol_consumption" class="form-select">
                            <option value="">Select consumption</option>
                            <option value="none">None</option>
                            <option value="social">Social/Occasional</option>
                            <option value="moderate">Moderate</option>
                            <option value="heavy">Heavy</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Exercise Frequency</label>
                        <select name="exercise_frequency" class="form-select">
                            <option value="">Select frequency</option>
                            <option value="none">None</option>
                            <option value="rarely">Rarely</option>
                            <option value="1-2">1-2 times/week</option>
                            <option value="3+">3+ times/week</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Dietary Habits</label>
                    <textarea name="dietary_habits" class="form-input form-textarea" placeholder="Describe dietary habits"></textarea>
                </div>
            </div>

            <!-- Insurance & Billing Tab -->
            <div class="tab-content" id="insurance-tab">
                <div class="tab-section-header">
                    <h2>Insurance & Billing</h2>
                    <p>Enter insurance policy and billing details.</p>
                </div>

                <div class="form-grid-2-col">
                    <div class="form-group">
                        <label class="form-label">Primary Insurance Provider</label>
                        <input type="text" name="insurance_provider" class="form-input" placeholder="Enter insurance company name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Policy Number</label>
                        <input type="text" name="policy_number" class="form-input" placeholder="Enter policy number">
                    </div>
    </div>
    <div class="form-grid-2-col">
                    <div class="form-group">
                        <label class="form-label">Policy Holder Name</label>
                        <input type="text" name="policy_holder" class="form-input" placeholder="Enter policy holder's name">
                    </div>

                     <div class="form-group">
                        <label class="form-label">Relationship to Patient</label>
                        <select name="gender" class="form-select" required>
                            <option value="">Select Relation</option>
                            <option value="self">Self</option>
                            <option value="spouse">Spouse</option>
                            <option value="children">Child</option>
                            <option value="parent">Parent</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    </div>
                      <div class="form-group">
                        <label class="form-label">Insurance Phone Number</label>
                        <input type="text" name="insurance_number" class="form-input" placeholder="Enter insurance contact number">

                <div class="tab-section-header">
                    <h2>Payment Method</h2>
                    <p>Select preferred payment method for medical services.</p>
                    <!-- Add this hidden field to your form in add_patient.php -->
<input type="hidden" name="payment_method" id="payment_method" value="">

<!-- Add this JavaScript to handle payment method selection -->
<script>
// Update payment method when a payment card is selected
document.querySelectorAll('.payment-method-card').forEach(card => {
    card.addEventListener('click', function() {
        const method = this.getAttribute('data-method');
        document.getElementById('payment_method').value = method;
        
        // Remove selected class from all cards
        document.querySelectorAll('.payment-method-card').forEach(c => {
            c.classList.remove('selected');
        });
        
        // Add selected class to clicked card
        this.classList.add('selected');
        
        // Hide all payment details
        document.querySelectorAll('.insurance-details, .mpesa-details, .card-details, .cash-details').forEach(detail => {
            detail.classList.remove('active');
        });
        
        // Show selected payment method details
        switch(method) {
            case 'insurance':
                document.getElementById('insurance-details').classList.add('active');
                break;
            case 'mpesa':
                document.getElementById('mpesa-details').classList.add('active');
                break;
            case 'card':
                document.getElementById('card-details').classList.add('active');
                break;
            case 'cash':
                document.getElementById('cash-details').classList.add('active');
                break;
        }
    });
});
</script>
                </div>
                    
                <div class="payment-methods">
                    <!-- Insurance Card -->
                    <div class="payment-method-card" data-method="insurance">
                        <div class="payment-method-header">
                            <div class="payment-method-icon insurance-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div>
                                <h3 class="payment-method-title">Insurance</h3>
                                <p class="payment-method-description">Bill to insurance provider</p>
                            </div>
                        </div>
                    </div>

                    <!-- M-Pesa Card -->
                    <div class="payment-method-card" data-method="mpesa">
                        <div class="payment-method-header">
                            <div class="payment-method-icon mpesa-icon">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <div>
                                <h3 class="payment-method-title">M-Pesa</h3>
                                <p class="payment-method-description">Mobile money payment</p>
                            </div>
                        </div>
                    </div>

                    <!-- Cash Card -->
                    <div class="payment-method-card" data-method="cash">
                        <div class="payment-method-header">
                            <div class="payment-method-icon cash-icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div>
                                <h3 class="payment-method-title">Cash</h3>
                                <p class="payment-method-description">Pay with cash</p>
                            </div>
                        </div>
                    </div>

                    <!-- Card Payment -->
                    <div class="payment-method-card" data-method="card">
                        <div class="payment-method-header">
                            <div class="payment-method-icon card-icon">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div>
                                <h3 class="payment-method-title">Card</h3>
                                <p class="payment-method-description">Credit/Debit card</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Insurance Details -->
                <div class="insurance-details" id="insurance-details">
                    <h3 style="margin-bottom: 1rem; color: #0f172a;">Insurance Verification</h3>
                    <div class="form-grid-2-col">
                        <div class="form-group">
                            <label class="form-label">Insurance ID Card</label>
                            <input type="file" name="insurance_card" class="form-input" accept="image/*,.pdf">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Verification Status</label>
                            <select name="verification_status" class="form-select">
                                <option value="pending">Pending Verification</option>
                                <option value="verified">Verified</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Coverage Notes</label>
                        <textarea name="coverage_notes" class="form-input form-textarea" placeholder="Enter any coverage limitations or notes"></textarea>
                    </div>
                </div>

                <!-- M-Pesa Details -->
                <div class="mpesa-details" id="mpesa-details">
                    <h3 style="margin-bottom: 1rem; color: #0f172a;">M-Pesa Payment Details</h3>
                    <div class="form-grid-2-col">
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="mpesa_phone" class="form-input" placeholder="07XXXXXXXX">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Transaction Code</label>
                            <input type="text" name="mpesa_code" class="form-input" placeholder="Enter M-Pesa transaction code">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Amount</label>
                        <input type="number" name="mpesa_amount" class="form-input" placeholder="Enter amount in KES">
                    </div>
                </div>

                <!-- Card Details -->
                <div class="card-details" id="card-details">
                    <h3 style="margin-bottom: 1rem; color: #0f172a;">Card Payment Details</h3>
                    <div class="form-grid-2-col">
                        <div class="form-group">
                            <label class="form-label">Card Number</label>
                            <div class="card-input-container">
                                <input type="text" name="card_number" class="form-input" placeholder="1234 5678 9012 3456" maxlength="19">
                                <i class="fas fa-credit-card card-icon-input"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Card Holder Name</label>
                            <input type="text" name="card_holder" class="form-input" placeholder="Enter name as on card">
                        </div>
                    </div>
                    <div class="form-grid-3-col">
                        <div class="form-group">
                            <label class="form-label">Expiry Date</label>
                            <input type="month" name="card_expiry" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">CVV</label>
                            <input type="text" name="card_cvv" class="form-input" placeholder="123" maxlength="4">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Amount</label>
                            <input type="number" name="card_amount" class="form-input" placeholder="Enter amount in KES">
                        </div>
                    </div>
                </div>

                <!-- Cash Details -->
                <div class="cash-details" id="cash-details">
                    <div class="cash-icon-large">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <h3 style="margin-bottom: 1rem; color: #0f172a;">Cash Payment</h3>
                    <p style="color: #64748b; margin-bottom: 1.5rem;">Payment will be processed at the reception desk.</p>
                    <div class="form-group" style="max-width: 200px; margin: 0 auto;">
                        <label class="form-label">Estimated Amount (KES)</label>
                        <input type="number" name="cash_amount" class="form-input" placeholder="Enter estimated amount">
                    </div>
                </div>

                <div class="tab-section-header">
                    <h2>Billing Address</h2>
                </div>

                <div class="form-group">
                    <label class="radio-label">
                        <input type="checkbox" name="same_as_personal" value="1" checked> 
                        Same as personal address
                    </label>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Billing Address</label>
                    <textarea name="billing_address" class="form-input form-textarea" placeholder="Enter billing address"></textarea>
                </div>
            </div>
        </div>

            <?php
// Function to handle file uploads (add this to your processing file or a separate functions file)
function handleFileUploads($pdo, $patient_id, $files) {
    if (isset($files['insurance_card']) && $files['insurance_card']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "../uploads/insurance_cards/";
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = pathinfo($files['insurance_card']['name'], PATHINFO_EXTENSION);
        $file_name = "insurance_card_" . $patient_id . "_" . time() . "." . $file_extension;
        $file_path = $upload_dir . $file_name;

        if (move_uploaded_file($files['insurance_card']['tmp_name'], $file_path)) {
            // Insert file record into database
            $file_stmt = $pdo->prepare("
                INSERT INTO files (patient_id, uploaded_by, file_path, file_type, description) 
                VALUES (?, ?, ?, 'insurance_card', 'Insurance ID Card')
            ");
            $file_stmt->execute([$patient_id, $_SESSION['user_id'], $file_path]);
            return true;
        }
    }
    return false;
}

// Function to send welcome notification (optional)
function sendPatientWelcome($patient_email, $patient_name, $patient_id) {
    if (!empty($patient_email)) {
        $subject = "Welcome to Our Healthcare System";
        $message = "Dear {$patient_name},\n\nWelcome to our healthcare system! Your patient ID is: {$patient_id}\n\nThank you for choosing us for your healthcare needs.";
        $headers = "From: noreply@hospital.com";
        
        // In production, use a proper email library
        // mail($patient_email, $subject, $message, $headers);
        
        error_log("Welcome email would be sent to: {$patient_email}");
    }
}
?>
        <div class="form-navigation">
            <a href="#" class="btn btn-secondary">Cancel</a>
            
            <div id="step-buttons" style="display: flex; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" id="prevBtn" onclick="changeTab(-1)" style="display: none;">
                    <i class="fas fa-arrow-left"></i>
                    Previous
                </button>
                <button type="button" class="btn btn-primary" id="nextBtn" onclick="changeTab(1)">
                    Next: Insurance & Billing
                    <i class="fas fa-arrow-right"></i>
                </button>
                <button type="submit" class="btn btn-primary" id="submitBtn" style="display: none;">
                    Register Patient
                </button>
            </div>
        </div>
    </form>
</div>

<script>
let currentTab = 0;
const tabs = ['personal', 'medical', 'insurance'];
let selectedPaymentMethod = '';

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
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');

    // Show/hide Previous button
    if (n > 0) {
        prevBtn.style.display = 'inline-flex';
        prevBtn.innerHTML = `<i class="fas fa-arrow-left"></i> Previous: ${tabButtons[n - 1].textContent}`;
    } else {
        prevBtn.style.display = 'none';
    }
    
    // Show/hide Next and Submit buttons
    if (n < tabs.length - 1) {
        nextBtn.style.display = 'inline-flex';
        nextBtn.innerHTML = `Next: ${tabButtons[n + 1].textContent} <i class="fas fa-arrow-right"></i>`;
        submitBtn.style.display = 'none';
    } else {
        nextBtn.style.display = 'none';
        submitBtn.style.display = 'inline-flex';
    }
    
    currentTab = n;
}

function changeTab(direction) {
    const newTab = currentTab + direction;
    
    // Basic form validation for the current step would go here before advancing
    if (validateCurrentTab()) {
        if (newTab >= 0 && newTab < tabs.length) {
            showTab(newTab);
        }
    }
}

function validateCurrentTab() {
    // Add validation logic here for each tab
    // For now, just return true to allow navigation
    return true;
}

// Payment Method Selection
document.querySelectorAll('.payment-method-card').forEach(card => {
    card.addEventListener('click', function() {
        const method = this.getAttribute('data-method');
        
        // Remove selected class from all cards
        document.querySelectorAll('.payment-method-card').forEach(c => {
            c.classList.remove('selected');
        });
        
        // Add selected class to clicked card
        this.classList.add('selected');
        
        // Hide all payment details
        document.querySelectorAll('.insurance-details, .mpesa-details, .card-details, .cash-details').forEach(detail => {
            detail.classList.remove('active');
        });
        
        // Show selected payment method details
        switch(method) {
            case 'insurance':
                document.getElementById('insurance-details').classList.add('active');
                break;
            case 'mpesa':
                document.getElementById('mpesa-details').classList.add('active');
                break;
            case 'card':
                document.getElementById('card-details').classList.add('active');
                break;
            case 'cash':
                document.getElementById('cash-details').classList.add('active');
                break;
        }
        
        selectedPaymentMethod = method;
    });
});

// Card number formatting
document.querySelector('input[name="card_number"]')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
    let matches = value.match(/\d{4,16}/g);
    let match = matches && matches[0] || '';
    let parts = [];
    
    for (let i = 0; i < match.length; i += 4) {
        parts.push(match.substring(i, i + 4));
    }
    
    if (parts.length) {
        e.target.value = parts.join(' ');
    } else {
        e.target.value = value;
    }
});

// Same as personal address toggle
document.querySelector('input[name="same_as_personal"]')?.addEventListener('change', function(e) {
    const billingAddress = document.querySelector('textarea[name="billing_address"]');
    if (this.checked) {
        billingAddress.value = document.querySelector('textarea[name="address"]').value;
        billingAddress.disabled = true;
    } else {
        billingAddress.disabled = false;
    }
});

// Allow clicking on tabs
document.querySelectorAll('.tab-button').forEach((button, index) => {
    button.addEventListener('click', () => {
        if (validateCurrentTab()) {
            showTab(index);
        }
    });
});
</script>

</body>
</html>