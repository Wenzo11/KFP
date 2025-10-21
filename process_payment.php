<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit();
}
require 'connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = $_POST['booking_id'];
    $amount = $_POST['amount'];
    $payment_method = $_POST['payment_method'];
    $payment_status = 'Completed';
    $payment_date = date('Y-m-d H:i:s');

    // Check if payment already exists
    $check_query = "SELECT * FROM payments WHERE booking_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $booking_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows === 0) {
        // Insert new payment
        $insert_query = "INSERT INTO payments (booking_id, amount, payment_method, payment_status, payment_date) 
                        VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("idsss", $booking_id, $amount, $payment_method, $payment_status, $payment_date);
        
        if ($stmt->execute()) {
            // Update booking status if needed
            $update_booking = "UPDATE bookings SET status = 'Completed' WHERE booking_id = ?";
            $update_stmt = $conn->prepare($update_booking);
            $update_stmt->bind_param("i", $booking_id);
            $update_stmt->execute();
            
            header("Location: ride_history.php?payment_success=1");
            exit();
        } else {
            header("Location: ride_history.php?payment_error=1");
            exit();
        }
    } else {
        header("Location: ride_history.php?payment_exists=1");
        exit();
    }
} else {
    header("Location: ride_history.php");
    exit();
}
?> 