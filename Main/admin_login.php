<?php
session_start();
require 'DatabaseHelper.php';

$db = new DatabaseHelper();
$pdo = $db->getConnection();

// Handle registration
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $email = trim($_POST['email']);
    $adminKey = trim($_POST['admin_key']);

    // Check admin key
    if ($adminKey !== 'qianbihua') {
        $registerError = "Invalid admin key";
    } else {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $registerError = "Username already exists";
        } else {
            // Create new admin
            if ($db->createAdmin($username, $password, $email)) {
                $registerSuccess = "Admin account created successfully";
            } else {
                $registerError = "Failed to create admin account";
            }
        }
    }
}

// Handle login
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Log login attempt
    error_log("Admin login attempt - Username: " . $username);

    // Check variables
    if (empty($username) || empty($password)) {
        $error = "Username and password cannot be empty";
        error_log("Admin login failed - Username or password is empty");
    } else {
        // Use prepared statement to prevent SQL injection
        $stmt = $pdo->prepare("SELECT id, username, password FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin) {
            // Verify password
            if (password_verify($password, $admin['password'])) {
                // Set session variables
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                
                // Update last login time
                $updateStmt = $pdo->prepare("UPDATE admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                $updateStmt->execute([$admin['id']]);
                
                // Log login information
                error_log("Admin login successful - UserID: {$admin['id']}, Username: {$admin['username']}");
                error_log("Session ID: " . session_id());
                error_log("Session content: " . print_r($_SESSION, true));
                
                // Redirect to admin page
                header("Location: admin_dashboard.php");
                exit();
            } else {
                error_log("Admin login failed - Wrong password - Username: " . $username);
                $error = "Invalid username or password";
            }
        } else {
            error_log("Admin login failed - User not found - Username: " . $username);
            $error = "Invalid username or password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            display: flex;
            gap: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        .login-container, .register-container {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            width: 300px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input[type="text"],
        input[type="password"],
        input[type="email"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        .error-message {
            color: red;
            margin-top: 10px;
        }
        .success-message {
            color: green;
            margin-top: 10px;
        }
        .back-button {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #666;
            text-decoration: none;
        }
        .back-button:hover {
            color: #333;
            text-decoration: underline;
        }
        .tab {
            display: inline-block;
            padding: 10px 20px;
            cursor: pointer;
            background-color: #f0f0f0;
            border-radius: 5px 5px 0 0;
            margin-right: 5px;
        }
        .tab.active {
            background-color: white;
            border-bottom: 2px solid #4CAF50;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="tab active" onclick="showTab('login')">Login</div>
            <div class="tab" onclick="showTab('register')">Register</div>
            
            <div id="login" class="tab-content active">
                <h2>Admin Login</h2>
                <?php if (isset($error)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <input type="hidden" name="login" value="1">
                    <div class="form-group">
                        <label for="login_username">Username</label>
                        <input type="text" id="login_username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="login_password">Password</label>
                        <input type="password" id="login_password" name="password" required>
                    </div>
                    <button type="submit">Login</button>
                </form>
            </div>
            
            <div id="register" class="tab-content">
                <h2>Admin Register</h2>
                <?php if (isset($registerError)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($registerError); ?></div>
                <?php endif; ?>
                <?php if (isset($registerSuccess)): ?>
                    <div class="success-message"><?php echo htmlspecialchars($registerSuccess); ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <input type="hidden" name="register" value="1">
                    <div class="form-group">
                        <label for="register_username">Username</label>
                        <input type="text" id="register_username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="register_password">Password</label>
                        <input type="password" id="register_password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="admin_key">Admin Key</label>
                        <input type="password" id="admin_key" name="admin_key" required>
                    </div>
                    <button type="submit">Register</button>
                </form>
            </div>
            
            <a href="user_login.php" class="back-button">Back to User Login</a>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Update tab styles
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
        }
    </script>
</body>
</html> 