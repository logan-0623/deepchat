<?php
session_start();
require 'DatabaseHelper.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $db = new DatabaseHelper();
    $pdo = $db->getConnection();

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // 记录登录尝试
    error_log("登录尝试 - 用户名: " . $username);

    // 参数验证
    if (empty($username) || empty($password)) {
        $error = "用户名和密码不能为空";
        error_log("登录失败 - 用户名或密码为空");
    } else {
        // 使用预处理语句防止SQL注入
        $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user) {
            // 验证密码（这里假设密码是明文存储的，实际应用中应该使用密码哈希）
            if ($password === $user['password']) {
                // 登录成功，设置会话变量
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                // 记录登录信息和会话状态
                error_log("登录成功 - 用户ID: {$user['id']}, 用户名: {$user['username']}");
                error_log("Session ID: " . session_id());
                error_log("Session 内容: " . print_r($_SESSION, true));
                
                // 重定向到聊天界面
                header("Location: Chat_Interface.php");
                exit();
            } else {
                error_log("登录失败 - 密码错误 - 用户名: " . $username);
                $error = "用户名或密码错误";
            }
        } else {
            error_log("登录失败 - 用户不存在 - 用户名: " . $username);
            $error = "用户名或密码错误";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录</title>
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
                <h2 style="text-align: center; margin-bottom: 1.5rem; color: #333;">欢迎回来</h2>
                
                <?php if (isset($error)): ?>
                    <div class="error-message">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="username">用户名：</label>
                    <input type="text" id="username" name="username" placeholder="请输入用户名" required>
                </div>
                
                <div class="form-group">
                    <label for="password">密码：</label>
                    <input type="password" id="password" name="password" placeholder="请输入密码" required>
                </div>
                
                <div class="form-group">
                    <input type="submit" value="登录">
                </div>
                
                <div class="register-link">
                    还没有账号？<a href="user_register.php">立即注册</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>