<?php echo '<div style="background:orange;color:black;padding:10px;z-index:9999;position:relative;">DEBUG: adminhome.php loaded</div>'; ?>
<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
include 'connect.php';

// Strong debug for delete_user_id
if (isset($_GET['delete_user_id'])) {
    echo "<div style='background:yellow;color:black;padding:10px;z-index:9999;position:relative;'>DEBUG: delete_user_id = " . htmlspecialchars($_GET['delete_user_id']) . "</div>";
}
// Fallback: show if delete handler is NOT triggered but page is loaded
if (!isset($_GET['delete_user_id'])) {
    echo "<div style='background:pink;color:black;padding:10px;z-index:9999;position:relative;'>DEBUG: delete_user_id NOT SET in URL</div>";
}

// Handle form submission for creating new user
if (isset($_POST['create_user'])) {
    $name = $_POST['name'];
    $role = $_POST['role'];
    $address = $_POST['address'];
    $contact = $_POST['contact'];
    $email = $_POST['email'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // First insert into account table
    $stmt = $conn->prepare("INSERT INTO account (username, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $password, $role);
    
    if ($stmt->execute()) {
        $user_id = $conn->insert_id;
        
        // Then insert into the appropriate table based on role
        if ($role === 'Customer') {
            $stmt = $conn->prepare("INSERT INTO customers (user_id, name, address, contact, email) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $user_id, $name, $address, $contact, $email);
        } elseif ($role === 'Rider') {
            $stmt = $conn->prepare("INSERT INTO riders (user_id, name, address, contact, email) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $user_id, $name, $address, $contact, $email);
        }
        
        if ($stmt->execute()) {
            echo "<script>alert('User created successfully!'); window.location.href='adminhome.php';</script>";
        } else {
            echo "<script>alert('Error creating user details!');</script>";
        }
    } else {
        echo "<script>alert('Error creating account!');</script>";
    }
}

// Fetch account data
$query = "SELECT * FROM account ORDER BY user_id ASC";
$result = mysqli_query($conn, $query);

$accounts = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $accounts[] = $row;
    }
}



// Fetch recent activities
$activities = [];
$activityQuery = "SELECT activity, timestamp FROM system_activity ORDER BY timestamp DESC LIMIT 5";
if ($activityResult = $conn->query($activityQuery)) {
    while ($row = $activityResult->fetch_assoc()) {
        $activities[] = $row;
    }
}
if (isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
}


// Fetch customers
$customers = [];
$customerQuery = "SELECT * FROM customers ORDER BY customer_id ASC";
if ($customerResult = $conn->query($customerQuery)) {
    while ($row = $customerResult->fetch_assoc()) {
        $customers[] = $row;
    }
}

// Fetch riders data from database
$query = "SELECT * FROM riders";
$result = mysqli_query($conn, $query);

// Initialize $riders as an empty array
$riders = array();

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $riders[] = $row;
    }
}   

// Basic stats
$totalUsers = $conn->query("SELECT COUNT(*) AS total FROM account")->fetch_assoc()['total'];

// Count active bookings (excluding completed ones)
$activeBookings = $conn->query("SELECT COUNT(*) AS total FROM bookings WHERE status != 'Completed'")->fetch_assoc()['total'];

// Count total bookings
$totalBookings = $conn->query("SELECT COUNT(*) AS total FROM bookings")->fetch_assoc()['total'];

// Count bookings by status
$pendingBookings = $conn->query("SELECT COUNT(*) AS total FROM bookings WHERE status = 'Pending'")->fetch_assoc()['total'];
$assignedBookings = $conn->query("SELECT COUNT(*) AS total FROM bookings WHERE status = 'Assigned'")->fetch_assoc()['total'];
$completedBookings = $conn->query("SELECT COUNT(*) AS total FROM bookings WHERE status = 'Completed'")->fetch_assoc()['total'];

// Fetch bookings

$sql = "SELECT b.*, c.name AS customer_name
        FROM bookings b
        LEFT JOIN customers c ON b.customer_id = c.customer_id
        ORDER BY b.booking_id DESC";
$result = mysqli_query($conn, $sql);
$bookings = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $bookings[] = $row;
    }
}

// Handle deletion of account
if (isset($_GET['delete_user_id'])) {
    $userId = $_GET['delete_user_id'];
    
    // Debug output
    error_log("Attempting to delete user ID: " . $userId);

    // Ensure the ID is a valid integer
    if (filter_var($userId, FILTER_VALIDATE_INT)) {
        // Start transaction
        $conn->begin_transaction();

        try {
            // First get the role to determine which table to delete from
            $roleQuery = "SELECT role FROM account WHERE user_id = ?";
            $stmt = $conn->prepare($roleQuery);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("i", $userId);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $account = $result->fetch_assoc();

            if ($account) {
                $role = $account['role'];
                $table = ($role === 'Customer') ? 'customers' : 'riders';
                
                error_log("Deleting from table: " . $table);

                // Get the customer_id or rider_id before deletion
                $idQuery = "SELECT " . ($role === 'Customer' ? 'customer_id' : 'rider_id') . " FROM $table WHERE user_id = ?";
                $stmt = $conn->prepare($idQuery);
                if (!$stmt) {
                    throw new Exception("Prepare failed for ID query: " . $conn->error);
                }
                
                $stmt->bind_param("i", $userId);
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed for ID query: " . $stmt->error);
                }
                
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                
                if ($row) {
                    $id = $row[$role === 'Customer' ? 'customer_id' : 'rider_id'];
                    
                    // Delete related records first
                    if ($role === 'Customer') {
                        // Delete related payments
                        $deletePaymentsQuery = "DELETE FROM payments WHERE booking_id IN (SELECT booking_id FROM bookings WHERE customer_id = ?)";
                        $stmt = $conn->prepare($deletePaymentsQuery);
                        if (!$stmt) {
                            throw new Exception("Prepare failed for payments deletion: " . $conn->error);
                        }
                        $stmt->bind_param("i", $id);
                        if (!$stmt->execute()) {
                            throw new Exception("Execute failed for payments deletion: " . $stmt->error);
                        }

                        // Delete related bookings
                        $deleteBookingsQuery = "DELETE FROM bookings WHERE customer_id = ?";
                        $stmt = $conn->prepare($deleteBookingsQuery);
                        if (!$stmt) {
                            throw new Exception("Prepare failed for bookings deletion: " . $conn->error);
                        }
                        $stmt->bind_param("i", $id);
                        if (!$stmt->execute()) {
                            throw new Exception("Execute failed for bookings deletion: " . $stmt->error);
                        }
                    } else {
                        // For riders, update bookings to remove rider assignment
                        $updateBookingsQuery = "UPDATE bookings SET rider_id = NULL WHERE rider_id = ?";
                        $stmt = $conn->prepare($updateBookingsQuery);
                        if (!$stmt) {
                            throw new Exception("Prepare failed for bookings update: " . $conn->error);
                        }
                        $stmt->bind_param("i", $id);
                        if (!$stmt->execute()) {
                            throw new Exception("Execute failed for bookings update: " . $stmt->error);
                        }
                    }

                    // Delete from the role-specific table
                    $deleteRoleQuery = "DELETE FROM $table WHERE user_id = ?";
                    $stmt = $conn->prepare($deleteRoleQuery);
                    if (!$stmt) {
                        throw new Exception("Prepare failed for role table: " . $conn->error);
                    }
                    
                    $stmt->bind_param("i", $userId);
                    if (!$stmt->execute()) {
                        throw new Exception("Execute failed for role table: " . $stmt->error);
                    }

                    // Always attempt to delete the account record, even if not found in role-specific table
                    $deleteAccountQuery = "DELETE FROM account WHERE user_id = ?";
                    $stmt = $conn->prepare($deleteAccountQuery);
                    if (!$stmt) {
                        throw new Exception("Prepare failed for account table: " . $conn->error);
                    }
                    
                    $stmt->bind_param("i", $userId);
                    if (!$stmt->execute()) {
                        throw new Exception("Execute failed for account table: " . $stmt->error);
                    }

                    $conn->commit();
                    $_SESSION['success'] = "Account and all related records deleted successfully";
                    error_log("Account deleted successfully");
                }
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error deleting account: " . $e->getMessage();
            error_log("Error deleting account: " . $e->getMessage());
        }
    } else {
        $_SESSION['error'] = "Invalid user ID";
        error_log("Invalid user ID: " . $userId);
    }

    // Redirect back to adminhome.php
    header("Location: adminhome.php");
    exit();
}

// Fetch account data
$query = "SELECT * FROM account ORDER BY user_id ASC";
$result = mysqli_query($conn, $query);

$accounts = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $accounts[] = $row;
    }
}

// Fetch account data
$query = "SELECT * FROM account ORDER BY user_id ASC";
$result = mysqli_query($conn, $query);

$accounts = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $accounts[] = $row;
    }
}

// Handle rider status update
if (isset($_POST['update_rider_status'])) {
    $rider_id = $_POST['rider_id'];
    $new_status = $_POST['status'];
    
    $update_query = "UPDATE riders SET status = ? WHERE rider_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("si", $new_status, $rider_id);
    
    if ($stmt->execute()) {
        echo "<script>alert('Rider status updated successfully!'); window.location.href='adminhome.php';</script>";
    } else {
        echo "<script>alert('Error updating rider status!');</script>";
    }
}

// Handle account update
if (isset($_POST['update_user'])) {
    $user_id = $_POST['user_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $contact = $_POST['contact'];
    $address = $_POST['address'];
    $username = $_POST['username'];
    $role = $_POST['role'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update account table
        $stmt = $conn->prepare("UPDATE account SET username = ?, role = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $username, $role, $user_id);
        $stmt->execute();

        // Get the appropriate table based on role
        $table = ($role === 'Customer') ? 'customers' : 'riders';

        // Update user details table
        $stmt = $conn->prepare("UPDATE $table SET name = ?, email = ?, contact = ?, address = ? WHERE user_id = ?");
        $stmt->bind_param("ssssi", $name, $email, $contact, $address, $user_id);
        $stmt->execute();

        $conn->commit();
        $_SESSION['success'] = "Account updated successfully";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error updating account: " . $e->getMessage();
    }

    header("Location: adminhome.php");
    exit();
}

?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white">
    <!-- Navbar -->
    <nav class="bg-gray-800 p-4 sticky top-0 z-50 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <div class="text-xl font-bold text-white">Admin Panel</div>
            <ul class="flex space-x-6 text-gray-300">
                <li><a href="adminhome.php" class="hover:text-white">Dashboard</a></li> <!-- Home Link -->

                <li><a href="active_users.php" class="hover:text-white">Users</a></li>
              
                <li>
                    <form method="POST" class="inline">
                      <a href="logout.php" class="text-red-500 hover:text-red-600">Logout</a>
                    </form>
                </li>
            </ul>
        </div>
    </nav>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-500 text-white p-4 mb-4">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-500 text-white p-4 mb-4">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>

    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold mb-6">Welcome to the Admin Homepage</h1>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Active Bookings Card -->
            <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold mb-2">Active Bookings</h3>
                <p class="text-3xl font-bold text-blue-400"><?php echo $activeBookings; ?></p>
                <p class="text-sm text-gray-400 mt-2">Current active bookings</p>
            </div>

            <!-- Total Users Card -->
            <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold mb-2">Total Users</h3>
                <p class="text-3xl font-bold text-green-400"><?php echo $totalUsers; ?></p>
                <p class="text-sm text-gray-400 mt-2">Registered users</p>
            </div>

            <!-- Total Bookings Card -->
            <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold mb-2">Total Bookings</h3>
                <p class="text-3xl font-bold text-purple-400"><?php echo $totalBookings; ?></p>
                <p class="text-sm text-gray-400 mt-2">All time bookings</p>
            </div>

            <!-- Booking Status Summary Card -->
            <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold mb-2">Booking Status</h3>
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400">Pending:</span>
                        <span class="text-yellow-400 font-semibold"><?php echo $pendingBookings; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400">Assigned:</span>
                        <span class="text-blue-400 font-semibold"><?php echo $assignedBookings; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400">Completed:</span>
                        <span class="text-green-400 font-semibold"><?php echo $completedBookings; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <h2 class="text-2xl font-semibold mb-4">Account Management</h2>
<button onclick="toggleModal('addAccountModal', true)" class="bg-green-600 px-4 py-2 rounded hover:bg-green-700 mb-6">
    Add Account
</button>

<!-- Add Account Modal -->
<div id="addAccountModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50">
    <div class="bg-gray-800 p-6 rounded-lg w-full max-w-lg">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold">Create New User</h3>
            <button onclick="toggleModal('addAccountModal', false)" class="text-white">X</button>
        </div>
        <form method="POST" action="adminhome.php">
            <input type="text" name="name" placeholder="Name" class="bg-gray-700 text-white p-2 rounded mb-3 w-full" required>
            <input type="text" name="role" placeholder="Role" class="bg-gray-700 text-white p-2 rounded mb-3 w-full" required>
            <input type="text" name="address" placeholder="Address" class="bg-gray-700 text-white p-2 rounded mb-3 w-full" required>
            <input type="text" name="contact" placeholder="Contact" class="bg-gray-700 text-white p-2 rounded mb-3 w-full" required>
            <input type="email" name="email" placeholder="Email" class="bg-gray-700 text-white p-2 rounded mb-3 w-full" required>
            <input type="text" name="username" placeholder="Username" class="bg-gray-700 text-white p-2 rounded mb-3 w-full" required>
            <input type="password" name="password" placeholder="Password" class="bg-gray-700 text-white p-2 rounded mb-3 w-full" required>
            <button type="submit" name="create_user" class="bg-green-600 px-4 py-2 rounded hover:bg-green-700 w-full">Create User</button>
        </form>
    </div>
</div>

<!-- Modal Toggle Script -->
<script>
function toggleModal(modalId, isVisible) {
    document.getElementById(modalId).classList.toggle('hidden', !isVisible);
}
</script>

<!-- Accounts List -->
<div class="mt-10 text-center">
    <h2 class="text-2xl font-semibold mb-4 text-left">Account Management</h2>
    <table class="w-full bg-gray-800 rounded-lg shadow-lg mx-auto">
        <thead class="bg-gray-700 text-gray-300">
            <tr>
                <th class="px-3 py-2">User ID</th>
                <th class="px-3 py-2">Username</th>
                <th class="px-3 py-2">Role</th>
                <th class="px-3 py-2">Created At</th>
                <th class="px-3 py-2">Actions</th>
            </tr>
        </thead>
        <tbody>
    <?php if (!empty($accounts)): ?>
        <?php foreach ($accounts as $account): ?>
            <tr class="border-b border-gray-700 hover:bg-gray-700">
                <td class="px-3 py-2"><?php echo htmlspecialchars($account['user_id']); ?></td>
                <td class="px-3 py-2"><?php echo htmlspecialchars($account['username']); ?></td>
                <td class="px-3 py-2"><?php echo htmlspecialchars($account['role']); ?></td>
                <td class="px-3 py-2"><?php echo htmlspecialchars($account['created_at']); ?></td>
                <td class="px-3 py-2 space-x-2">
                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($account)); ?>)" class="bg-blue-600 px-3 py-1 rounded hover:bg-blue-700 text-white">Edit</button>
                    <button type="button" onclick="confirmDelete(<?php echo $account['user_id']; ?>)" class="bg-red-600 px-3 py-1 rounded hover:bg-red-700 text-white">Delete</button>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="5" class="text-center px-3 py-2">No accounts found.</td>
        </tr>
    <?php endif; ?>
</tbody>
    </table>
</div>
<!-- Customers Table -->
<div class="mt-10 text-left">
    <h2 class="text-2xl font-semibold mb-4">Customers List</h2>
    <table class="w-full bg-gray-800 rounded-lg shadow-lg mx-auto">
        <thead class="bg-gray-700 text-gray-300">
            <tr>
                <th class="px-3 py-2">Customer ID</th>
                <th class="px-3 py-2">User ID</th>
                <th class="px-3 py-2">Name</th>
                <th class="px-3 py-2">Age</th>
                
                <th class="px-3 py-2">Address</th>
                <th class="px-3 py-2">Contact</th>
                <th class="px-3 py-2">Email</th>
              
            </tr>
        </thead>
        <tbody>
            <?php if (count($customers) > 0): ?>
                <?php foreach ($customers as $customer): ?>
                    <tr class="border-b border-gray-700 text-center hover:bg-gray-700">
                        <td class="px-3 py-2"><?php echo $customer['customer_id']; ?></td>
                        <td class="px-3 py-2"><?php echo $customer['user_id']; ?></td>
                        <td class="px-3 py-2"><?php echo htmlspecialchars($customer['name']); ?></td>
                        <td class="px-3 py-2"><?php echo htmlspecialchars($customer['age']); ?></td>
                        <td class="px-3 py-2"><?php echo htmlspecialchars($customer['address']); ?></td>
                        <td class="px-3 py-2"><?php echo htmlspecialchars($customer['contact']); ?></td>
                        <td class="px-3 py-2"><?php echo htmlspecialchars($customer['email']); ?></td>
                        
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" class="text-center px-3 py-2">No customers found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<!-- Riders Table -->
<div class="mt-10 text-left">
    <h2 class="text-2xl font-semibold mb-4">Riders List</h2>
    <table class="w-full bg-gray-800 rounded-lg shadow-lg mx-auto">
        <thead class="bg-gray-700 text-gray-300">
            <tr>
                <th class="px-3 py-2">Rider ID</th>
                <th class="px-3 py-2">User ID</th>
                <th class="px-3 py-2">Name</th>
                <th class="px-3 py-2">Age</th>
                <th class="px-3 py-2">Address</th>
                <th class="px-3 py-2">Contact</th>
                <th class="px-3 py-2">Email</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($riders) > 0): ?>
                <?php foreach ($riders as $rider): ?>
                    <tr class="border-b border-gray-700 text-center hover:bg-gray-700">
                        <td class="px-3 py-2"><?php echo $rider['rider_id']; ?></td>
                        <td class="px-3 py-2"><?php echo $rider['user_id']; ?></td>
                        <td class="px-3 py-2"><?php echo htmlspecialchars($rider['name']); ?></td>
                        <td class="px-3 py-2"><?php echo htmlspecialchars($rider['age']); ?></td>
                        <td class="px-3 py-2"><?php echo htmlspecialchars($rider['address']); ?></td>
                        <td class="px-3 py-2"><?php echo htmlspecialchars($rider['contact']); ?></td>
                        <td class="px-3 py-2"><?php echo htmlspecialchars($rider['email']); ?></td>
                        <td class="px-3 py-2">
                            <form method="POST" class="inline">
                                <select name="status" class="bg-gray-700 border border-gray-600 text-white px-2 py-1 rounded" onchange="this.form.submit()">
                                    <option value="Available" <?php echo ($rider['status'] ?? '') === 'Available' ? 'selected' : ''; ?>>Available</option>
                                    <option value="On Duty" <?php echo ($rider['status'] ?? '') === 'On Duty' ? 'selected' : ''; ?>>On Duty</option>
                                    <option value="Offline" <?php echo ($rider['status'] ?? '') === 'Offline' ? 'selected' : ''; ?>>Offline</option>
                                </select>
                                <input type="hidden" name="rider_id" value="<?php echo $rider['rider_id']; ?>">
                                <input type="hidden" name="update_rider_status" value="1">
                            </form>
                        </td>
                        <td class="px-3 py-2 space-x-2">
                            <a href="edit_rider.php?rider_id=<?php echo $rider['rider_id']; ?>" class="bg-blue-600 px-3 py-1 rounded hover:bg-blue-700 text-white">Edit</a>
                            <a href="?delete_rider_id=<?php echo $rider['rider_id']; ?>" class="bg-red-600 px-3 py-1 rounded hover:bg-red-700 text-white" onclick="return confirm('Are you sure you want to delete this rider?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="9" class="text-center px-3 py-2">No riders found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
        
   <!-- Bookings Table -->
<div class="mt-10 text-left">
    <h2 class="text-2xl font-semibold mb-4">Bookings List</h2>
    <?php if (isset($_GET['update'])): ?>
    <div class="p-4 mb-4 rounded-lg <?php echo $_GET['update'] === 'success' ? 'bg-green-500' : 'bg-red-500'; ?>">
        <p class="text-white">
            <?php
            if ($_GET['update'] === 'success') {
                echo "Booking status updated successfully!";
            } elseif ($_GET['update'] === 'error') {
                echo "An error occurred while updating the booking status.";
            } elseif ($_GET['update'] === 'invalid') {
                echo "Invalid input or request.";
            }
            ?>
        </p>
    </div>
<?php endif; ?>
    <table class="w-full bg-gray-800 rounded-lg shadow-lg mx-auto">
        <thead class="bg-gray-700 text-gray-300">
            <tr>
                <th class="px-3 py-2">Booking ID</th>
                <th class="px-3 py-2">Customer Name</th>
                <th class="px-3 py-2">Pickup Location</th>
                <th class="px-3 py-2">Destination</th>
                <th class="px-3 py-2">Ride Date</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2">Assigned Rider</th>

            </tr>
        </thead>
        <tbody>
            <?php if (count($bookings) > 0): ?>
                <?php foreach ($bookings as $booking): ?>
                    <tr class="border-b border-gray-700 hover:bg-gray-700">
                        <td class="px-3 py-2"><?php echo $booking['booking_id']; ?></td>
                        <td class="px-3 py-2"><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                        <td class="px-3 py-2"><?php echo htmlspecialchars($booking['pickup_location']); ?></td>
                        <td class="px-3 py-2"><?php echo htmlspecialchars($booking['destination']); ?></td>
                        <td class="px-3 py-2"><?php echo htmlspecialchars($booking['ride_date']); ?></td>
                        
                        <!-- Booking Status Dropdown -->
                        <td class="px-3 py-2">
                            <form method="POST" action="updatebooking.php">
                                <select name="status" class="bg-gray-700 border border-gray-600 text-white" onchange="this.form.submit()">
                                    <option value="Pending" <?php echo $booking['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Assigned" <?php echo $booking['status'] == 'Assigned' ? 'selected' : ''; ?>>Assigned</option>
                                    <option value="In Progress" <?php echo $booking['status'] == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="Completed" <?php echo $booking['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                                <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                            </form>
                        </td>

                        <!-- Assign Rider Dropdown -->
                        <td class="px-3 py-2">
                            <form method="POST" action="assign_rider.php">
                                <select name="rider_id" class="bg-gray-700 border border-gray-600 text-white" onchange="this.form.submit()">
                                    <option value="">Select Rider</option>
                                    <?php foreach ($riders as $rider): ?>
                                        <option value="<?php echo $rider['rider_id']; ?>" <?php echo $booking['rider_id'] == $rider['rider_id'] ? 'selected' : ''; ?>>
                                            <?php echo $rider['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                            </form>
                        </td>

                      
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="8" class="text-center px-3 py-2">No bookings found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50">
        <div class="bg-gray-800 p-6 rounded-lg w-full max-w-lg">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold">Create New User</h3>
                <button onclick="toggleModal('addUserModal', false)" class="text-white">X</button>
            </div>
            <form method="POST" action="adminhome.php">
                <input type="text" name="name" placeholder="Name" class="bg-gray-700 text-white p-2 rounded mb-3 w-full" required>
                <input type="text" name="role" placeholder="Role" class="bg-gray-700 text-white p-2 rounded mb-3 w-full" required>
                <input type="text" name="address" placeholder="Address" class="bg-gray-700 text-white p-2 rounded mb-3 w-full" required>
                <input type="text" name="contact" placeholder="Contact" class="bg-gray-700 text-white p-2 rounded mb-3 w-full" required>
                <input type="email" name="email" placeholder="Email" class="bg-gray-700 text-white p-2 rounded mb-3 w-full" required>
                <input type="text" name="username" placeholder="Username" class="bg-gray-700 text-white p-2 rounded mb-3 w-full" required>
                <input type="password" name="password" placeholder="Password" class="bg-gray-700 text-white p-2 rounded mb-3 w-full" required>
                <button type="submit" name="create_user" class="bg-green-600 px-4 py-2 rounded hover:bg-green-700 w-full">Create User</button>
            </form>
        </div>
    </div>

    <!-- EDIT USER MODAL -->
    <div id="editUserModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50">
        <div class="bg-gray-800 p-6 rounded-lg w-full max-w-lg">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold">Edit User</h3>
                <button onclick="toggleModal('editUserModal', false)" class="text-gray-300 hover:text-white text-xl">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="edit_username" class="block mb-2 text-sm">Username</label>
                        <input name="username" id="edit_username" placeholder="Username" class="w-full p-2 rounded bg-gray-700 text-white" required>
                    </div>
                    <div>
                        <label for="edit_role" class="block mb-2 text-sm">Role</label>
                        <select name="role" id="edit_role" class="w-full p-2 rounded bg-gray-700 text-white" required>
                            <option value="Customer">Customer</option>
                            <option value="Rider">Rider</option>
                        </select>
                    </div>
                    <div>
                        <label for="edit_name" class="block mb-2 text-sm">Name</label>
                        <input name="name" id="edit_name" placeholder="Name" class="w-full p-2 rounded bg-gray-700 text-white" required>
                    </div>
                    <div>
                        <label for="edit_email" class="block mb-2 text-sm">Email</label>
                        <input name="email" id="edit_email" type="email" placeholder="Email" class="w-full p-2 rounded bg-gray-700 text-white" required>
                    </div>
                    <div>
                        <label for="edit_contact" class="block mb-2 text-sm">Contact</label>
                        <input name="contact" id="edit_contact" placeholder="Contact" class="w-full p-2 rounded bg-gray-700 text-white" required>
                    </div>
                    <div>
                        <label for="edit_address" class="block mb-2 text-sm">Address</label>
                        <input name="address" id="edit_address" placeholder="Address" class="w-full p-2 rounded bg-gray-700 text-white" required>
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-4">
                    <button type="button" onclick="toggleModal('editUserModal', false)" class="bg-gray-600 px-4 py-2 rounded hover:bg-gray-700">Cancel</button>
                    <button type="submit" name="update_user" class="bg-blue-600 px-4 py-2 rounded hover:bg-blue-700">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle Modal Visibility
        function toggleModal(modalId, isVisible) {
            document.getElementById(modalId).classList.toggle('hidden', !isVisible);
        }

        function openEditModal(account) {
            // Get user details based on role
            fetch(`get_user_details.php?user_id=${account.user_id}`)
                .then(response => response.json())
                .then(userDetails => {
                    document.getElementById('edit_user_id').value = account.user_id;
                    document.getElementById('edit_username').value = account.username;
                    document.getElementById('edit_role').value = account.role;
                    document.getElementById('edit_name').value = userDetails.name;
                    document.getElementById('edit_email').value = userDetails.email;
                    document.getElementById('edit_contact').value = userDetails.contact;
                    document.getElementById('edit_address').value = userDetails.address;
                    
                    toggleModal('editUserModal', true);
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading user details');
                });
        }

        function confirmDelete(userId) {
            if (confirm('Are you sure you want to delete this account? This action cannot be undone.')) {
                console.log('Deleting user ID:', userId); // Debug log
                window.location.href = `adminhome.php?delete_user_id=${userId}`;
            }
        }

    </script>

    <!-- Payments Table -->
    <div class="mt-10 text-left">
        <h2 class="text-2xl font-semibold mb-4">Payments List</h2>
        <table class="w-full bg-gray-800 rounded-lg shadow-lg mx-auto">
            <thead class="bg-gray-700 text-gray-300">
                <tr>
                    <th class="px-3 py-2">Payment ID</th>
                    <th class="px-3 py-2">Booking ID</th>
                    <th class="px-3 py-2">Customer</th>
                    <th class="px-3 py-2">Rider</th>
                    <th class="px-3 py-2">Amount</th>
                    <th class="px-3 py-2">Method</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Date</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Fetch all payments with related info
                $query = "SELECT p.payment_id, p.booking_id, p.amount, p.payment_method, p.payment_status, p.payment_date, 
                                c.name AS customer_name, r.name AS rider_name
                        FROM payments p
                        LEFT JOIN bookings b ON p.booking_id = b.booking_id
                        LEFT JOIN customers c ON b.customer_id = c.customer_id
                        LEFT JOIN riders r ON b.rider_id = r.user_id
                        ORDER BY p.payment_date DESC";
                $result = $conn->query($query);
                $payments = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

                if (empty($payments)): ?>
                    <tr>
                        <td colspan="8" class="text-center px-3 py-2">No payments found</td>
                    </tr>
                <?php else: 
                    foreach ($payments as $payment): ?>
                    <tr class="border-b border-gray-700 text-center hover:bg-gray-700">
                        <td class="px-3 py-2"><?php echo htmlspecialchars($payment['payment_id']); ?></td>
                        <td class="px-3 py-2"><?php echo htmlspecialchars($payment['booking_id']); ?></td>
                        <td class="px-3 py-2"><?php echo htmlspecialchars($payment['customer_name']); ?></td>
                        <td class="px-3 py-2"><?php echo htmlspecialchars($payment['rider_name']); ?></td>
                        <td class="px-3 py-2">₱<?php echo number_format($payment['amount'], 2); ?></td>
                        <td class="px-3 py-2"><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                        <td class="px-3 py-2">
                            <span class="px-2 py-1 rounded <?php echo $payment['payment_status'] === 'Completed' ? 'bg-green-600' : 'bg-yellow-600'; ?>">
                                <?php echo htmlspecialchars($payment['payment_status']); ?>
                            </span>
                        </td>
                        <td class="px-3 py-2"><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                    </tr>
                <?php endforeach;
                endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
