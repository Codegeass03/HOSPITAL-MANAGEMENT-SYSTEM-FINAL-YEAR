<?php
session_start();  
// if(!isset($_SESSION["super_admin"])) {
//     header("Location: ../index.php");
//     exit();
// }

include "includes/db_connect.php";
include "includes/sidebar.php";
?>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff Management</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    body {
      margin: 0;
      display: flex;
      font-family: Arial, sans-serif;
    }
/* 


    /* Main content wrapper */
    .main-wrapper {
      margin-left: 250px;
      flex: 1;
      display: flex;
      flex-direction: column;
      background-color: #f8fafc;
      min-height: 100vh;
    }

    /* Your existing main-content style */
    .main-content {
      padding: 2rem;
      flex: 1;
      background-color: #f8fafc;
      min-height: 100vh;
    }

    .page-header {
      display: flex;
      justify-content: space-between; /* Fixed: Changed from "between" to "space-between" for proper right alignment */
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

    .add-staff-btn {
      background: linear-gradient(135deg, #0ea5e9, #0284c7);
      color: white;
      padding: 0.75rem 1.5rem;
      border: none;
      border-radius: 0.5rem;
      text-decoration: none;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.2s;
      box-shadow: 0 2px 4px rgba(14, 165, 233, 0.2);
    }

    .add-staff-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
    }

    .search-filters {
      display: flex;
      gap: 1rem;
      margin-bottom: 2rem;
      flex-wrap: wrap;
      align-items: center;
    }

    .search-box {
      position: relative;
      flex: 1;
      min-width: 300px;
    }

    .search-box input {
      width: 100%;
      padding: 0.75rem 1rem 0.75rem 2.5rem;
      border: 1px solid #e2e8f0;
      border-radius: 0.5rem;
      font-size: 0.875rem;
      background: white;
      transition: all 0.2s;
    }

    .search-box input:focus {
      outline: none;
      border-color: #0ea5e9;
      box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
    }

    .search-box .search-icon {
      position: absolute;
      left: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
    }

    .department-filter {
      padding: 0.75rem 1rem;
      border: 1px solid #e2e8f0;
      border-radius: 0.5rem;
      background: white;
      font-size: 0.875rem;
      color: #475569;
      min-width: 150px;
    }

    .staff-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 1.5rem;
    }

    .staff-card {
      background: white;
      border-radius: 0.75rem;
      padding: 1.5rem;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      transition: all 0.2s;
      border: 1px solid #f1f5f9;
    }

    .staff-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    .staff-header {
      display: flex;
      justify-content: space-between; /* Fixed: Changed from "between" to "space-between" for proper alignment */
      align-items: flex-start;
      margin-bottom: 1rem;
    }

    .staff-avatar {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      color: #64748b;
      font-weight: 600;
      margin-bottom: 1rem;
    }

    .staff-info h3 {
      margin: 0 0 0.25rem 0;
      color: #1e293b;
      font-size: 1.125rem;
      font-weight: 600;
    }

    .staff-role {
      color: #64748b;
      font-size: 0.875rem;
      margin-bottom: 1rem;
    }

    .status-badge {
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.025em;
    }

    .status-active {
      background-color: #dcfce7;
      color: #166534;
    }

    .status-leave {
      background-color: #fef3c7;
      color: #92400e;
    }

    .staff-details {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
      margin-bottom: 1rem;
    }

    .detail-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: #64748b;
      font-size: 0.875rem;
    }

    .detail-icon {
      width: 16px;
      color: #94a3b8;
    }

    .staff-stats {
      display: flex;
      gap: 1rem;
      margin-bottom: 1rem;
      padding-top: 1rem;
      border-top: 1px solid #f1f5f9;
    }

    .stat-item {
      display: flex;
      align-items: center;
      gap: 0.375rem;
      color: #64748b;
      font-size: 0.75rem;
    }

    .stat-icon {
      color: #fbbf24;
    }

    .staff-actions {
      display: flex;
      gap: 0.5rem;
      margin-top: 1rem;
    }

    .action-btn {
      flex: 1;
      padding: 0.5rem;
      border: 1px solid #e2e8f0;
      background: white;
      border-radius: 0.375rem;
      color: #64748b;
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.25rem;
      font-size: 0.875rem;
      transition: all 0.2s;
    }

    .action-btn:hover {
      background: #f8fafc;
      color: #334155;
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
      width: 90%;
      max-width: 500px;
      max-height: 90vh;
      overflow-y: auto;
    }

    .modal-header {
      padding: 1.5rem 1.5rem 0 1.5rem;
      display: flex;
      justify-content: space-between; /* Fixed: Changed from "between" to "space-between" for proper alignment */
      align-items: center;
    }

    .modal-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: #1e293b;
      margin: 0;
    }

    .modal-close {
      background: none;
      border: none;
      font-size: 1.5rem;
      color: #64748b;
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
      transition: border-color 0.2s;
    }

    .form-input:focus, .form-select:focus, .form-textarea:focus {
      outline: none;
      border-color: #0ea5e9;
      box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
    }

    .form-textarea {
      resize: vertical;
      min-height: 80px;
    }

    .modal-footer {
      padding: 0 1.5rem 1.5rem 1.5rem;
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
      text-decoration: none;
      font-weight: 500;
      transition: all 0.2s;
    }

    .btn-secondary:hover {
      background: #f9fafb;
    }

    .btn-primary {
      padding: 0.75rem 1.5rem;
      background: linear-gradient(135deg, #0ea5e9, #0284c7);
      color: white;
      border: none;
      border-radius: 0.5rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn-primary:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
    }
  </style>
</head>
<body>
<div class="main-content">
  <div class="page-header">
    <div>
      <h1 class="page-title">
        <i class="fas fa-user-md"></i>
        Staff Management
      </h1>
      <p class="page-subtitle">Manage doctors, nurses, and medical staff</p>
    </div>
 
    <button class="add-staff-btn" onclick="openAddStaffModal()">
      <i class="fas fa-plus"></i>
      Add Staff Member
    </button>
    
  </div>

  <div class="search-filters">
    <div class="search-box">
      <i class="fas fa-search search-icon"></i>
      <input type="text" placeholder="Search staff by name, department, or role..." id="searchInput">
    </div>
    <select class="department-filter" id="departmentFilter">
      <option value="">All Departments</option>
      <option value="cardiology">Cardiology</option>
      <option value="neurology">Neurology</option>
      <option value="emergency">Emergency</option>
      <option value="orthopedics">Orthopedics</option>
      <option value="pharmacy">Pharmacy</option>
      <option value="lab">Lab</option>
      <option value="hr">HR</option>
    </select>
  </div>

  <div class="staff-grid">
    <!-- Dr. Sarah Wilson -->
    <div class="staff-card">
      <div class="staff-header">
        <div class="staff-avatar">SW</div>
        <span class="status-badge status-active">Active</span>
      </div>
      <div class="staff-info">
        <h3>Dr. Sarah Wilson</h3>
        <div class="staff-role">Senior Doctor</div>
      </div>
      <div class="staff-details">
        <div class="detail-item">
          <i class="fas fa-stethoscope detail-icon"></i>
          <span>Cardiology</span>
        </div>
        <div class="detail-item">
          <i class="fas fa-procedures detail-icon"></i>
          <span>Interventional Cardiology</span>
        </div>
        <div class="detail-item">
          <i class="fas fa-clock detail-icon"></i>
          <span>15 years</span>
        </div>
        <div class="detail-item">
          <i class="fas fa-calendar detail-icon"></i>
          <span>Mon-Fri 8:00-16:00</span>
        </div>
      </div>
      <div class="staff-actions">
        <a href="#" class="action-btn">
          <i class="fas fa-edit"></i>
        </a>
        <a href="#" class="action-btn">
          <i class="fas fa-phone"></i>
        </a>
        <a href="#" class="action-btn">
          <i class="fas fa-envelope"></i>
        </a>
      </div>
    </div>

    <!-- Dr. Michael Anderson -->
    <div class="staff-card">
      <div class="staff-header">
        <div class="staff-avatar">MA</div>
        <span class="status-badge status-active">Active</span>
      </div>
      <div class="staff-info">
        <h3>Dr. Michael Anderson</h3>
        <div class="staff-role">Department Head</div>
      </div>
      <div class="staff-details">
        <div class="detail-item">
          <i class="fas fa-brain detail-icon"></i>
          <span>Neurology</span>
        </div>
        <div class="detail-item">
          <i class="fas fa-user-md detail-icon"></i>
          <span>Neurological Surgery</span>
        </div>
        <div class="detail-item">
          <i class="fas fa-clock detail-icon"></i>
          <span>20 years</span>
        </div>
        <div class="detail-item">
          <i class="fas fa-calendar detail-icon"></i>
          <span>Mon-Thu 9:00-17:00</span>
        </div>
      </div>
      <div class="staff-actions">
        <a href="#" class="action-btn">
          <i class="fas fa-edit"></i>
        </a>
        <a href="#" class="action-btn">
          <i class="fas fa-phone"></i>
        </a>
        <a href="#" class="action-btn">
          <i class="fas fa-envelope"></i>
        </a>
      </div>
    </div>

    <!-- Nurse Emma Thompson -->
    <div class="staff-card">
      <div class="staff-header">
        <div class="staff-avatar">ET</div>
        <span class="status-badge status-active">Active</span>
      </div>
      <div class="staff-info">
        <h3>Nurse Emma Thompson</h3>
        <div class="staff-role">Head Nurse</div>
      </div>
      <div class="staff-details">
        <div class="detail-item">
          <i class="fas fa-ambulance detail-icon"></i>
          <span>Emergency</span>
        </div>
        <div class="detail-item">
          <i class="fas fa-clock detail-icon"></i>
          <span>12 years</span>
        </div>
        <div class="detail-item">
          <i class="fas fa-calendar detail-icon"></i>
          <span>24/7 Shifts</span>
        </div>
      </div>
      <div class="staff-actions">
        <a href="#" class="action-btn">
          <i class="fas fa-edit"></i>
        </a>
        <a href="#" class="action-btn">
          <i class="fas fa-phone"></i>
        </a>
        <a href="#" class="action-btn">
          <i class="fas fa-envelope"></i>
        </a>
      </div>
    </div>

    <!-- Dr. James Garcia -->
    <div class="staff-card">
      <div class="staff-header">
        <div class="staff-avatar">JG</div>
        <span class="status-badge status-leave">On Leave</span>
      </div>
      <div class="staff-info">
        <h3>Dr. James Garcia</h3>
        <div class="staff-role">Consultant</div>
      </div>
      <div class="staff-details">
        <div class="detail-item">
          <i class="fas fa-bone detail-icon"></i>
          <span>Orthopedics</span>
        </div>
        <div class="detail-item">
          <i class="fas fa-running