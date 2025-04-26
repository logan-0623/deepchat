<?php
session_start();
require 'DatabaseHelper.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $db = new DatabaseHelper();
    $pdo = $db->getConnection();

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Login attempts are logged
    error_log("Login attempts - Username: " . $username);

    // check var
    if (empty($username) || empty($password)) {
        $error = "The username and password cannot be empty";
        error_log("Login failed - The username or password is empty");
    } else {
        // Use preprocessing statements to prevent SQL injection
        $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

		if ($user) {
    		// Verify the hashed password with a password_verify
    		if (password_verify($password, $user['password'])) {
        		// The password is verified
                // If the login is successful, set the session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                // Log login information and session status
                error_log("Login successful - user ID: {$user['id']}, User name: {$user['username']}");
                error_log("Session ID: " . session_id());
                error_log("Session content: " . print_r($_SESSION, true));
                
                // reload to chat
                header("Location: Chat_Interface.php");
                exit();
            } else {
                error_log("Login failed - Wrong password - Username: " . $username);
                $error = "Wrong username or password";
            }
        } else {
            error_log("Login failed - The user does not exist - Username: " . $username);
            $error = "Wrong username or password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>login</title>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center center;
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .container {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
        }
        
        .login-form {
            background: rgba(255, 255, 255, 0.9);
            padding: 2.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #667eea;
            outline: none;
        }
        
        input[type="submit"] {
            width: 100%;
            padding: 0.8rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        input[type="submit"]:hover {
            background: #5a6fd1;
        }
        
        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            color: #666;
        }
        
        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        .error-message {
            background: #ff6b6b;
            color: white;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-form">
            <form action="" method="post">
                <h2 style="text-align: center; margin-bottom: 1.5rem; color: #333;">Welcome to Deepchat</h2>
                
                <?php if (isset($error)): ?>
                    <div class="error-message">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="username">Username：</label>
                    <input type="text" id="username" name="username" placeholder="Please enter a username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">password：</label>
                    <input type="password" id="password" name="password" placeholder="Please enter your password" required>
                </div>
                
                <div class="form-group">
                    <input type="submit" value="login">
                </div>
                
                <div class="register-link">
                   Don't have an account yet?<a href="user_register.php">Sign up now</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>