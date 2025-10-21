<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$rider_user_id = $_SESSION['user_id'];

// Fetch the rider's name and rider_id from the database
$rider_name = '';
$rider_id = null;
$stmt = $conn->prepare("SELECT rider_id, name FROM riders WHERE user_id = ?");
$stmt->bind_param("i", $rider_user_id);
$stmt->execute();
$stmt->bind_result($rider_id, $rider_name);
$stmt->fetch();
$stmt->close();

// Check if booking_id is provided
if (isset($_POST['booking_id'])) {
    $booking_id = $_POST['booking_id'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // Check if booking is still pending
        $check_stmt = $conn->prepare("SELECT status FROM bookings WHERE booking_id = ?");
        $check_stmt->bind_param("i", $booking_id);
        $check_stmt->execute();
        $check_stmt->bind_result($current_status);
        $check_stmt->fetch();
        $check_stmt->close();
        if ($current_status !== 'Pending') {
            throw new Exception("Booking is no longer pending (current status: $current_status)");
        }
        // Update booking status to assigned and set rider information
        $sql = "UPDATE bookings SET 
                status = 'Assigned', 
                rider_id = ?, 
                rider_name = ? 
                WHERE booking_id = ? AND status = 'Pending'";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("isi", $rider_id, $rider_name, $booking_id);
            if ($stmt->execute()) {
                // Update rider status to 'On Duty'
                $update_rider = "UPDATE riders SET status = 'On Duty' WHERE user_id = ?";
                $rider_stmt = $conn->prepare($update_rider);
                $rider_stmt->bind_param("i", $rider_id);
                $rider_stmt->execute();
                $rider_stmt->close();
                $conn->commit();
                header("Location: rider_page.php?ride_accepted=1");
                exit();
            } else {
                throw new Exception("Error updating booking: " . $stmt->error);
            }
            $stmt->close();
        } else {
            throw new Exception("Database error: " . $conn->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error in accept_ride.php: " . $e->getMessage());
        $error_msg = urlencode($e->getMessage());
        header("Location: pending_rides.php?error=accept_failed&msg=$error_msg");
        exit();
    }
} else {
    // No booking ID provided
    header("Location: pending_rides.php?error=no_booking");
    exit();
}
?>
