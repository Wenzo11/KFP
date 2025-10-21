<?php
session_start();
include 'connect.php';
if (isset($_GET['logout'])) {
  session_unset();
  session_destroy();
  header("Location: login.php");
  exit();
}

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT * FROM account WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify password — use password_verify() if hashed, else plain comparison (not recommended)
        if (password_verify($password, $user['password'])) {

            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Redirect based on role
            if ($user['role'] == 'admin') {
                header("Location: adminhome.php");
                exit();
            } elseif ($user['role'] == 'customer') {
                header("Location: customers_home.php");
                exit();
            } elseif ($user['role'] == 'rider') {
                header("Location: rider_page.php");
                exit();
            } else {
                echo "<script>alert('Unknown role. Contact administrator.'); window.location='login.php';</script>";
                exit();
            }

        } else {
            echo "<script>alert('Incorrect password.'); window.location='login.php';</script>";
            exit();
        }

    } else {
        echo "<script>alert('Username not found.'); window.location='login.php';</script>";
        exit();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-900">

<div class="center-screen">
  <div class="login-container">
    <div class="flex flex-col items-center mb-8">
      <img src="logo.png" alt="Your Logo" class="h-24 w-24 rounded-full mb-4 object-cover border border-gray-600">
      <h1 class="text-3xl font-extrabold text-white text-center" style="font-family: 'Poppins', sans-serif; letter-spacing: 0.05em;">Welcome to Pasugat</h1>
    </div>

    <h2 class="text-2xl font-bold text-left text-white mb-6">Login</h2>
    <form action="login.php" method="POST" class="space-y-4">

      <div>
        <label for="username" class="block text-sm font-medium text-gray-300 mb-1">Username</label>
        <input type="text" name="username" id="username" class="form-input" required />
      </div>

      <div>
        <label for="password" class="block text-sm font-medium text-gray-300 mb-1">Password</label>
        <input type="password" name="password" id="password" class="form-input" required />
        <div class="mt-2 flex items-center">
          <input type="checkbox" id="showPassword" class="mr-2 bg-gray-700 border-gray-600 text-orange-500 focus:ring-orange-500">
          <label for="showPassword" class="text-sm text-gray-400">Show Password</label>
        </div>
      </div>

      <?php if (!empty($notification)): ?>
        <div class="mt-2 p-2 bg-red-600 text-white rounded-md">
          <?php echo htmlspecialchars($notification); ?>
        </div>
      <?php endif; ?>

          <div>
      <button type="submit" name="login" class="w-full login-button text-white font-semibold py-2 rounded-lg transition">Login</button>
    </div>

      <p class="text-sm text-center text-gray-400">
        Don't have an account? <a href="register.php" class="text-orange-400 hover:underline hover:text-orange-300">Register</a>
      </p>
    </form>
  </div>
</div>

<script>
  document.getElementById("showPassword").addEventListener("change", function () {
    const passwordInput = document.getElementById("password");
    passwordInput.type = this.checked ? "text" : "password";
  });
</script>

<style>
  .login-container {
    background-color: #1f2937;
    border-radius: 1rem;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
    padding: 2rem;
    width: 100%;
    max-width: 400px;
    border: 1px solid #374151;
  }

  .login-button {
    background-color: rgb(0, 130, 140);
  }

  .login-button:hover {
    background-color: rgb(0, 255, 225);
  }

  .form-input {
    background-color: #111827;
    border: 1px solid #374151;
    color: #f3f4f6;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    width: 100%;
    outline: none;
    transition: all 0.2s;
  }

  .form-input:focus {
    border-color: rgb(255, 255, 255);
    box-shadow: 0 0 0 2px rgba(234, 88, 12, 0.4);
  }

  .center-screen {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 1rem;
  }
</style>

</body>
</html>
