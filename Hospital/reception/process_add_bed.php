<?php
// process_add_bed.php
session_start();
include "includes/db_connect.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ward_id = $_POST['ward_id'];
    $bed_number = $_POST['bed_number'];
    $bed_type = $_POST['bed_type'];
    $price_per_day = $_POST['price_per_day'];
    $bed_status = $_POST['bed_status'];
    $description = $_POST['description'] ?? '';
    $features = $_POST['features'] ?? '';

    try {
        // Check if bed number already exists in this ward
        $stmt = $pdo->prepare("SELECT id FROM beds WHERE bed_number = ? AND ward_id = ?");
        $stmt->execute([$bed_number, $ward_id]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = "Bed number already exists in this ward.";
            header("Location: beds.php?ward_id=" . $ward_id);
            exit();
        }

        // Insert new bed
        $stmt = $pdo->prepare("INSERT INTO beds (bed_number, ward_id, bed_type, bed_status, price_per_day, description, features) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$bed_number, $ward_id, $bed_type, $bed_status, $price_per_day, $description, $features]);

        $_SESSION['success'] = "Bed added successfully!";
        header("Location: beds.php?ward_id=" . $ward_id);
        exit();

    } catch (PDOException $e) {
        $_SESSION['error'] = "Error adding bed: " . $e->getMessage();
        header("Location: beds.php?ward_id=" . $ward_id);
        exit();
    }
} else {
    header("Location: beds.php");
    exit();
}
?>