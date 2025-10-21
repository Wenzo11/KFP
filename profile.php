<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit();
}
require 'connect.php';

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Fetch customer information
$query = "SELECT c.*, a.username 
          FROM customers c 
          JOIN account a ON c.user_id = a.user_id 
          WHERE c.user_id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();
$stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = $_POST['name'] ?? '';
    $contact = $_POST['contact'] ?? '';
    $address = $_POST['address'] ?? '';
    $email = $_POST['email'] ?? '';
    $username = $_POST['username'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Handle profile picture upload
    $profile_pic = $customer['profile_pic'] ?? null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['profile_pic']['tmp_name'];
        $fileName = $_FILES['profile_pic']['name'];
        $fileSize = $_FILES['profile_pic']['size'];
        $fileType = $_FILES['profile_pic']['type'];
        $fileNameCmps = explode('.', $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($fileExtension, $allowedfileExtensions)) {
            $newFileName = 'profile_' . $user_id . '_' . time() . '.' . $fileExtension;
            $uploadFileDir = 'profile_pics/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }
            $dest_path = $uploadFileDir . $newFileName;
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $profile_pic = $newFileName;
            } else {
                $error_message = 'Error uploading profile picture.';
            }
        } else {
            $error_message = 'Invalid file type for profile picture.';
        }
    }

    // Validate input
    if (empty($name) || empty($contact) || empty($email)) {
        $error_message = "Name, contact, and email are required fields.";
    } else {
        try {
            // Start transaction
            $conn->begin_transaction();

            // Update customer information (including email and profile_pic)
            $update_customer = "UPDATE customers SET name = ?, contact = ?, address = ?, email = ?, profile_pic = ? WHERE user_id = ?";
            $stmt = $conn->prepare($update_customer);
            $stmt->bind_param("sssssi", $name, $contact, $address, $email, $profile_pic, $user_id);
            $stmt->execute();
            $stmt->close();

            // Update username in account table
            $update_username = "UPDATE account SET username = ? WHERE user_id = ?";
            $stmt = $conn->prepare($update_username);
            $stmt->bind_param("si", $username, $user_id);
            $stmt->execute();
            $stmt->close();

            // If password change is requested
            if (!empty($current_password) && !empty($new_password)) {
                // Verify current password
                $verify_password = "SELECT password FROM account WHERE user_id = ?";
                $stmt = $conn->prepare($verify_password);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $account = $result->fetch_assoc();
                $stmt->close();

                if (password_verify($current_password, $account['password'])) {
                    if ($new_password === $confirm_password) {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $update_password = "UPDATE account SET password = ? WHERE user_id = ?";
                        $stmt = $conn->prepare($update_password);
                        $stmt->bind_param("si", $hashed_password, $user_id);
                        $stmt->execute();
                        $stmt->close();
                    } else {
                        throw new Exception("New passwords do not match.");
                    }
                } else {
                    throw new Exception("Current password is incorrect.");
                }
            }

            $conn->commit();
            $success_message = "Profile updated successfully!";
            
            // Refresh customer data
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $customer = $result->fetch_assoc();
            $stmt->close();

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error updating profile: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Pa-Sugat</title>
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
                <img src="<?php echo !empty($customer['profile_pic']) ? 'profile_pics/' . htmlspecialchars($customer['profile_pic']) : 'https://ui-avatars.com/api/?name=' . urlencode($customer['name']); ?>" 
                     alt="Profile Picture" class="w-10 h-10 rounded-full object-cover border-2 border-gray-600">
                <div>
                    <p class="text-lg font-semibold"><?php echo htmlspecialchars($customer['name']); ?></p>
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
                <a href="ride_history.php" class="flex items-center px-3 py-2 hover:bg-gray-700 rounded">
                    <i data-feather="clock" class="w-4 h-4 mr-2"></i> Ride History
                </a>
                <a href="profile.php" class="flex items-center px-3 py-2 bg-gray-700 rounded">
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
            <div class="flex flex-col items-center mb-6">
                <img src="<?php echo !empty($customer['profile_pic']) ? 'profile_pics/' . htmlspecialchars($customer['profile_pic']) : 'https://ui-avatars.com/api/?name=' . urlencode($customer['name']); ?>" alt="Profile Picture" class="w-36 h-36 rounded-full object-cover mb-3 border-4 border-blue-500 shadow-lg">
            </div>
            <h2 class="text-2xl font-bold mb-6 text-center">My Profile</h2>

            <?php if ($success_message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="bg-gray-800 rounded-lg p-6 shadow">
                <form method="POST" action="profile.php" class="space-y-6" enctype="multipart/form-data">
                    <div class="flex flex-col items-center mb-4">
                        <input type="file" name="profile_pic" accept="image/*" class="mb-2 text-gray-200" style="max-width: 250px;">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Personal Information -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold mb-4">Personal Information</h3>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Full Name</label>
                                <input type="text" name="name" value="<?php echo htmlspecialchars($customer['name']); ?>" 
                                       class="w-full p-2 rounded bg-gray-700 border border-gray-600 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Contact Number</label>
                                <input type="tel" name="contact" value="<?php echo htmlspecialchars($customer['contact']); ?>" 
                                       class="w-full p-2 rounded bg-gray-700 border border-gray-600 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Address</label>
                                <textarea name="address" rows="3" 
                                          class="w-full p-2 rounded bg-gray-700 border border-gray-600 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"><?php echo htmlspecialchars($customer['address']); ?></textarea>
                            </div>
                        </div>

                        <!-- Account Information -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold mb-4">Account Information</h3>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Username</label>
                                <input type="text" name="username" value="<?php echo htmlspecialchars($customer['username']); ?>" class="w-full p-2 rounded bg-gray-700 border border-gray-600 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Email</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>" 
                                       class="w-full p-2 rounded bg-gray-700 border border-gray-600 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Current Password</label>
                                <input type="password" name="current_password" 
                                       class="w-full p-2 rounded bg-gray-700 border border-gray-600 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">New Password</label>
                                <input type="password" name="new_password" 
                                       class="w-full p-2 rounded bg-gray-700 border border-gray-600 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Confirm New Password</label>
                                <input type="password" name="confirm_password" 
                                       class="w-full p-2 rounded bg-gray-700 border border-gray-600 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" name="update_profile" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded transition">
                            Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        feather.replace();
    </script>
</body>
</html> 