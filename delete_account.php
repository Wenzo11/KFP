<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['user_id']) || !filter_var($_GET['user_id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error'] = "Invalid user ID";
    header("Location: adminhome.php");
    exit();
}

$userId = $_GET['user_id'];

$conn->begin_transaction();

try {
    error_log('Delete account called with user_id: ' . $_GET['user_id']);
    error_log('Delete account script running for user_id: ' . $userId);

    // Get the role and table
    $roleQuery = "SELECT role FROM account WHERE user_id = ?";
    $stmt = $conn->prepare($roleQuery);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);
    $result = $stmt->get_result();
    $account = $result->fetch_assoc();

    if ($account) {
        $role = $account['role'];
        $table = ($role === 'Customer') ? 'customers' : 'riders';

        // Get the customer_id or rider_id
        $idQuery = "SELECT " . ($role === 'Customer' ? 'customer_id' : 'rider_id') . " FROM $table WHERE user_id = ?";
        $stmt = $conn->prepare($idQuery);
        if (!$stmt) throw new Exception("Prepare failed for ID query: " . $conn->error);
        $stmt->bind_param("i", $userId);
        if (!$stmt->execute()) throw new Exception("Execute failed for ID query: " . $stmt->error);
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row) {
            $id = $row[$role === 'Customer' ? 'customer_id' : 'rider_id'];

            if ($role === 'Customer') {
                // Delete related payments
                $deletePaymentsQuery = "DELETE FROM payments WHERE booking_id IN (SELECT booking_id FROM bookings WHERE customer_id = ?)";
                $stmt = $conn->prepare($deletePaymentsQuery);
                if (!$stmt) throw new Exception("Prepare failed for payments deletion: " . $conn->error);
                $stmt->bind_param("i", $id);
                if (!$stmt->execute()) throw new Exception("Execute failed for payments deletion: " . $stmt->error);

                // Delete related bookings
                $deleteBookingsQuery = "DELETE FROM bookings WHERE customer_id = ?";
                $stmt = $conn->prepare($deleteBookingsQuery);
                if (!$stmt) throw new Exception("Prepare failed for bookings deletion: " . $conn->error);
                $stmt->bind_param("i", $id);
                if (!$stmt->execute()) throw new Exception("Execute failed for bookings deletion: " . $stmt->error);

                // Now delete from customers
                $deleteRoleQuery = "DELETE FROM customers WHERE user_id = ?";
                $stmt = $conn->prepare($deleteRoleQuery);
                if (!$stmt) throw new Exception("Prepare failed for role table: " . $conn->error);
                $stmt->bind_param("i", $userId);
                if (!$stmt->execute()) throw new Exception("Execute failed for role table: " . $stmt->error);
            } else {
                // For riders, set rider_id to NULL in bookings
                $updateBookingsQuery = "UPDATE bookings SET rider_id = NULL WHERE rider_id = ?";
                $stmt = $conn->prepare($updateBookingsQuery);
                if (!$stmt) throw new Exception("Prepare failed for bookings update: " . $conn->error);
                $stmt->bind_param("i", $id);
                if (!$stmt->execute()) throw new Exception("Execute failed for bookings update: " . $stmt->error);

                // Now delete from riders
                $deleteRoleQuery = "DELETE FROM riders WHERE user_id = ?";
                $stmt = $conn->prepare($deleteRoleQuery);
                if (!$stmt) throw new Exception("Prepare failed for role table: " . $conn->error);
                $stmt->bind_param("i", $userId);
                if (!$stmt->execute()) throw new Exception("Execute failed for role table: " . $stmt->error);
            }
        }
    }

    // Always attempt to delete the account record
    $deleteAccountQuery = "DELETE FROM account WHERE user_id = ?";
    $stmt = $conn->prepare($deleteAccountQuery);
    if (!$stmt) throw new Exception("Prepare failed for account table: " . $conn->error);
    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) throw new Exception("Execute failed for account table: " . $stmt->error);

    $conn->commit();
    $_SESSION['success'] = "Account and all related records deleted successfully";
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Error deleting account: " . $e->getMessage();
}

header("Location: adminhome.php");
exit();
?> 