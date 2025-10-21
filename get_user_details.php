<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

if (!isset($_GET['user_id'])) {
    http_response_code(400);
    exit('User ID is required');
}

$user_id = $_GET['user_id'];

// Get account information
$account_query = "SELECT * FROM account WHERE user_id = ?";
$stmt = $conn->prepare($account_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();

if (!$account) {
    http_response_code(404);
    exit('Account not found');
}

// Get user details based on role
$role = $account['role'];
$table = ($role === 'Customer') ? 'customers' : 'riders';
$query = "SELECT * FROM $table WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    http_response_code(404);
    exit('User details not found');
}

// Return the user details as JSON
header('Content-Type: application/json');
echo json_encode($user); 