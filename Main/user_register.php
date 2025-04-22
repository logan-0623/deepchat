<?php
session_start();
require 'DatabaseHelper.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $db = new DatabaseHelper();
    $pdo = $db->getConnection();

    $username = $_POST['name'];
    $age = $_POST['age'];
    $gender = $_POST['gender'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $password2 = $_POST['password2'];

    if ($password !== $password2) {
        echo "<script>alert('两次密码输入不一致');history.back();</script>";
        exit();
    }

    // 检查用户名或邮箱是否重复
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);

    if ($stmt->fetch()) {
        echo "<script>alert('用户名或邮箱已被注册');history.back();</script>";
        exit();
    }

    // 插入新用户数据
    $stmt = $pdo->prepare("INSERT INTO users (username, age, gender, email, password) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$username, $age, $gender, $email, $password]);

    // 自动登录并跳转
    $_SESSION['username'] = $username;
    header("Location: Chat_Interface_cleaned_preserved.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8">
		<title>User Register Table</title>
		<style>
			body {
				background-color: #f6f6f6;
			}
			.container {
				margin: auto;
				width: 50%;
				padding: 40px;
				background-color: white;
				border-radius: 20px;
				box-shadow: 0 0 10px rgba(0,0,0,0.3);
			}
			h1 {
				text-align: center;
				font-size: 32px;
				color: #333;
			}
			form div {
				margin-bottom: 20px;
			}
			label {
				display: inline-block;
				font-size: 16px;
				width: 100px;
				color: #555;
			}
			input[type="text"], input[type="password"], input[type="email"], input[type="number"] {
				padding: 8px 10px;
				font-size: 16px;
				border-radius: 8px;
				border: 1px solid #ccc;
				width: 250px;
			}
			input[type="radio"] {
				margin-right: 10px;
			}
			input[type="submit"], input[type="reset"] {
				padding: 5px 20px;
				font-size: 18px;
				background-color: #4caf50;
				color: white;
				border: none;
				border-radius: 5px;
				cursor: pointer;
			}
			input[type="submit"]:hover, input[type="reset"]:hover {
				background-color: #3e8e41;
			}
		</style>
	</head>
	<body>
		<div class="container" >
			<h1>User Register Table</h1>
			<form method="post" onsubmit="return validateForm();">
				<div>
					<label>Username:</label>
					<input type="text" required="required" name="name"/>
				</div>
				<div>
					<label>Age:</label>
					<input type="number" id="count" min="0" max="100" step="1" name="age"/>
				</div>
				<div>
    				<label>Gender:</label>
    				<select name="gender" required>
        			<option value="" disabled selected>-- Select Gender --</option>
        			<option value="male">Male</option>
        			<option value="female">Female</option>
        			<option value="other">Other</option>
    				</select>
				</div>
				<div>
					<label>Email:</label>
					<input type="email" name="email"/>
				</div>
				<div>
				<label>Password:</label>
				<input type="password" required="required" pattern="[0-9A-Za-z]{6,18}" name="password" id="password1" placeholder="6-18 Letters or numbers" />
    			</div>
    			<div>
        		<label>Confirm password:</label>
        		<input type="password" required="required" pattern="[0-9A-Za-z]{6,18}" name="password2" id="password2" placeholder="6-18 Letters or numbers" />
    			</div>
				<div>
					<input type="submit" value="submit"/>
					<input type="reset" value="reset"/>
					<input type="button" value="Back" onclick="history.back()">
				</div>
			</form>
			<script>
				function validatePassword() {
    				const password1 = document.getElementById("password1").value;
    				const password2 = document.getElementById("password2").value;

    			if (password1 !== password2) {
        			alert("Passwords do not match!");
        			return false; 
    			}
    			return true; 
				}
				function checkUsername() {
            		const username = document.getElementById("username").value;
            		const message = document.getElementById("usernameMessage");

            		if (username.length >= 3) { // 至少3个字符才检查
                		fetch('check_username.php?username=' + username)
                    	.then(response => response.json())
                    	.then(data => {
                        if (data.exists) {
                            message.textContent = "Username already taken!";
                            message.style.color = "red";
                        } else {
                            message.textContent = "Username available!";
                            message.style.color = "green";
                        }
                    	});
            		} else {
                	message.textContent = "";
            		}
        		}

        		// 提交前验证密码和用户名
        		function validateForm() {
            	const password1 = document.getElementById("password1").value;
            	const password2 = document.getElementById("password2").value;
            	const usernameMessage = document.getElementById("usernameMessage");

            	if (password1 !== password2) {
                	alert("Passwords do not match!");
                	return false;
            	}

           	 	if (usernameMessage.style.color === "red") {
                	alert("Username already taken!");
                	return false;
            	}

            	return true;
        		}
			</script>
		</div>
	</body>
</html>