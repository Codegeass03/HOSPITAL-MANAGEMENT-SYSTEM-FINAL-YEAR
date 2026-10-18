<?php
// delete_bed.php
session_start();
include "includes/db_connect.php";

if (isset($_GET['id']) && isset($_GET['ward_id'])) {
    $bed_id = $_GET['id'];
    $ward_id = $_GET['ward_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM beds WHERE id = ?");
        $stmt->execute([$bed_id]);
        
        $_SESSION['success'] = "Bed deleted successfully!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting bed: " . $e->getMessage();
    }
    
    header("Location: beds.php?ward_id=" . $ward_id);
    exit();
} else {
    header("Location: wards.php");
    exit();
}
?>