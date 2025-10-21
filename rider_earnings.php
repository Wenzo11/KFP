<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'rider') {
    header("Location: login.php");
    exit();
}
include 'connect.php';

// Get the actual rider ID from the riders table
$user_id = $_SESSION['user_id'];
$rider_query = "SELECT rider_id FROM riders WHERE user_id = ?";
$rider_stmt = $conn->prepare($rider_query);
$rider_stmt->bind_param("i", $user_id);
$rider_stmt->execute();
$rider_result = $rider_stmt->get_result();
$rider_data = $rider_result->fetch_assoc();
$rider_id = $rider_data['rider_id'];
$rider_stmt->close();

// First, let's check if there are any bookings for this rider at all
$check_query = "SELECT COUNT(*) as total FROM bookings WHERE rider_id = ?";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("i", $rider_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$total_bookings = $check_result->fetch_assoc()['total'];

// Now check bookings by status
$status_query = "SELECT status, COUNT(*) as count FROM bookings WHERE rider_id = ? GROUP BY status";
$status_stmt = $conn->prepare($status_query);
$status_stmt->bind_param("i", $rider_id);
$status_stmt->execute();
$status_result = $status_stmt->get_result();

// Original query with LEFT JOIN to see all bookings
$query = "SELECT b.booking_id, b.pickup_location, b.destination, b.ride_date, b.status as booking_status, 
          c.name as customer_name, c.contact as customer_contact, 
          p.amount, p.payment_method, p.payment_status, p.payment_date 
          FROM bookings b 
          LEFT JOIN customers c ON b.customer_id = c.customer_id 
          LEFT JOIN payments p ON b.booking_id = p.booking_id 
          WHERE b.rider_id = ? 
          ORDER BY b.ride_date DESC";
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Query preparation failed: " . $conn->error);
}

$stmt->bind_param("i", $rider_id);
if (!$stmt->execute()) {
    die("Query execution failed: " . $stmt->error);
}

$result = $stmt->get_result();
if (!$result) {
    die("Getting result failed: " . $stmt->error);
}

$transactions = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();

$user_id = $_SESSION['user_id'];
$query = "SELECT name, profile_pic FROM riders WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$rider = $result->fetch_assoc();
$stmt->close();

// Calculate total earnings
$total_earnings = 0;
foreach ($transactions as $transaction) {
    if ($transaction['booking_status'] === 'Completed') {
        if (in_array(strtolower($transaction['payment_status']), ['completed', 'paid', 'complete', 'payment completed'])) {
            $amount = floatval($transaction['amount']);
            $total_earnings += $amount;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rider Earnings - Pa-Sugat</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-gray-800 p-5 fixed h-full">
            <div class="mb-10 flex items-center space-x-3">
                <img src="<?php echo !empty($rider['profile_pic']) ? 'profile_pics/' . htmlspecialchars($rider['profile_pic']) : 'https://ui-avatars.com/api/?name=' . urlencode($rider['name']); ?>"
                     alt="Profile Picture" class="w-10 h-10 rounded-full object-cover border-2 border-gray-600">
                <div>
                    <p class="text-lg font-semibold"><?php echo htmlspecialchars($rider['name']); ?></p>
                    <p class="text-sm text-gray-400">Role: Rider</p>
                </div>
            </div>
            <nav class="space-y-2">
                <a href="rider_page.php" class="flex items-center px-3 py-2 hover:bg-gray-700 rounded"><i data-feather="home" class="w-4 h-4 mr-2"></i> Home</a>
                <a href="pending_rides.php" class="flex items-center px-3 py-2 hover:bg-gray-700 rounded"><i data-feather="map-pin" class="w-4 h-4 mr-2"></i> Available Rides</a>
                <a href="rider_earnings.php" class="flex items-center px-3 py-2 hover:bg-gray-700 rounded"><i data-feather="dollar-sign" class="w-4 h-4 mr-2"></i> Earnings</a>
                <a href="rider_profile.php" class="flex items-center px-3 py-2 hover:bg-gray-700"><i data-feather="user" class="w-4 h-4 mr-2"></i> Profile</a>
                <a href="#" class="flex items-center px-3 py-2 hover:bg-gray-700 rounded"><i data-feather="settings" class="w-4 h-4 mr-2"></i> Settings</a>
            </nav>
            <a href="logout.php" class="flex items-center text-red-400 hover:text-red-600 mt-10">
                <i data-feather="log-out" class="w-4 h-4 mr-2"></i> Log Out
            </a>
        </div>
        <!-- Main Content -->
        <div class="ml-64 p-8 flex-1">
            <h2 class="text-2xl font-bold mb-4">Completed Transactions</h2>
            <div class="bg-gray-800 rounded-lg p-6 shadow mb-6">
                <h3 class="text-lg font-semibold mb-2">Total Earnings</h3>
                <p class="text-3xl text-green-400 font-bold">₱<?php echo number_format($total_earnings, 2); ?></p>
            </div>
            <div class="bg-gray-800 rounded-lg p-6 shadow">
                <h3 class="text-lg font-semibold mb-3">Transactions</h3>
                <?php if (empty($transactions)): ?>
                    <p class="text-sm text-gray-400">No completed transactions found.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left mt-4">
                            <thead class="bg-gray-700 text-gray-300">
                                <tr>
                                    <th class="px-2 py-1">Date</th>
                                    <th class="px-2 py-1">Customer</th>
                                    <th class="px-2 py-1">Pickup</th>
                                    <th class="px-2 py-1">Destination</th>
                                    <th class="px-2 py-1">Amount</th>
                                    <th class="px-2 py-1">Payment Status</th>
                                    <th class="px-2 py-1">Payment Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td class="px-2 py-1"><?php echo htmlspecialchars($transaction['ride_date']); ?></td>
                                        <td class="px-2 py-1"><?php echo htmlspecialchars($transaction['customer_name']); ?><br><span class="text-xs text-gray-400"><?php echo htmlspecialchars($transaction['customer_contact']); ?></span></td>
                                        <td class="px-2 py-1"><?php echo htmlspecialchars($transaction['pickup_location']); ?></td>
                                        <td class="px-2 py-1"><?php echo htmlspecialchars($transaction['destination']); ?></td>
                                        <td class="px-2 py-1">₱<?php echo number_format($transaction['amount'] ?? 0, 2); ?></td>
                                        <td class="px-2 py-1">
                                            <?php if (in_array(strtolower($transaction['payment_status']), ['completed', 'paid'])): ?>
                                                <span class="bg-green-600 text-white px-2 py-1 rounded">Paid</span>
                                            <?php else: ?>
                                                <span class="bg-yellow-600 text-white px-2 py-1 rounded">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-2 py-1"><?php echo $transaction['payment_date'] ? htmlspecialchars($transaction['payment_date']) : '-'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($transactions)): ?>
                <div class="mt-8">
                    <h3 class="text-lg font-semibold mb-3">Quick Ride Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($transactions as $transaction): ?>
                            <div class="bg-gray-700 p-4 rounded-lg">
                                <div class="mb-2">
                                    <span class="text-xs text-gray-400">Date:</span>
                                    <span class="text-sm text-white"><?php echo htmlspecialchars($transaction['ride_date']); ?></span>
                                </div>
                                <div class="mb-2">
                                    <span class="text-xs text-gray-400">Pickup:</span>
                                    <span class="text-sm text-white"><?php echo htmlspecialchars($transaction['pickup_location']); ?></span>
                                </div>
                                <div class="mb-2">
                                    <span class="text-xs text-gray-400">Destination:</span>
                                    <span class="text-sm text-white"><?php echo htmlspecialchars($transaction['destination']); ?></span>
                                </div>
                                <div class="mb-2">
                                    <span class="text-xs text-gray-400">Amount:</span>
                                    <span class="text-sm text-green-400">₱<?php echo number_format($transaction['amount'] ?? 0, 2); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>feather.replace();</script>
</body>
</html>