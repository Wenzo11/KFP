<?php
include 'connect.php';
session_start();

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

    if ($booking_id && $status) {
        // Update the booking status in the database
        $query = "UPDATE bookings SET status = ? WHERE booking_id = ?";
        $stmt = $conn->prepare($query);

        if ($stmt) {
            $stmt->bind_param("si", $status, $booking_id);

            if ($stmt->execute()) {
                // Redirect back to adminhome.php with a success message
                header("Location: adminhome.php?update=success");
                exit();
            } else {
                // Redirect back with an error message
                header("Location: adminhome.php?update=error");
                exit();
            }
        } else {
            // Redirect back with an error message if query preparation fails
            header("Location: adminhome.php?update=error");
            exit();
        }
    } else {
        // Redirect back with an invalid input error
        header("Location: adminhome.php?update=invalid");
        exit();
    }
} else {
    // Redirect back if the request method is not POST
    header("Location: adminhome.php?update=invalid");
    exit();
}
?>