<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'rider') {
    header("Location: login.php");
    exit();
}
include 'connect.php';


$rider_id = $_SESSION['user_id'];

// Handle status update
if (isset($_POST['update_status'])) {
    $new_status = $_POST['update_status'];
    $update_query = "UPDATE riders SET status = ? WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("si", $new_status, $rider_id);
    
    if ($update_stmt->execute()) {
        header("Location: rider_page.php?status_updated=1");
        exit();
    } else {
        error_log("Status update failed: " . $update_stmt->error);
    }
    $update_stmt->close();
}

// Fetch rider information
$rider_query = "SELECT name, status FROM riders WHERE user_id = ?";
$rider_stmt = $conn->prepare($rider_query);
if (!$rider_stmt) {
    error_log("Prepare failed: " . $conn->error);
    die("Prepare failed: " . $conn->error);
}

$rider_stmt->bind_param("i", $rider_id);
if (!$rider_stmt->execute()) {
    error_log("Execute failed: " . $rider_stmt->error);
}

$rider_result = $rider_stmt->get_result();
$rider_data = $rider_result->fetch_assoc();
$rider_stmt->close();

// Set username and role
$username = $rider_data['name'] ?? 'Unknown Rider';
$role = 'rider';
$current_status = $rider_data['status'] ?? 'inactive';

// Get today's date
$today = date('Y-m-d');

// Fetch all pending rides (not assigned to any rider)
$query = "SELECT b.*, c.name as customer_name, c.contact as customer_contact
          FROM bookings b
          LEFT JOIN customers c ON b.customer_id = c.customer_id
          WHERE b.status = 'Pending'
          ORDER BY b.ride_date ASC";
$result = $conn->query($query);
$bookings = $result->fetch_all(MYSQLI_ASSOC);


?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rider Home - Pa-Sugat</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-gray-800 p-5 fixed h-full">
            <div class="mb-10">
                <img src="logo.png" alt="Logo" class="h-12 w-12 rounded-full mb-2">
                <h1 class="text-xl font-bold">Pa-Sugat</h1>
            </div>

            <div class="mb-10">
                <div class="flex items-center space-x-3 mb-2">
                    <div class="w-10 h-10 rounded-full bg-gray-500 flex items-center justify-center">
                        <span class="text-xl text-white"><?= strtoupper(substr($username, 0, 1)) ?></span>
                    </div>
                    <div>
                        <p class="text-lg font-semibold"><?php echo htmlspecialchars($username); ?></p>
                        <p class="text-sm text-gray-400">Role: <?php echo htmlspecialchars($role); ?></p>
                    </div>
                </div>
          
            </div>

            <nav class="space-y-2">
                <a href="rider_page.php" class="flex items-center px-3 py-2 hover:bg-gray-700 rounded"><i data-feather="home" class="w-4 h-4 mr-2"></i> Home</a>
                <a href="pending_rides.php" class="flex items-center px-3 py-2 hover:bg-gray-700 rounded"><i data-feather="map-pin" class="w-4 h-4 mr-2"></i> Available Rides</a>
                <a href="rider_earnings.php" class="flex items-center px-3 py-2 hover:bg-gray-700 rounded"><i data-feather="dollar-sign" class="w-4 h-4 mr-2"></i> Earnings</a>
                <a href="rider_profile.php" class="flex items-center px-3 py-2"><i data-feather="user" class="w-4 h-4 mr-2"></i> Profile</a>
                <a href="#" class="flex items-center px-3 py-2 hover:bg-gray-700 rounded"><i data-feather="settings" class="w-4 h-4 mr-2"></i> Settings</a>
            </nav>
            <a href="logout.php" class="flex items-center text-red-400 hover:text-red-600 mt-10">
                <i data-feather="log-out" class="w-4 h-4 mr-2"></i> Log Out
            </a>
        </div>

        <!-- Main Content -->
        <div class="ml-64 p-8 flex-1">
            <h2 class="text-2xl font-bold mb-6">Pending Bookings</h2>

            <?php if (empty($bookings)): ?>
                <div class="bg-gray-800 p-6 rounded shadow">
                    <p class="text-gray-400">No pending bookings available right now.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($bookings as $booking): ?>
                        <div class="bg-gray-800 p-6 rounded-lg shadow hover:ring-2 hover:ring-green-500 transition">
                            <p><span class="font-semibold text-yellow-400">Customer:</span> <?= htmlspecialchars($booking['customer_name'] ?? 'Unknown') ?></p>
                            <p><span class="font-semibold text-yellow-400">Date:</span> <?= htmlspecialchars($booking['ride_date']) ?></p>
                            <p><span class="font-semibold text-yellow-400">Pickup:</span> <?= htmlspecialchars($booking['pickup_location']) ?></p>
                            <p><span class="font-semibold text-yellow-400">Destination:</span> <?= htmlspecialchars($booking['destination']) ?></p>
                            <p><span class="font-semibold text-yellow-400">Status:</span> <?= htmlspecialchars($booking['status']) ?></p>

                            <form method="POST" action="accept_ride.php" class="mt-4">
                            <input type="hidden" name="booking_id" value="<?= htmlspecialchars($booking['booking_id']) ?>">
                                <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">
                                    Accept Ride
                                </button>
                            </form>
                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <script>
                        feather.replace();
                    </script>
                </body>
                </html>