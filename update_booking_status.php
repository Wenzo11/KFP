<o?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['booking_id']) || !isset($_POST['status'])) {
    header("Location: index.php");
    exit();
}

$booking_id = $_POST['booking_id'];
$new_status = $_POST['status'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Simplified status validation
$allowed_statuses = ['pending', 'accepted', 'completed'];
if (!in_array($new_status, $allowed_statuses)) {
    $_SESSION['error'] = "Invalid status";
    header("Location: " . ($role === 'rider' ? 'rider_dashboard.php' : 'admin_dashboard.php'));
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Update booking status
    $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $booking_id]);

    // If status is completed, create payment record
    if ($new_status === 'completed') {
        $stmt = $pdo->prepare("INSERT INTO payments (booking_id, amount, status, created_at) VALUES (?, 60, 'pending', NOW())");
        $stmt->execute([$booking_id]);
    }

    $pdo->commit();
    $_SESSION['success'] = "Booking status updated successfully";
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Error updating booking status: " . $e->getMessage();
}

// Redirect based on role
header("Location: " . ($role === 'rider' ? 'rider_dashboard.php' : 'admin_dashboard.php'));
exit(); 