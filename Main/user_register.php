<?php
session_start();
require 'DatabaseHelper.php';

// Process form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $db = new DatabaseHelper();
        
        // Verify the verification code
        if (empty($_POST['captcha']) || $_POST['captcha'] !== $_SESSION['captcha']) {
            throw new Exception("Captcha error");
        }

        // Prepare user data
        $userData = [
            'name' => $_POST['name'],
            'age' => $_POST['age'] ?? null,
            'gender' => $_POST['gender'] ?? null,
            'email' => $_POST['email'] ?? null,
            'password' => $_POST['password'],
            'password2' => $_POST['password2'],
            'g-recaptcha-response' => $_POST['g-recaptcha-response'] ?? null
        ];

        // register
        $userId = $db->registerUser($userData);
        
        // If the registration is successful, you will be automatically logged in and redirected
        $_SESSION['username'] = $userData['name'];
        header("Location: user_login.php");
        exit();

    } catch (Exception $e) {
        $_SESSION['register_error'] = $e->getMessage();
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>User Register</title>
        <style>
            #captcha_image {
                margin-left: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            body {
                background-color: #f6f6f6;
                font-family: Arial, sans-serif;
            }
            .container {
                margin: 50px auto;
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
                margin-bottom: 30px;
            }
            form div {
                margin-bottom: 20px;
            }
            label {
                display: inline-block;
                font-size: 16px;
                width: 150px;
                color: #555;
                vertical-align: middle;
            }
            input[type="text"], 
            input[type="password"], 
            input[type="email"], 
            input[type="number"],
            select {
                padding: 10px;
                font-size: 16px;
                border-radius: 8px;
                border: 1px solid #ccc;
                width: 250px;
                box-sizing: border-box;
            }
            input[type="radio"] {
                margin-right: 10px;
            }
            .error {
                color: #d9534f;
                background-color: #f2dede;
                padding: 10px;
                border-radius: 5px;
                margin-bottom: 20px;
                border: 1px solid #ebccd1;
            }
            .button-group {
                margin-top: 30px;
                text-align: center;
            }
            input[type="submit"], 
            input[type="reset"],
            input[type="button"] {
                padding: 10px 25px;
                font-size: 16px;
                margin: 0 10px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                transition: background-color 0.3s;
            }
            input[type="submit"] {
                background-color: #4caf50;
                color: white;
            }
            input[type="submit"]:hover {
                background-color: #3e8e41;
            }
            input[type="reset"] {
                background-color: #f0ad4e;
                color: white;
            }
            input[type="reset"]:hover {
                background-color: #ec971f;
            }
            input[type="button"] {
                background-color: #5bc0de;
                color: white;
            }
            input[type="button"]:hover {
                background-color: #31b0d5;
            }
            .captcha-container {
                display: flex;
                align-items: center;
            }
            .captcha-refresh {
                font-size: 12px;
                color: #666;
                margin-left: 10px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <?php if (isset($_SESSION['register_error'])): ?>
                <div class="error"><?php echo htmlspecialchars($_SESSION['register_error']); ?></div>
                <?php unset($_SESSION['register_error']); ?>
            <?php endif; ?>

            <h1>User Register</h1>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" onsubmit="return validateForm()">
                <input type="hidden" id="human_check" name="human_check" value="<?php echo time(); ?>">
                
                <div>
                    <label for="name">Username:</label>
                    <input type="text" id="name" name="name" required placeholder="Enter your username">
                </div>
                
                <div>
                    <label for="age">Age:</label>
                    <input type="number" id="age" name="age" min="0" max="100" placeholder="Must">
                </div>
                
                <div>
                    <label for="gender">Gender:</label>
                    <select id="gender" name="gender" required>
                        <option value="" disabled selected>-- Select Gender --</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div>
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" placeholder="Must">
                </div>
                
                <div>
                    <label for="password1">Password:</label>
                    <input type="password" id="password1" name="password" required 
                           pattern="[0-9A-Za-z]{6,18}" placeholder="6-18 letters or numbers">
                </div>
                
                <div>
                    <label for="password2">Confirm Password:</label>
                    <input type="password" id="password2" name="password2" required 
                           pattern="[0-9A-Za-z]{6,18}" placeholder="Re-enter your password">
                </div>
                
                <div>
                    <label for="captcha">Verification Code:</label>
                    <div class="captcha-container">
                        <input type="text" id="captcha" name="captcha" required 
                               placeholder="Enter the code" style="width: 150px;">
                        <img src="captcha.php" alt="CAPTCHA" id="captcha_image" 
                             style="vertical-align: middle; cursor: pointer;" 
                             title="Click to refresh">
                        <span class="captcha-refresh">(Click image to refresh)</span>
                    </div>
                </div>
                
                <div>
                    <div class="g-recaptcha" data-sitekey="6LcD5CQrAAAAABucvQzTUFm3ElqhDgKp1RCsOZSO"></div>
                    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
                </div>
                
                <div class="button-group">
                    <input type="submit" value="Submit">
                    <input type="reset" value="Reset">
                    <input type="button" value="Back" onclick="window.location.href='user_login.php'">
                </div>
            </form>
        </div>

        <script>
            // Click to refresh the CAPTCHA image
            document.getElementById('captcha_image').addEventListener('click', function() {
                this.src = 'captcha.php?' + new Date().getTime();
            });

            // Form validation
            function validateForm() {
                // Password verification
                const password1 = document.getElementById("password1").value;
                const password2 = document.getElementById("password2").value;
                
                if (password1 !== password2) {
                    alert("Passwords do not match!");
                    return false;
                }

                // Captcha verification
                const captcha = document.getElementById("captcha").value;
                if (!captcha || captcha.length < 4) {
                    alert("Please enter the verification code!");
                    return false;
                }
                
                // reCAPTCHA validation
                if (grecaptcha.getResponse().length === 0) {
                    alert("Please complete the human verification!");
                    return false;
                }
                
                // Time Verification (Prevents Bots from Submitting Quickly)
                const submitTime = new Date().getTime();
                const formStartTime = parseInt(document.getElementById("human_check").value);
                if ((submitTime - formStartTime) < 3000) {
                    alert("Please fill out the form more carefully!");
                    return false;
                }
                
                return true;
            }

            // The time when the form was initialized
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('human_check').value = new Date().getTime();
            });
        </script>
    </body>
</html>