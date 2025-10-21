<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if (isset($_POST['booking_id'])) {
    $booking_id = $_POST['booking_id'];
    $stmt = $conn->prepare("DELETE FROM bookings WHERE booking_id = ?");
    $stmt->bind_param("i", $booking_id);
    if ($stmt->execute()) {
        header("Location: adminhome.php?delete=success");
        exit();
    } else {
        header("Location: adminhome.php?delete=error");
        exit();
    }
} else {
    header("Location: adminhome.php?delete=invalid");
    exit();
}
?> 