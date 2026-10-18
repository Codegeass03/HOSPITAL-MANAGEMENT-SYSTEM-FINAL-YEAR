<?php
session_start();
include("connection.php");

if($_SESSION["usertype"] != "d") {
    header("location: login.php");
    exit();
}

if($_POST) {
    $patient_id = $_POST['patient_id'];
    $doctor_id = $_SESSION['user_id'];
    $medication_name = $_POST['medication_name'];
    $dosage = $_POST['dosage'];
    $frequency = $_POST['frequency'];
    $duration = $_POST['duration'];
    $instructions = $_POST['instructions'];
    
    // Insert prescription
    $sql = "INSERT INTO prescriptions (patient_id, doctor_id, medication_name, 
            dosage, frequency, duration, instructions) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $database->prepare($sql);
    $stmt->bind_param("iisssss", $patient_id, $doctor_id, $medication_name, 
                     $dosage, $frequency, $duration, $instructions);
    
    if($stmt->execute()) {
        // Check pharmacy stock
        $stock_sql = "SELECT * FROM stock_master 
                     WHERE product_name LIKE ? AND product_quantity > 0 
                     ORDER BY product_selling_price ASC LIMIT 1";
        $stmt = $database->prepare($stock_sql);
        $search_term = "%" . $medication_name . "%";
        $stmt->bind_param("s", $search_term);
        $stmt->execute();
        $stock_result = $stmt->get_result();
        
        if($stock_result->num_rows > 0) {
            $stock = $stock_result->fetch_assoc();
            $success = "Prescription created and medication is available in pharmacy.";
        } else {
            $warning = "Prescription created but medication is out of stock in pharmacy.";
        }
    } else {
        $error = "Error creating prescription.";
    }
}
?>