<?php
session_start();
require_once "includes/db_connect.php";

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user has admin privileges
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!in_array($user['role'], ['Super Admin', 'Admin', 'Department Head'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_team'])) {
    $team_id = $_POST['team_id'];
    $team_name = $_POST['team_name'];
    $team_type = $_POST['team_type'];
    $department = $_POST['department'] ?? null;
    $contact_number = $_POST['contact_number'] ?? null;
    $vehicle_number = $_POST['vehicle_number'] ?? null;
    $specializations = $_POST['specializations'] ?? null;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE emergency_teams 
            SET team_name = ?, 
                team_type = ?, 
                department = ?,
                contact_number = ?,
                vehicle_number = ?,
                specializations = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $team_name, $team_type, $department,
            $contact_number, $vehicle_number,
            $specializations, $team_id
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Team updated successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating team: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}