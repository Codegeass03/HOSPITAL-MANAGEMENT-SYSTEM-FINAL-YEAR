<?php
session_start();
require_once "includes/db_connect.php";

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'No team ID provided']);
    exit();
}

$team_id = intval($_GET['id']);

try {
    $stmt = $pdo->prepare("SELECT * FROM emergency_teams WHERE id = ?");
    $stmt->execute([$team_id]);
    $team = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($team) {
        echo json_encode(['success' => true, 'team' => $team]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Team not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}