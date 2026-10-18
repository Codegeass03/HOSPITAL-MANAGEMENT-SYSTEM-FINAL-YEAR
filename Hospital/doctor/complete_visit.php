<?php
session_start();
include "includes/db_connect.php";

if (!isset($_POST['appointment_id'], $_POST['patient_id'])) {
    $_SESSION['error'] = "Missing required parameters.";
    header("Location: clinical_notes.php?patient_id=" . ($_POST['patient_id'] ?? ''));
    exit();
}

$appointment_id = (int)$_POST['appointment_id'];
$patient_id     = (int)$_POST['patient_id'];
$doctor_id      = $_SESSION['user_id'] ?? null;

if (!$doctor_id) {
    $_SESSION['error'] = "Unauthorized access.";
    header("Location: login.php");
    exit();
}

try {
    $pdo->beginTransaction();

    /* 1️⃣ VERIFY appointment belongs to doctor & is completable */
    $check_stmt = $pdo->prepare("
        SELECT id 
        FROM appointments
        WHERE id = ?
          AND patient_id = ?
          AND doctor_id = ?
          AND status IN ('scheduled', 'confirmed', 'in_progress')
        FOR UPDATE
    ");
    $check_stmt->execute([$appointment_id, $patient_id, $doctor_id]);

    if ($check_stmt->rowCount() === 0) {
        throw new Exception("You are not allowed to complete this appointment.");
    }

    /* 2️⃣ Mark appointment as completed */
    $update_stmt = $pdo->prepare("
        UPDATE appointments
        SET status = 'completed',
            updated_at = NOW()
        WHERE id = ?
    ");
    $update_stmt->execute([$appointment_id]);

    /* 3️⃣ Save clinical notes (once per appointment) */
    if (!empty($_POST['symptoms']) || !empty($_POST['diagnosis'])) {

        $notes_stmt = $pdo->prepare("
            INSERT INTO clinical_notes 
            (patient_id, doctor_id, subjective, objective, assessment, plan, provider_name)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $notes_stmt->execute([
            $patient_id,
            $doctor_id,
            $_POST['symptoms'] ?? '',
            $_POST['objective'] ?? '',
            $_POST['diagnosis'] ?? '',
            $_POST['treatment_plan'] ?? '',
            $_SESSION['username'] ?? 'Dr. Unknown'
        ]);
    }

    $pdo->commit();
    $_SESSION['success'] = "Visit completed successfully.";

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Error completing visit: " . $e->getMessage();
    error_log("Complete visit error: " . $e->getMessage());
}

header("Location: clinical_notes.php?patient_id=" . $patient_id);
exit();
