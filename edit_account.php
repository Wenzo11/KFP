<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get the user_id from URL parameter
if (!isset($_GET['user_id'])) {
    header("Location: adminhome.php");
    exit();
}

$user_id = $_GET['user_id'];

// Get account information
$account_query = "SELECT * FROM account WHERE user_id = ?";
$stmt = $conn->prepare($account_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();

if (!$account) {
    header("Location: adminhome.php");
    exit();
}

// Get user details based on role
$role = $account['role'];
$table = ($role === 'Customer') ? 'customers' : 'riders';
$query = "SELECT * FROM $table WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

        // Update user details table
        $stmt = $conn->prepare("UPDATE $table SET name = ?, email = ?, contact = ?, address = ? WHERE user_id = ?");
        $stmt->bind_param("ssssi", $name, $email, $contact, $address, $user_id);
        $stmt->execute();

        $conn->commit();
        $_SESSION['success'] = "Account updated successfully";
        header("Location: adminhome.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error updating account: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Account</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white">
    <div class="container mx-auto p-6">
        <h2 class="text-2xl font-bold mb-6">Edit Account</h2>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-500 text-white p-4 rounded mb-4">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="max-w-lg">
            <div class="mb-4">
                <label for="username" class="block mb-2">Username</label>
                <input type="text" class="w-full bg-gray-700 text-white p-2 rounded" id="username" name="username" value="<?php echo htmlspecialchars($account['username']); ?>" required>
            </div>
            
            <div class="mb-4">
                <label for="role" class="block mb-2">Role</label>
                <select class="w-full bg-gray-700 text-white p-2 rounded" id="role" name="role" required>
                    <option value="Customer" <?php echo $account['role'] === 'Customer' ? 'selected' : ''; ?>>Customer</option>
                    <option value="Rider" <?php echo $account['role'] === 'Rider' ? 'selected' : ''; ?>>Rider</option>
                </select>
            </div>
            
            <div class="mb-4">
                <label for="name" class="block mb-2">Name</label>
                <input type="text" class="w-full bg-gray-700 text-white p-2 rounded" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
            </div>
            
            <div class="mb-4">
                <label for="email" class="block mb-2">Email</label>
                <input type="email" class="w-full bg-gray-700 text-white p-2 rounded" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            
            <div class="mb-4">
                <label for="contact" class="block mb-2">Contact Number</label>
                <input type="text" class="w-full bg-gray-700 text-white p-2 rounded" id="contact" name="contact" value="<?php echo htmlspecialchars($user['contact']); ?>" required>
            </div>
            
            <div class="mb-4">
                <label for="address" class="block mb-2">Address</label>
                <textarea class="w-full bg-gray-700 text-white p-2 rounded" id="address" name="address" required><?php echo htmlspecialchars($user['address']); ?></textarea>
            </div>
            
            <div class="flex gap-4">
                <button type="submit" class="bg-blue-600 px-4 py-2 rounded hover:bg-blue-700">Update Account</button>
                <a href="adminhome.php" class="bg-gray-600 px-4 py-2 rounded hover:bg-gray-700">Back to Dashboard</a>
            </div>
        </form>
    </div>
</body>
</html> 