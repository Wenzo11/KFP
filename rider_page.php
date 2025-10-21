<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'rider') {
    header("Location: login.php");
    exit();
}
include 'connect.php';

$rider_id = $_SESSION['user_id'];

// Fetch the correct rider_id from the riders table using the session's user_id
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT rider_id, name, status, profile_pic FROM riders WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($rider_id, $rider_name, $current_status, $profile_pic);
$stmt->fetch();
$stmt->close();

// Set username and role
$username = isset($rider_name) ? htmlspecialchars($rider_name) : 'Unknown Rider';
$role = 'rider';
$current_status = $current_status ?? 'inactive';

// Get today's date
$today = date('Y-m-d');

// Fetch assigned rides for this rider using the correct rider_id
$query = "SELECT b.*, c.name as customer_name, c.contact as customer_contact,
          p.amount, p.payment_method, p.payment_status
          FROM bookings b
          LEFT JOIN customers c ON b.customer_id = c.customer_id
          LEFT JOIN payments p ON b.booking_id = p.booking_id
          WHERE b.rider_id = ? 
          AND b.status IN ('Assigned', 'In Progress', 'Completed')
          ORDER BY 
            CASE 
                WHEN b.status = 'Assigned' THEN 1
                WHEN b.status = 'In Progress' THEN 2
                WHEN b.status = 'Completed' THEN 3
            END,
            b.ride_date ASC";

$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $rider_id);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
}

$result = $stmt->get_result();
$assigned_rides = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle status update
if (isset($_POST['update_status']) && isset($_POST['booking_id'])) {
    $booking_id = $_POST['booking_id'];
    $new_status = $_POST['update_status'];
    
    // Only allow valid status transitions
    if ($new_status === 'In Progress' || $new_status === 'Completed') {
        // Debug: fetch current status from DB before update
        $debug_stmt = $conn->prepare("SELECT status, rider_id FROM bookings WHERE booking_id = ?");
        $debug_stmt->bind_param("i", $booking_id);
        $debug_stmt->execute();
        $debug_stmt->bind_result($current_status_db, $rider_id_db);
        $debug_stmt->fetch();
        $debug_stmt->close();
        // Store debug info in session
        $_SESSION['debug_status_update'] = [
            'booking_id' => $booking_id,
            'rider_id' => $rider_id,
            'new_status' => $new_status,
            'current_status_db' => $current_status_db,
            'rider_id_db' => $rider_id_db
        ];
        $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE booking_id = ? AND rider_id = ?");
        $stmt->bind_param("sii", $new_status, $booking_id, $rider_id);
        $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        // If ride is completed, update rider status to inactive
        if ($new_status === 'Completed') {
            $stmt = $conn->prepare("UPDATE riders SET status = 'inactive' WHERE rider_id = ?");
            $stmt->bind_param("i", $rider_id);
            $stmt->execute();
            $stmt->close();
        }
        
        if ($affected_rows === 0) {
            header("Location: rider_page.php?error=no_update&booking_id=$booking_id&rider_id=$rider_id");
            exit();
        }
        
        header("Location: rider_page.php?debug=1");
        exit();
    }
}

// Handle payment recording
if (isset($_POST['record_payment'])) {
    $booking_id = $_POST['booking_id'];
    $amount = $_POST['amount'];
    $payment_method = $_POST['payment_method'];
    $payment_status = 'Completed';
    $payment_date = date('Y-m-d H:i:s');

    // Insert payment record
    $stmt = $conn->prepare("INSERT INTO payments (booking_id, amount, payment_method, payment_date, payment_status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("idsss", $booking_id, $amount, $payment_method, $payment_date, $payment_status);
    if ($stmt->execute()) {
        header("Location: rider_page.php?payment_success=1");
        exit();
    } else {
        error_log("Payment insert failed: " . $stmt->error);
    }
    $stmt->close();
}

// Add this after the existing session and connection code
if (isset($_POST['update_rider_status'])) {
    $new_status = $_POST['status'];
    $rider_id = $_SESSION['user_id'];
    
    $update_query = "UPDATE riders SET status = ? WHERE user_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("si", $new_status, $rider_id);
    
    if ($stmt->execute()) {
        header("Location: rider_page.php?status_updated=1");
        exit();
    } else {
        error_log("Status update failed: " . $stmt->error);
    }
}

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

            <div class="mb-10 flex items-center space-x-3">
                <img src="<?php echo !empty($profile_pic) ? 'profile_pics/' . htmlspecialchars($profile_pic) : 'https://ui-avatars.com/api/?name=' . urlencode($rider_name); ?>"
                     alt="Profile Picture" class="w-10 h-10 rounded-full object-cover border-2 border-gray-600">
                <div>
                    <p class="text-lg font-semibold"><?php echo htmlspecialchars($rider_name); ?></p>
                    <p class="text-sm text-gray-400">Role: <?php echo htmlspecialchars($role); ?></p>
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
            <h2 class="text-2xl font-bold mb-4">Welcome back, <?php echo htmlspecialchars($username); ?>!</h2>

            <?php if (isset($_GET['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    Ride status updated successfully!
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['ride_accepted'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    Ride accepted successfully! You can now start the ride when you're ready.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php
                    $error = $_GET['error'];
                    switch($error) {
                        case 'update_failed':
                            echo "Failed to update ride status. Please try again.";
                            break;
                        case 'invalid_status':
                            echo "Invalid status update requested.";
                            break;
                        case 'invalid_amount':
                            echo "Please enter a valid payment amount.";
                            break;
                        case 'invalid_payment_method':
                            echo "Please select a valid payment method.";
                            break;
                        case 'payment_exists':
                            echo "Payment has already been recorded for this ride.";
                            break;
                        case 'payment_failed':
                            echo "Failed to record payment. Please try again.";
                            break;
                        case 'no_update':
                            echo "No ride was updated. This may mean the ride is not assigned to you or the status is already set. Booking ID: " . htmlspecialchars($_GET['booking_id']) . ", Rider ID: " . htmlspecialchars($_GET['rider_id']);
                            break;
                        default:
                            echo "An error occurred. Please try again.";
                    }
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['debug_status_update'])): $dbg = $_SESSION['debug_status_update']; ?>
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded mb-4">
                    <strong>Debug Info:</strong><br>
                    Booking ID: <?= htmlspecialchars($dbg['booking_id']) ?><br>
                    Rider ID (session): <?= htmlspecialchars($dbg['rider_id']) ?><br>
                    Rider ID (in DB): <?= htmlspecialchars($dbg['rider_id_db']) ?><br>
                    Status in DB before update: <?= htmlspecialchars($dbg['current_status_db']) ?><br>
                    Status attempted: <?= htmlspecialchars($dbg['new_status']) ?><br>
                </div>
                <?php unset($_SESSION['debug_status_update']); endif; ?>

            <div class="mb-6">
                <div class="bg-gray-800 rounded-lg p-6 shadow">
                    <h3 class="text-lg font-semibold mb-4">Your Status</h3>
                    <form method="POST" class="flex items-center space-x-4">
                        <select name="status" class="bg-gray-700 border border-gray-600 text-white px-4 py-2 rounded">
                            <option value="Available" <?php echo $current_status === 'Available' ? 'selected' : ''; ?>>Available</option>
                            <option value="On Duty" <?php echo $current_status === 'On Duty' ? 'selected' : ''; ?>>On Duty</option>
                            <option value="Offline" <?php echo $current_status === 'Offline' ? 'selected' : ''; ?>>Offline</option>
                        </select>
                        <button type="submit" name="update_rider_status" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition">
                            Update Status
                        </button>
                    </form>
                    <?php if (isset($_GET['status_updated'])): ?>
                        <div class="mt-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                            Status updated successfully!
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6">
                <!-- Assigned Rides -->
                <div class="bg-gray-800 rounded-lg p-6 shadow">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Your Rides</h3>
                        <a href="pending_rides.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded transition">
                            View Available Rides
                        </a>
                    </div>

                    <?php if (empty($assigned_rides)): ?>
                        <p class="text-sm text-gray-400">No rides assigned to you yet.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($assigned_rides as $ride): ?>
                                <div class="bg-gray-700 p-4 rounded-lg">
                                    <div class="flex justify-between items-start mb-4">
                                        <div>
                                            <span class="px-3 py-1 rounded-full text-sm font-medium
                                                <?php
                                                switch($ride['status']) {
                                                    case 'Assigned':
                                                        echo 'bg-yellow-500 text-black';
                                                        break;
                                                    case 'In Progress':
                                                        echo 'bg-blue-500 text-white';
                                                        break;
                                                    case 'Completed':
                                                        echo 'bg-green-500 text-white';
                                                        break;
                                                    default:
                                                        echo 'bg-gray-500 text-white';
                                                }
                                                ?>">
                                                <?= htmlspecialchars($ride['status']) ?>
                                            </span>
                                            <p class="text-sm text-gray-400 mt-1">Booking ID: <?= htmlspecialchars($ride['booking_id']) ?></p>
                                        </div>
                                        <p class="text-sm text-gray-400">Date: <?= htmlspecialchars($ride['ride_date']) ?></p>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <h4 class="font-semibold text-yellow-400 mb-2">Customer Details</h4>
                                            <p>Name: <?= htmlspecialchars($ride['customer_name'] ?? 'Unknown') ?></p>
                                            <?php if (!empty($ride['customer_contact'])): ?>
                                                <p>Contact: <?= htmlspecialchars($ride['customer_contact']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-yellow-400 mb-2">Ride Details</h4>
                                            <p>Pickup: <?= htmlspecialchars($ride['pickup_location']) ?></p>
                                            <p>Destination: <?= htmlspecialchars($ride['destination']) ?></p>
                                            <?php if (!empty($ride['landmark'])): ?>
                                                <p>Landmark: <?= htmlspecialchars($ride['landmark']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <?php if ($ride['status'] === 'Assigned'): ?>
                                            <form method="post" class="flex space-x-2">
                                                <input type="hidden" name="booking_id" value="<?= $ride['booking_id'] ?>">
                                                <button type="submit" name="update_status" value="In Progress" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition">
                                                    Start Ride
                                                </button>
                                            </form>
                                        <?php elseif ($ride['status'] === 'In Progress'): ?>
                                            <form method="post" class="flex space-x-2">
                                                <input type="hidden" name="booking_id" value="<?= $ride['booking_id'] ?>">
                                                <button type="submit" name="update_status" value="Completed" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded transition">
                                                    Complete Ride
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($ride['status'] === 'Completed' && empty($ride['payment_status'])): ?>
                                            <form method="post" class="flex flex-col md:flex-row md:items-center space-y-2 md:space-y-0 md:space-x-2">
                                                <input type="hidden" name="booking_id" value="<?= $ride['booking_id'] ?>">
                                                <input type="number" name="amount" step="0.01" min="0" placeholder="Amount" required class="p-2 rounded text-black">
                                                <select name="payment_method" required class="p-2 rounded text-black">
                                                    <option value="">Select Method</option>
                                                    <option value="GCash">GCash</option>
                                                    <option value="Cash">Cash</option>
                                                </select>
                                                <button type="submit" name="record_payment" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded transition">
                                                    Record Payment
                                                </button>
                                            </form>
                                        <?php elseif ($ride['status'] === 'Completed' && !empty($ride['payment_status'])): ?>
                                            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded">
                                                Payment Recorded: <?= htmlspecialchars($ride['payment_method']) ?> - ₱<?= number_format($ride['amount'], 2) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        feather.replace();
        
        function toggleDropdown() {
            const dropdown = document.getElementById('statusDropdown');
            dropdown.classList.toggle('hidden');
        }

        // Close dropdown when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('button')) {
                const dropdown = document.getElementById('statusDropdown');
                if (!dropdown.classList.contains('hidden')) {
                    dropdown.classList.add('hidden');
                }
            }
        }
    </script>
</body>
</html>
