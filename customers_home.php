<?php 
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit();
}
require 'connect.php';

// Get customer ID from customers table
$user_id = $_SESSION['user_id'];
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

}

// Initialize variables
$error = '';
$success = '';

// Fetch customer's rides including completed ones
$query = "SELECT b.*, r.name as rider_name, r.contact as rider_contact
          FROM bookings b
          LEFT JOIN riders r ON b.rider_id = r.rider_id
          WHERE b.customer_id = ? 
          AND b.status NOT IN ('Completed', 'Cancelled')
          ORDER BY b.ride_date DESC";

$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $customer_id);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
}

$result = $stmt->get_result();
$rides = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Debug log
error_log("Customer ID: " . $customer_id);
error_log("Number of rides found: " . count($rides));
error_log("Rides data: " . print_r($rides, true));

// Fetch active riders
$query = "SELECT r.name, r.contact, r.status 
          FROM riders r 
          WHERE LOWER(r.status) = 'active' 
          LIMIT 5";

$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die("Prepare failed: " . $conn->error);
}

if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
}

$result = $stmt->get_result();
$active_riders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch assigned rides for this customer
$query = "SELECT b.*, c.name as customer_name, c.contact as customer_contact
          FROM bookings b
          LEFT JOIN customers c ON b.customer_id = c.customer_id
          WHERE b.customer_id = ? 
          AND b.status IN ('Assigned', 'In Progress', 'Available')
          ORDER BY b.ride_date ASC";

$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $customer_id);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
}

$result = $stmt->get_result();
$assigned_rides = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle booking form submission
if (isset($_POST['book'])) {
    $pickup_location = $_POST['pickup_location'] ?? '';
    $destination = $_POST['destination'] ?? '';
    $landmark = $_POST['landmark'] ?? '';

    if (empty($pickup_location) || empty($destination)) {
        $error = 'Pickup and destination are required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO bookings (customer_id, customer_name, pickup_location, destination, landmark, ride_date, status) 
                               VALUES (?, ?, ?, ?, NOW(), 'Available')");
        $stmt->bind_param("isss", $customer_id, $username, $pickup_location, $destination, $landmark);
        
        if ($stmt->execute()) {
            $success = 'Ride booked successfully!';
            header("Location: customers_home.php?success=1");
            exit();
        } else {
            $error = 'Failed to book ride. Please try again.';
            error_log("Booking error: " . $stmt->error);
        }
        $stmt->close();
    }
}

// Fetch upcoming rides
if (isset($conn)) {
    try {
        // Fetch upcoming rides for the customer
        $query = "SELECT b.*, 
                 DATE_FORMAT(b.ride_date, '%M %d, %Y %h:%i %p') as formatted_date,
                 r.name as rider_name,
                 r.contact as rider_contact
                 FROM bookings b 
                 LEFT JOIN riders r ON b.rider_id = r.rider_id
                 WHERE b.customer_id = ? 
                 AND b.ride_date >= NOW() 
                 AND b.status NOT IN ('Completed', 'Cancelled')
                 ORDER BY b.ride_date ASC";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            die("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $customer_id);
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $upcoming_rides = $result->fetch_all(MYSQLI_ASSOC);
        
        // Debug log
        error_log("Upcoming rides for customer $customer_id: " . print_r($upcoming_rides, true));
        
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching upcoming rides: " . $e->getMessage());
    }
} else {
    error_log("Database connection is not set.");
}

// Fetch active riders from the database
if (isset($conn)) {
    try {
        $query = "SELECT r.name, r.contact, r.status 
                 FROM riders r 
                 WHERE LOWER(r.status) = 'active' 
                 LIMIT 5";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            die("Prepare failed: " . $conn->error);
        }
        
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $nearby_riders = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching active riders: " . $e->getMessage());
        $nearby_riders = [];
    }
} else {
    error_log("Database connection is not set.");
    $nearby_riders = [];
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Home - Pa-Sugat</title>
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
                <a href="customers_home.php" class="flex items-center px-3 py-2 bg-gray-700 rounded"><i data-feather="home" class="w-4 h-4 mr-2"></i> Home</a>
                <a href="booking.php" class="flex items-center px-3 py-2 hover:bg-gray-700 rounded"><i data-feather="map-pin" class="w-4 h-4 mr-2"></i> Book Ride</a>
                <a href="ride_history.php" class="flex items-center px-3 py-2 hover:bg-gray-700 rounded"><i data-feather="clock" class="w-4 h-4 mr-2"></i> Ride History</a>
                <a href="profile.php" class="flex items-center px-3 py-2 hover:bg-gray-700 rounded"><i data-feather="user" class="w-4 h-4 mr-2"></i> Profile</a>
                <a href="settings.php" class="flex items-center px-3 py-2 hover:bg-gray-700 rounded"><i data-feather="settings" class="w-4 h-4 mr-2"></i> Settings</a>
            </nav>

            <a href="logout.php" class="flex items-center text-red-400 hover:text-red-600 mt-10">
                <i data-feather="log-out" class="w-4 h-4 mr-2"></i> Log Out
            </a>
        </div>

        <!-- Main Content -->
        <div class="ml-64 p-8 flex-1">
            <h2 class="text-2xl font-bold mb-4">Welcome back, <?php echo htmlspecialchars($username); ?>!</h2>

            <!-- Success/Error Messages -->
            <?php if (isset($_GET['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    Ride booked successfully!
                </div>
            <?php elseif (!empty($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Your Rides -->
                <div class="bg-gray-800 rounded-lg p-6 shadow">
                    <h3 class="text-lg font-semibold mb-3">Your Rides</h3>

                    <?php if (empty($rides)): ?>
                        <div class="text-center">
                            <p class="text-sm text-gray-400 mb-4">You haven't booked any rides yet.</p>
                            <a href="booking.php" class="inline-block bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded transition">
                                Book a Ride
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($rides as $ride): ?>
                                <div class="bg-gray-700 p-4 rounded-lg">
                                    <div class="mb-4">
                                        <h4 class="font-semibold text-yellow-400">Ride Details</h4>
                                        <div class="grid grid-cols-2 gap-2 mt-2">
                                            <p class="text-sm text-gray-400">From:</p>
                                            <p class="text-sm"><?php echo htmlspecialchars($ride['pickup_location']); ?></p>
                                            <p class="text-sm text-gray-400">To:</p>
                                            <p class="text-sm"><?php echo htmlspecialchars($ride['destination']); ?></p>
                                            <p class="text-sm text-gray-400">Date:</p>
                                            <p class="text-sm"><?php echo htmlspecialchars($ride['ride_date']); ?></p>
                                            <p class="text-sm text-gray-400">Status:</p>
                                            <p class="text-sm">
                                                <?php 
                                                $statusClass = '';
                                                switch($ride['status']) {
                                                    case 'Available':
                                                        $statusClass = 'text-yellow-400';
                                                        break;
                                                    case 'Assigned':
                                                        $statusClass = 'text-blue-400';
                                                        break;
                                                    case 'In Progress':
                                                        $statusClass = 'text-orange-400';
                                                        break;
                                                    case 'Completed':
                                                        $statusClass = 'text-green-400';
                                                        break;
                                                    default:
                                                        $statusClass = 'text-gray-400';
                                                }
                                                ?>
                                                <span class="<?php echo $statusClass; ?>"><?php echo htmlspecialchars($ride['status']); ?></span>
                                            </p>
                                        </div>
                                    </div>

                                    <?php if ($ride['status'] === 'Assigned' && !empty($ride['rider_name'])): ?>
                                        <div class="mt-4 p-3 bg-blue-900 rounded-lg">
                                            <h5 class="font-semibold text-blue-300 mb-2">Your Rider</h5>
                                            <div class="grid grid-cols-2 gap-2">
                                                <p class="text-sm text-gray-400">Name:</p>
                                                <p class="text-sm text-white"><?php echo htmlspecialchars($ride['rider_name']); ?></p>
                                                <p class="text-sm text-gray-400">Contact:</p>
                                                <p class="text-sm text-white"><?php echo htmlspecialchars($ride['rider_contact']); ?></p>
                                            </div>
                                            <button onclick="showRideDetails(<?php echo htmlspecialchars(json_encode($ride)); ?>)" 
                                                    class="mt-3 w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition">
                                                View Ride Details
                                            </button>
                                        </div>
                                    <?php elseif ($ride['status'] === 'Available'): ?>
                                        <div class="mt-4 p-3 bg-yellow-900 rounded-lg">
                                            <p class="text-sm text-yellow-300">
                                                <i data-feather="clock" class="w-4 h-4 inline mr-1"></i>
                                                Waiting for a rider to be assigned...
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="bg-gray-800 rounded-lg p-6 shadow">
                    <h3 class="text-lg font-semibold mb-3">Quick Actions</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <a href="booking.php" class="bg-gray-700 p-4 rounded-lg hover:bg-gray-600 transition flex flex-col items-center text-center">
                            <i data-feather="map-pin" class="w-8 h-8 text-blue-400 mb-2"></i>
                            <span class="font-medium">Book New Ride</span>
                            <span class="text-sm text-gray-400 mt-1">Schedule a new ride</span>
                        </a>
                        
                        <a href="ride_history.php" class="bg-gray-700 p-4 rounded-lg hover:bg-gray-600 transition flex flex-col items-center text-center">
                            <i data-feather="clock" class="w-8 h-8 text-yellow-400 mb-2"></i>
                            <span class="font-medium">Ride History</span>
                            <span class="text-sm text-gray-400 mt-1">View past rides</span>
                        </a>
                        
                        <a href="profile.php" class="bg-gray-700 p-4 rounded-lg hover:bg-gray-600 transition flex flex-col items-center text-center">
                            <i data-feather="user" class="w-8 h-8 text-green-400 mb-2"></i>
                            <span class="font-medium">My Profile</span>
                            <span class="text-sm text-gray-400 mt-1">Update your details</span>
                        </a>
                        
                        <a href="settings.php" class="bg-gray-700 p-4 rounded-lg hover:bg-gray-600 transition flex flex-col items-center text-center">
                            <i data-feather="settings" class="w-8 h-8 text-purple-400 mb-2"></i>
                            <span class="font-medium">Settings</span>
                            <span class="text-sm text-gray-400 mt-1">Manage preferences</span>
                        </a>
                    </div>

                    <!-- Quick Stats -->
                    <div class="mt-6 pt-6 border-t border-gray-700">
                        <h4 class="text-sm font-medium text-gray-400 mb-3">Your Stats</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-gray-700 p-3 rounded-lg">
                                <p class="text-sm text-gray-400">Total Rides</p>
                                <p class="text-xl font-semibold text-blue-400">
                                    <?php
                                    $total_rides_query = "SELECT COUNT(*) as total FROM bookings WHERE customer_id = ?";
                                    $stmt = $conn->prepare($total_rides_query);
                                    $stmt->bind_param("i", $customer_id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    echo $result->fetch_assoc()['total'];
                                    $stmt->close();
                                    ?>
                                </p>
                            </div>
                            <div class="bg-gray-700 p-3 rounded-lg">
                                <p class="text-sm text-gray-400">Active Rides</p>
                                <p class="text-xl font-semibold text-green-400">
                                    <?php
                                    $active_rides_query = "SELECT COUNT(*) as active FROM bookings WHERE customer_id = ? AND status NOT IN ('Completed', 'Cancelled')";
                                    $stmt = $conn->prepare($active_rides_query);
                                    $stmt->bind_param("i", $customer_id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    echo $result->fetch_assoc()['active'];
                                    $stmt->close();
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ride Details Modal -->
    <div id="rideDetailsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50">
        <div class="bg-gray-800 p-6 rounded-lg w-full max-w-lg">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold">Ride Details</h3>
                <button onclick="toggleModal('rideDetailsModal', false)" class="text-white">X</button>
            </div>
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-400">Booking ID</p>
                        <p class="text-white" id="modal-booking-id"></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-400">Status</p>
                        <p class="text-white" id="modal-status"></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-400">Pickup Location</p>
                        <p class="text-white" id="modal-pickup"></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-400">Destination</p>
                        <p class="text-white" id="modal-destination"></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-400">Ride Date</p>
                        <p class="text-white" id="modal-date"></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-400">Rider Name</p>
                        <p class="text-white" id="modal-rider-name"></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-400">Rider Contact</p>
                        <p class="text-white" id="modal-rider-contact"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showRideDetails(ride) {
            // Populate modal with ride details
            document.getElementById('modal-booking-id').textContent = ride.booking_id;
            document.getElementById('modal-status').textContent = ride.status;
            document.getElementById('modal-pickup').textContent = ride.pickup_location;
            document.getElementById('modal-destination').textContent = ride.destination;
            document.getElementById('modal-date').textContent = ride.ride_date;
            document.getElementById('modal-rider-name').textContent = ride.rider_name;
            document.getElementById('modal-rider-contact').textContent = ride.rider_contact;

            // Show the modal
            toggleModal('rideDetailsModal', true);
        }

        function toggleModal(modalId, isVisible) {
            document.getElementById(modalId).classList.toggle('hidden', !isVisible);
        }
    </script>
    <script>feather.replace();</script>
</body>
</html>
