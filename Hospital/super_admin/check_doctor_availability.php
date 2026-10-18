<?php
session_start();
include "includes/db_connect.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id = $_POST['doctor_id'] ?? null;
    $date = $_POST['date'] ?? null;
    
    if (!$doctor_id || !$date) {
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }
    
    // Check doctor's schedule for that day
    $day_of_week = date('N', strtotime($date)); // 1=Monday, 7=Sunday
    $schedule_stmt = $pdo->prepare("
        SELECT * FROM doctor_schedules 
        WHERE doctor_id = ? AND day_of_week = ?
    ");
    $schedule_stmt->execute([$doctor_id, $day_of_week]);
    $schedule = $schedule_stmt->fetch();
    
    if (!$schedule) {
        echo json_encode(['available_slots' => []]);
        exit;
    }
    
    // Get existing appointments for that day
    $appointments_stmt = $pdo->prepare("
        SELECT appointment_time 
        FROM appointments 
        WHERE doctor_id = ? 
        AND appointment_date = ? 
        AND status NOT IN ('cancelled', 'completed')
    ");
    $appointments_stmt->execute([$doctor_id, $date]);
    $booked_times = $appointments_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Generate time slots
    $start_time = strtotime($schedule['start_time']);
    $end_time = strtotime($schedule['end_time']);
    $interval = 30 * 60; // 30 minutes
    
    $available_slots = [];
    
    for ($time = $start_time; $time < $end_time; $time += $interval) {
        $slot_time = date('H:i', $time);
        $slot_end = date('H:i', $time + $interval);
        
        // Check if slot is booked
        $is_booked = false;
        foreach ($booked_times as $booked) {
            $booked_time = strtotime($booked);
            if (abs($booked_time - $time) < $interval) {
                $is_booked = true;
                break;
            }
        }
        
        $available_slots[] = [
            'time' => $slot_time,
            'end' => $slot_end,
            'status' => $is_booked ? 'busy' : 'available'
        ];
    }
    
    echo json_encode(['available_slots' => $available_slots]);
}
*/
?>