<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['booking_id'])) {
    header("Location: adminhome.php?edit=invalid");
    exit();
}

$booking_id = $_GET['booking_id'];

// Fetch booking details
$stmt = $conn->prepare("SELECT * FROM bookings WHERE booking_id = ?");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();
$stmt->close();

if (!$booking) {
    echo "Booking not found.";
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pickup_location = $_POST['pickup_location'];
    $destination = $_POST['destination'];
    $landmark = $_POST['landmark'];
    $ride_date = $_POST['ride_date'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE bookings SET pickup_location = ?, destination = ?, landmark = ?, ride_date = ?, status = ? WHERE booking_id = ?");
    $stmt->bind_param("sssssi", $pickup_location, $destination, $landmark, $ride_date, $status, $booking_id);
    if ($stmt->execute()) {
        header("Location: adminhome.php?edit=success");
        exit();
    } else {
        echo "Error updating booking.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Booking</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="flex items-center justify-center min-h-screen">
        <form method="POST" class="bg-gray-800 p-8 rounded shadow-lg w-full max-w-md">
            <h2 class="text-2xl font-bold mb-6">Edit Booking</h2>
            <label class="block mb-2">Pickup Location</label>
            <input type="text" name="pickup_location" value="<?php echo htmlspecialchars($booking['pickup_location']); ?>" class="w-full p-2 mb-4 rounded text-black" required>
            <label class="block mb-2">Destination</label>
            <input type="text" name="destination" value="<?php echo htmlspecialchars($booking['destination']); ?>" class="w-full p-2 mb-4 rounded text-black" required>
            <label class="block mb-2">Landmark</label>
            <input type="text" name="landmark" value="<?php echo htmlspecialchars($booking['landmark']); ?>" class="w-full p-2 mb-4 rounded text-black">
            <label class="block mb-2">Ride Date</label>
            <input type="datetime-local" name="ride_date" value="<?php echo date('Y-m-d\TH:i', strtotime($booking['ride_date'])); ?>" class="w-full p-2 mb-4 rounded text-black" required>
            <label class="block mb-2">Status</label>
            <select name="status" class="w-full p-2 mb-4 rounded text-black" required>
                <option value="Pending" <?php if ($booking['status'] == 'Pending') echo 'selected'; ?>>Pending</option>
                <option value="Assigned" <?php if ($booking['status'] == 'Assigned') echo 'selected'; ?>>Assigned</option>
                <option value="In Progress" <?php if ($booking['status'] == 'In Progress') echo 'selected'; ?>>In Progress</option>
                <option value="Completed" <?php if ($booking['status'] == 'Completed') echo 'selected'; ?>>Completed</option>
            </select>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded w-full">Save Changes</button>
        </form>
    </div>
</body>
</html> 