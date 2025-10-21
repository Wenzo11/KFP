<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit();
}
require 'connect.php';

$user_id = $_SESSION['user_id'];

// Get customer ID
$customer_query = "SELECT customer_id, name, profile_pic FROM customers WHERE user_id = ?";
$customer_stmt = $conn->prepare($customer_query);
$customer_stmt->bind_param("i", $user_id);
$customer_stmt->execute();
$customer_result = $customer_stmt->get_result();
if ($customer_result->num_rows > 0) {
    $customer_data = $customer_result->fetch_assoc();
    $customer_id = $customer_data['customer_id'];
    $username = $customer_data['name'];
    $customer_stmt->close();
} else {
    $customer_stmt->close();
    die("Customer not found.");
}

// Fetch all rides with payment information
$query = "SELECT b.*, r.name as rider_name, r.contact as rider_contact,
          p.payment_id, p.amount, p.payment_status, p.payment_date, p.payment_method
          FROM bookings b
          LEFT JOIN riders r ON b.rider_id = r.rider_id
          LEFT JOIN payments p ON b.booking_id = p.booking_id
          WHERE b.customer_id = ?
          ORDER BY b.ride_date DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();
$completed_rides = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ride History - Pa-Sugat</title>
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

            <div class="mb-10 flex items-center space-x-3">
                <img src="<?php echo !empty($customer_data['profile_pic']) ? 'profile_pics/' . htmlspecialchars($customer_data['profile_pic']) : 'https://ui-avatars.com/api/?name=' . urlencode($customer_data['name']); ?>"
                     alt="Profile Picture" class="w-10 h-10 rounded-full object-cover border-2 border-gray-600">
                <div>
                    <p class="text-lg font-semibold"><?php echo htmlspecialchars($customer_data['name']); ?></p>
                    <p class="text-sm text-gray-400">Role: Customer</p>
                </div>
            </div>

            <nav class="space-y-2">
                <a href="customers_home.php" class="flex items-center px-3 py-2 hover:bg-gray-700 rounded">
                    <i data-feather="home" class="w-4 h-4 mr-2"></i> Home
                </a>
                <a href="booking.php" class="flex items-center px-3 py-2 hover:bg-gray-700 rounded">
                    <i data-feather="map-pin" class="w-4 h-4 mr-2"></i> Book Ride
                </a>
                <a href="ride_history.php" class="flex items-center px-3 py-2 bg-gray-700 rounded">
                    <i data-feather="clock" class="w-4 h-4 mr-2"></i> Ride History
                </a>
                <a href="profile.php" class="flex items-center px-3 py-2 hover:bg-gray-700 rounded">
                    <i data-feather="user" class="w-4 h-4 mr-2"></i> Profile
                </a>
                <a href="settings.php" class="flex items-center px-3 py-2 hover:bg-gray-700 rounded">
                    <i data-feather="settings" class="w-4 h-4 mr-2"></i> Settings
                </a>
            </nav>

            <a href="logout.php" class="flex items-center text-red-400 hover:text-red-600 mt-10">
                <i data-feather="log-out" class="w-4 h-4 mr-2"></i> Log Out
            </a>
        </div>

        <!-- Main Content -->
        <div class="ml-64 p-8 flex-1">
            <h2 class="text-2xl font-bold mb-4">Ride History</h2>
            <div class="bg-gray-800 rounded-lg p-6 shadow">
                <?php if (empty($completed_rides)): ?>
                    <p class="text-sm text-gray-400">You have no rides yet.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($completed_rides as $ride): ?>
                            <div class="bg-gray-700 p-4 rounded-lg">
                                <div class="mb-2">
                                    <h4 class="font-semibold text-yellow-400">Ride Details</h4>
                                    <div class="grid grid-cols-2 gap-2 mt-2">
                                        <p class="text-sm text-gray-400">From:</p>
                                        <p class="text-sm"><?php echo htmlspecialchars($ride['pickup_location']); ?></p>
                                        <p class="text-sm text-gray-400">To:</p>
                                        <p class="text-sm"><?php echo htmlspecialchars($ride['destination']); ?></p>
                                        <p class="text-sm text-gray-400">Date:</p>
                                        <p class="text-sm"><?php echo htmlspecialchars($ride['ride_date']); ?></p>
                                        <p class="text-sm text-gray-400">Status:</p>
                                        <p class="text-sm <?php 
                                            echo match($ride['status']) {
                                                'Completed' => 'text-green-400',
                                                'In Progress' => 'text-blue-400',
                                                'Cancelled' => 'text-red-400',
                                                default => 'text-yellow-400'
                                            };
                                        ?>"><?php echo htmlspecialchars($ride['status']); ?></p>
                                    </div>
                                </div>
                                <?php if ($ride['status'] === 'Completed'): ?>
                                    <div class="mb-2">
                                        <h4 class="font-semibold text-blue-300">Rider Details</h4>
                                        <div class="grid grid-cols-2 gap-2 mt-2">
                                            <p class="text-sm text-gray-400">Name:</p>
                                            <p class="text-sm"><?php echo htmlspecialchars($ride['rider_name'] ?? 'Unknown'); ?></p>
                                            <p class="text-sm text-gray-400">Contact:</p>
                                            <p class="text-sm"><?php echo htmlspecialchars($ride['rider_contact'] ?? ''); ?></p>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <h4 class="font-semibold text-green-400">Payment Details</h4>
                                        <div class="grid grid-cols-2 gap-2 mt-2">
                                            <p class="text-sm text-gray-400">Amount:</p>
                                            <p class="text-sm">₱<?php echo number_format($ride['amount'] ?? 60, 2); ?></p>
                                            <p class="text-sm text-gray-400">Status:</p>
                                            <p class="text-sm <?php 
                                                echo match($ride['payment_status'] ?? 'Pending') {
                                                    'Completed' => 'text-green-400',
                                                    'Failed' => 'text-red-400',
                                                    default => 'text-yellow-400'
                                                };
                                            ?>"><?php echo htmlspecialchars($ride['payment_status'] ?? 'Pending'); ?></p>
                                            <p class="text-sm text-gray-400">Method:</p>
                                            <p class="text-sm"><?php echo htmlspecialchars($ride['payment_method'] ?? 'Not specified'); ?></p>
                                            <p class="text-sm text-gray-400">Date:</p>
                                            <p class="text-sm"><?php echo htmlspecialchars($ride['payment_date'] ?? 'Not paid'); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>feather.replace();</script>
</body>
</html>