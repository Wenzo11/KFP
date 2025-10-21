<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = $_SESSION['user_id'];
    $pickup_location = $_POST['pickup_location'];
    $dropoff_location = $_POST['dropoff_location'];
    $booking_date = $_POST['booking_date'];
    $booking_time = $_POST['booking_time'];
    $status = 'pending';
    $created_at = date('Y-m-d H:i:s');

    try {
        $stmt = $pdo->prepare("INSERT INTO bookings (customer_id, pickup_location, dropoff_location, booking_date, booking_time, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$customer_id, $pickup_location, $dropoff_location, $booking_date, $booking_time, $status, $created_at]);
        
        $_SESSION['success'] = "Ride booked successfully!";
        header("Location: customer_dashboard.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Error booking ride: " . $e->getMessage();
    }
}

// Get available riders
$stmt = $pdo->query("SELECT * FROM riders WHERE status = 'available'");
$riders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Ride</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Book a Ride</h2>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label for="pickup_location" class="form-label">Pickup Location</label>
                <input type="text" class="form-control" id="pickup_location" name="pickup_location" required>
            </div>
            
            <div class="mb-3">
                <label for="dropoff_location" class="form-label">Dropoff Location</label>
                <input type="text" class="form-control" id="dropoff_location" name="dropoff_location" required>
            </div>
            
            <div class="mb-3">
                <label for="booking_date" class="form-label">Booking Date</label>
                <input type="date" class="form-control" id="booking_date" name="booking_date" required>
            </div>
            
            <div class="mb-3">
                <label for="booking_time" class="form-label">Booking Time</label>
                <input type="time" class="form-control" id="booking_time" name="booking_time" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Book Ride</button>
            <a href="customer_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 