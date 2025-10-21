<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
include 'connect.php';



// Fetch users table data
$usersQuery = "SELECT user_id, username, email, role, created_at FROM users ORDER BY created_at DESC";
$usersResult = mysqli_query($conn, $usersQuery);
if (!$usersResult) {
  error_log("Query error: " . mysqli_error($conn));
  $notification = "Failed to fetch users data.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Currently Online Users</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white font-sans">

  <!-- Navbar -->
  <nav class="bg-gray-800 p-4 sticky top-0 z-50 shadow-md">
  <div class="container mx-auto flex justify-between items-center">
    <div class="text-xl font-bold text-white">Admin Panel</div>
    <ul class="flex space-x-6 text-gray-300">
    <li><a href="adminhome.php" class="hover:text-white">Dashboard</a></li>
    <li><a href="active_users.php" class="hover:text-white">Users</a></li>
    <li>
      <a href="logout.php" class="text-red-500 hover:text-red-600">Logout</a>
    </li>
    </ul>
  </div>
  </nav>

  

  <!-- Users Table -->
  <h1 class="text-4xl font-bold mt-12 mb-6 text-center text-indigo-400">All Users</h1>
  <div class="bg-gray-800 rounded-xl shadow-2xl p-8 overflow-x-auto">
    <table class="w-full text-left border border-gray-700">
    <thead class="bg-gray-700">
      <tr>
      <th class="p-4 text-indigo-300">User ID</th>
      <th class="p-4 text-indigo-300">Username</th>
      <th class="p-4 text-indigo-300">Email</th>
      <th class="p-4 text-indigo-300">Role</th>
      <th class="p-4 text-indigo-300">Created At</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($usersResult && mysqli_num_rows($usersResult) > 0): ?>
      <?php while ($user = mysqli_fetch_assoc($usersResult)) : ?>
        <tr class="border-b border-gray-600 hover:bg-indigo-700 transition-all duration-300">
        <td class="p-4"><?php echo htmlspecialchars($user['user_id']); ?></td>
        <td class="p-4"><?php echo htmlspecialchars($user['username']); ?></td>
        <td class="p-4"><?php echo htmlspecialchars($user['email']); ?></td>
        <td class="p-4"><?php echo htmlspecialchars($user['role']); ?></td>
        <td class="p-4"><?php echo date("M d, Y - h:i A", strtotime($user['created_at'])); ?></td>
        </tr>
      <?php endwhile; ?>
      <?php else: ?>
      <tr>
        <td colspan="5" class="p-4 text-center text-gray-400">No users found.</td>
      </tr>
      <?php endif; ?>
    </tbody>
    </table>
  </div>
  </div>
  <script src="https://unpkg.com/feather-icons"></script>
  <script>
  feather.replace();
  </script>
</body>
</html>
