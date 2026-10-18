<?php
require_once "includes/db_connect.php";

echo "<h2>Creating SOS System Tables</h2>";

$queries = [
    "CREATE TABLE IF NOT EXISTS sos_alerts (
        id INT PRIMARY KEY AUTO_INCREMENT,
        patient_id INT NOT NULL,
        emergency_type VARCHAR(100) NOT NULL,
        priority ENUM('critical', 'high', 'medium', 'low') DEFAULT 'high',
        status ENUM('pending', 'active', 'acknowledged', 'responded', 'resolved', 'cancelled') DEFAULT 'pending',
        latitude DECIMAL(10, 8),
        longitude DECIMAL(11, 8),
        location_address TEXT,
        medical_summary TEXT,
        assigned_to INT,
        response_team_id INT,
        estimated_arrival DATETIME,
        actual_arrival DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        responded_at DATETIME,
        resolved_at DATETIME,
        notes TEXT,
        INDEX idx_patient_id (patient_id),
        INDEX idx_status (status),
        INDEX idx_priority (priority),
        INDEX idx_created_at (created_at)
    )" => "Create sos_alerts table",
    
    "CREATE TABLE IF NOT EXISTS emergency_teams (
        id INT PRIMARY KEY AUTO_INCREMENT,
        team_name VARCHAR(100) NOT NULL,
        team_type ENUM('medical', 'paramedic', 'nursing', 'security') DEFAULT 'medical',
        department VARCHAR(100),
        assigned_user_id INT,
        current_status ENUM('available', 'busy', 'off_duty', 'on_break') DEFAULT 'available',
        current_location VARCHAR(255),
        contact_number VARCHAR(20),
        vehicle_number VARCHAR(50),
        specializations TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )" => "Create emergency_teams table",
    
    "CREATE TABLE IF NOT EXISTS emergency_logs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        patient_id INT NOT NULL,
        alert_id INT,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_patient_emergency (patient_id),
        INDEX idx_alert_id (alert_id),
        INDEX idx_timestamp (timestamp)
    )" => "Create emergency_logs table"
];

foreach ($queries as $query => $description) {
    try {
        $pdo->exec($query);
        echo "<p style='color:green;'>✓ $description</p>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>✗ $description failed: " . $e->getMessage() . "</p>";
    }
}

echo "<hr><a href='test_sos.php'>Test Again</a>";
?>