<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['rider_id']) || !isset($_POST['status'])) {
    header("Location: index.php");
    exit();
}

$rider_id = $_POST['rider_id'];
$new_status = $_POST['status'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Validate status
$allowed_statuses = ['available', 'busy', 'offline'];
if (!in_array($new_status, $allowed_statuses)) {
    $_SESSION['error'] = "Invalid status";
    header("Location: " . ($role === 'rider' ? 'rider_dashboard.php' : 'admin_dashboard.php'));
    exit();
}

try {
    // Update rider status
    $stmt = $pdo->prepare("UPDATE riders SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $rider_id]);

    $_SESSION['success'] = "Rider status updated successfully";
} catch (Exception $e) {
    $_SESSION['error'] = "Error updating rider status: " . $e->getMessage();
}

header("Location: " . ($role === 'rider' ? 'rider_dashboard.php' : 'admin_dashboard.php'));
exit(); 