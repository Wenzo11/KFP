<?php
session_start();
include 'connect.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);
print_r($_POST); // Add this temporarily to see what is being sent

if (isset($_POST['rider_id']) && isset($_POST['booking_id'])) {
    $rider_id = $_POST['rider_id'];
    $booking_id = $_POST['booking_id'];

    // Fetch the rider's name and user_id from the riders table
    $rider_name = null;
    $user_id = null;
    $rider_stmt = $conn->prepare("SELECT name, user_id FROM riders WHERE rider_id = ?");
    if ($rider_stmt) {
        $rider_stmt->bind_param("i", $rider_id);
        $rider_stmt->execute();
        $rider_stmt->bind_result($rider_name, $user_id);
        $rider_stmt->fetch();
        $rider_stmt->close();

        // Debug log
        error_log("Rider assignment - Rider ID: $rider_id, User ID: $user_id, Name: $rider_name");
    }

    if ($rider_name && $rider_id) {
        // Update both rider_id (should be rider_id from riders), rider_name, and set status to 'Assigned'
        $updateQuery = "UPDATE bookings SET rider_id = ?, rider_name = ?, status = 'Assigned' WHERE booking_id = ?";
        $stmt = $conn->prepare($updateQuery);

        if ($stmt) {
            $stmt->bind_param("isi", $rider_id, $rider_name, $booking_id);
            if ($stmt->execute()) {
                // Debug log
                error_log("Successfully assigned rider to booking $booking_id");
                $_SESSION['success'] = "Rider assigned successfully!";
                header("Location: adminhome.php"); // Redirect after assignment
                exit();
            } else {
                error_log("Error updating booking: " . $stmt->error);
                echo "Error: Unable to assign rider. " . $stmt->error;
            }
            $stmt->close();
        } else {
            error_log("Error preparing update query: " . $conn->error);
            echo "Error: Unable to prepare the assign query. " . $conn->error;
        }
    } else {
        error_log("Error: Could not find rider information for rider_id: $rider_id");
        echo "Error: Could not find rider information.";
    }
} else {
    error_log("Error: Missing required POST data. POST: " . print_r($_POST, true));
    echo "Error: Invalid data. POST: ";
    print_r($_POST);
}

echo 'Logged in as user_id: ' . $_SESSION['user_id'];
?>
