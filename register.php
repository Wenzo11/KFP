<?php
include 'connect.php'; // Include database connection file
session_start(); // Start the session

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables for form data
$name = $address = $contact = $email = $username = $password = $confirm_password = $role = $age = "";
$notification = "";

// Handling form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form values
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $contact = trim($_POST['contact']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role']; // Get the role
    $age = trim($_POST['age']);

    // Check if any field is empty
    if (empty($name) || empty($address) || empty($contact) || empty($email) || empty($username) || empty($password) || empty($confirm_password) || empty($role) || empty($age)) {
        $notification .= "All fields are required.<br>";
    }

    // Validate age
    if (!is_numeric($age) || $age < 18 || $age > 100) {
        $notification .= "Age must be a valid number between 18 and 100.<br>";
    }

    // Validate contact (phone number)
    if (!preg_match("/^\d{11}$/", $contact)) {
        $notification .= "Invalid phone number. Please enter a valid 11-digit number.<br>";
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !strpos($email, "@gmail.com")) {
        $notification .= "Please enter a valid Gmail address.<br>";
    }

    // Check if the username already exists
    $username_check = "SELECT * FROM account WHERE username = ?";
    $stmt = $conn->prepare($username_check);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $notification .= "Username already taken. Please choose a different one.<br>";
    }

    // Password validation (matching)
    if ($password !== $confirm_password) {
        $notification .= "Passwords do not match.<br>";
    }

    // If no errors, insert into the accounts table
    if (empty($notification)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT); // Hash the password

        // Insert into the accounts table
        $sql = "INSERT INTO account (username, password, role) VALUES (?, ?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sss", $username, $hashed_password, $role);

            if ($stmt->execute()) {
                // Get the inserted user_id
                $user_id = $stmt->insert_id;

                // Insert into the respective table based on the role
                if ($role === 'customer') {
                    $sql_customer = "INSERT INTO customers (user_id, name, address, contact, email, age) VALUES (?, ?, ?, ?, ?, ?)";
                    if ($stmt_customer = $conn->prepare($sql_customer)) {
                        $stmt_customer->bind_param("issssi", $user_id, $name, $address, $contact, $email, $age);
                        if ($stmt_customer->execute()) {
                            $notification .= "Registration successful as Customer!<br>";
                        } else {
                            $notification .= "Failed to add customer details: " . $stmt_customer->error . "<br>";
                        }
                        $stmt_customer->close();
                    } else {
                        $notification .= "Error preparing customer insert: " . $conn->error . "<br>";
                    }
                } elseif ($role === 'rider') {
                    $sql_rider = "INSERT INTO riders (user_id, name, address, contact, email, age) VALUES (?, ?, ?, ?, ?, ?)";
                    if ($stmt_rider = $conn->prepare($sql_rider)) {
                        $stmt_rider->bind_param("issssi", $user_id, $name, $address, $contact, $email, $age);
                        if ($stmt_rider->execute()) {
                            $notification .= "Registration successful as Rider!<br>";
                        } else {
                            $notification .= "Failed to add rider details: " . $stmt_rider->error . "<br>";
                        }
                        $stmt_rider->close();
                    } else {
                        $notification .= "Error preparing rider insert: " . $conn->error . "<br>";
                    }
                }

                $age = trim($_POST['age']);

                // Validate age
                if (!is_numeric($age) || $age < 18) {
                    $notification .= "Age must be 18 or above.<br>";
                }

                // Additional validation for riders
                if ($role === 'rider' && $age < 18) {
                    $notification .= "Riders must be at least 18 years old.<br>";
                }

                // Redirect to login page after successful registration
                header("Location: login.php");
                $_SESSION['notification'] = "Registration successful! You can now log in.";
                exit();

            } else {
                $notification .= "Error: " . $stmt->error . "<br>";
            }

            $stmt->close();
        } else {
            $notification .= "Error preparing accounts insert: " . $conn->error . "<br>";
        }
    }

    // Close database connection
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900">
  <div class="max-w-xl mx-auto mt-10 bg-gray-800 p-8 rounded-lg shadow-md border border-gray-700">
    <!-- Logo & Title -->
    <div class="flex items-center mb-6">
      <img src="logo.png" alt="Logo" class="h-16 w-16 rounded-full mr-4 border border-gray-600">
      <h1 class="text-2xl font-bold text-white">Register to Pasugat</h1>
    </div>

    <form action="register.php" method="POST" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-300">Name</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>" required class="w-full bg-gray-700 border border-gray-600 rounded-md p-2 text-white focus:ring-orange-500 focus:border-orange-500">
      </div>




      <!-- Age Field -->
            <div>
              <label class="block text-sm font-medium text-gray-300">Age</label>
              <input type="number" name="age" value="<?php echo htmlspecialchars($age); ?>" required class="w-full bg-gray-700 border border-gray-600 rounded-md p-2 text-white focus:ring-orange-500 focus:border-orange-500">
            </div>


      <div>
        <label class="block text-sm font-medium text-gray-300">Address</label>
        <input type="text" name="address" value="<?php echo htmlspecialchars($address); ?>" required class="w-full bg-gray-700 border border-gray-600 rounded-md p-2 text-white focus:ring-orange-500 focus:border-orange-500">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-300">Contact</label>
        <input type="text" name="contact" value="<?php echo htmlspecialchars($contact); ?>" required class="w-full bg-gray-700 border border-gray-600 rounded-md p-2 text-white focus:ring-orange-500 focus:border-orange-500">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-300">Email</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required class="w-full bg-gray-700 border border-gray-600 rounded-md p-2 text-white focus:ring-orange-500 focus:border-orange-500">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-300">Username</label>
        <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" required class="w-full bg-gray-700 border border-gray-600 rounded-md p-2 text-white focus:ring-orange-500 focus:border-orange-500">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-300">Password</label>
        <input type="password" name="password" id="password" required class="w-full bg-gray-700 border border-gray-600 rounded-md p-2 text-white focus:ring-orange-500 focus:border-orange-500">
        <div class="mt-2">
          <label class="block text-sm font-medium text-gray-300">Confirm Password</label>
          <input type="password" name="confirm_password" id="confirm_password" required class="w-full bg-gray-700 border border-gray-600 rounded-md p-2 text-white focus:ring-orange-500 focus:border-orange-500">
        </div>
        <div class="mt-2 flex items-center">
          <input type="checkbox" id="showPassword" class="mr-2 bg-gray-700 border-gray-600 text-orange-500 focus:ring-orange-500">
          <label for="showPassword" class="text-sm text-gray-400">Show Password</label>
        </div>
      </div>

      

      <!-- Role Selection Dropdown -->
      <div>
        <label class="block text-sm font-medium text-gray-300">Role</label>
        <select name="role" required class="w-full bg-gray-700 border border-gray-600 rounded-md p-2 text-white focus:ring-orange-500 focus:border-orange-500">
          <option value="customer">Customer</option>
          <option value="rider">Rider</option>
          <option value="admin">Admin</option>
        </select>
      </div>

      <button type="submit" class="w-full bg-orange-600 hover:bg-orange-700 text-white py-2 px-4 rounded-md transition duration-200">
        Register
      </button>
    </form>

    <!-- Notification Section -->
    <?php if (!empty($notification)): ?>
      <div class="mt-4 p-4 <?php echo strpos($notification, 'successful') !== false ? 'bg-green-600' : 'bg-red-600'; ?> text-white rounded-md">
        <?php echo htmlspecialchars($notification); ?>
      </div>
    <?php endif; ?>

    <p class="text-sm text-gray-400 mt-4">Already have an account? <a href="login.php" class="text-orange-400 hover:underline hover:text-orange-300">Login</a></p>
  </div>

  <!-- Show Password Script -->
  <script>
    document.getElementById('showPassword').addEventListener('change', function () {
      const passwordInput = document.getElementById('password');
      const confirmPasswordInput = document.getElementById('confirm_password');
      passwordInput.type = this.checked ? "text" : "password";
      confirmPasswordInput.type = this.checked ? "text" : "password";
    });
  </script>
</body>
</html>