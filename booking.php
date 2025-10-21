<?php 
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit();
}
include 'connect.php';

$customer_id = $_SESSION['user_id']; 

// Fetch the user's name from the database
$sql_user = "SELECT username FROM account WHERE user_id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $customer_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

if ($result_user->num_rows > 0) {
    $user = $result_user->fetch_assoc();
    $username = $user['username']; // Assign username from the database
} else {
    echo "User not found.";
    exit();
}

// Handle form submission for ride booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book'])) {
    $pickup_location = $_POST['pickup_location'];
    $destination = $_POST['destination'];
    $landmark = $_POST['landmark'];

    // Set timezone to Philippines and get the current date and time
    date_default_timezone_set('Asia/Manila');
    $ride_date = date("Y-m-d H:i:s"); // Current time in MySQL format

    // Validate input fields
    if (empty($pickup_location) || empty($destination) || empty($landmark)) {
        echo "<script>alert('Please provide all necessary booking details.'); window.history.back();</script>";
        exit();
    }

    try {
        // First get the customer_id and name from the customers table
        $customer_query = "SELECT c.customer_id, c.name FROM customers c WHERE c.user_id = ?";
        $customer_stmt = $conn->prepare($customer_query);
        $customer_stmt->bind_param("i", $customer_id);
        $customer_stmt->execute();
        $customer_result = $customer_stmt->get_result();
        
        if ($customer_result->num_rows > 0) {
            $customer_data = $customer_result->fetch_assoc();
            $customer_id = $customer_data['customer_id'];
            $customer_name = $customer_data['name'];
            
            // Now insert the booking with the correct customer_id and name
            $sql = "INSERT INTO bookings (customer_id, customer_name, pickup_location, destination, landmark, ride_date, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $status = 'Available';
            if ($stmt) {
                $stmt->bind_param("issssss", $customer_id, $customer_name, $pickup_location, $destination, $landmark, $ride_date, $status);
                
                if ($stmt->execute()) {
                    echo "<script>alert('Booking successful!'); window.location.href='customers_home.php';</script>";
                } else {
                    error_log("Booking insertion failed: " . $stmt->error);
                    echo "<script>alert('Booking failed. Please try again.'); window.history.back();</script>";
                }
                $stmt->close();
            } else {
                error_log("Error preparing booking statement: " . $conn->error);
                echo "<script>alert('Error preparing booking. Please try again.'); window.history.back();</script>";
            }
        } else {
            error_log("Customer not found for user_id: " . $customer_id);
            echo "<script>alert('Customer profile not found. Please contact support.'); window.history.back();</script>";
        }
        $customer_stmt->close();
    } catch (Exception $e) {
        error_log("Booking exception: " . $e->getMessage());
        echo "<script>alert('An error occurred. Please try again.'); window.history.back();</script>";
    }
}

$user_id = $_SESSION['user_id'];
$query = "SELECT name, profile_pic FROM customers WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();
$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pa-Sugat</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <style type="text/tailwindcss">
        @layer components {
            .dark-theme {
                @apply bg-gray-900 text-gray-100;
            }
            .dark-nav {
                @apply bg-gray-800 text-gray-100;
            }
            .dark-card {
                @apply bg-gray-700 text-gray-100;
            }
            .dark-hover {
                @apply hover:bg-gray-600;
            }
            .dark-text-secondary {
                @apply text-gray-400;
            }
            .dark-border {
                @apply border-gray-600;
            }
            .sidebar {
                width: 280px;
            }
            .main-content {
                margin-left: 280px;
            }
            .ride-option {
                @apply dark-card p-4 rounded-lg mb-3 cursor-pointer hover:ring-2 hover:ring-blue-500;
            }
        }
    </style>
</head>
<body class="dark-theme">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-gray-800 p-5 fixed h-full">
            <div class="mb-10">
                <img src="logo.png" alt="Logo" class="h-12 w-12 rounded-full mb-2">
                <h1 class="text-xl font-bold">Pa-Sugat</h1>
            </div>

             <div class="mb-10 flex items-center space-x-3">
                <img src="<?php echo !empty($customer['profile_pic']) ? 'profile_pics/' . htmlspecialchars($customer['profile_pic']) : 'https://ui-avatars.com/api/?name=' . urlencode($customer['name']); ?>"
                     alt="Profile Picture" class="w-10 h-10 rounded-full object-cover border-2 border-gray-600">
                <div>
                    <p class="text-lg font-semibold"><?php echo htmlspecialchars($customer['name']); ?></p>
                    <p class="text-sm text-gray-400">Role: Customer</p>
                </div>
            </div>

            <!-- <div class="mb-10">
                <p class="text-lg font-semibold"><?php echo htmlspecialchars($username); ?></p>
                <p class="text-sm text-gray-400">Role: Customer</p>
                <p class="text-sm text-yellow-400">Rating: 4.9 ★</p>
            </div> -->

            <nav class="space-y-2">
                <a href="customers_home.php" class="flex items-center px-3 py-2 hover:bg-gray-700 rounded"><i data-feather="home" class="w-4 h-4 mr-2"></i> Home</a>
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
        <div class="main-content flex-grow p-6">
            <!-- Ride Booking Card -->
            <div class="dark-card rounded-lg shadow p-6 mb-6">
                <h2 class="text-xl font-bold mb-4">Book a Motorcycle Ride</h2>
                        
                <!-- Ride Bookings Form -->
                <form action="booking.php" method="POST" class="space-y-4">
                    <div class="flex flex-col space-y-2">
                        <label for="pickup_location" class="text-white">Pickup Location:</label>
                        <input type="text" name="pickup_location" class="p-2 rounded text-black" required>
                    </div>

                    <div class="flex flex-col space-y-2">
                        <label for="destination" class="text-white">Destination:</label>
                        <input type="text" name="destination" class="p-2 rounded text-black" required>
                    </div>

                    <div class="flex flex-col space-y-2">
                        <label for="landmark" class="text-white">Landmark:</label>
                        <input type="text" name="landmark" class="p-2 rounded text-black" required>
                    </div>

                    <button type="submit" name="book" class="bg-green-600 text-white p-2 rounded">Book Ride</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        feather.replace();
    </script>
</body>
</html>
