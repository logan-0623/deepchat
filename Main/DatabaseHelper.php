<?php
class DatabaseHelper {
    private $pdo;

    public function __construct() {
        // Database connection configuration
        $host = '127.0.0.1';  // Use IP address instead of localhost
        $db   = 'deepchat';    // Database name
        $user = 'Deepchat';      // Database username
        $pass = '126912';          // Database password
        $charset = 'utf8mb4';

        // Build DSN
        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        
        // Connection options
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            // Attempt to connect to database
            $this->pdo = new PDO($dsn, $user, $pass, $options);
            
            // Test connection
            $this->pdo->query("SELECT 1");
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed, please contact the administrator");
        }
    }

    public function getConnection() {
        return $this->pdo;
    }

    /**
     * User registration function
     */
    public function registerUser($userData) {
        try {
            // Validate required fields
            if (empty($userData['name']) || empty($userData['password']) || empty($userData['password2'])) {
                throw new Exception("Please fill in all required fields");
            }

            // Check passwords match
            if ($userData['password'] !== $userData['password2']) {
                throw new Exception("Passwords do not match");
            }

            // Check if username already exists
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$userData['name']]);

            if ($stmt->fetch()) {
                throw new Exception("Username already exists");
            }

            // Check if email already registered (if provided)
            if (!empty($userData['email'])) {
                $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$userData['email']]);

                if ($stmt->fetch()) {
                    throw new Exception("Email is already registered");
                }
            }

            // Validate reCAPTCHA
            if (!empty($userData['g-recaptcha-response'])) {
                $recaptcha_secret = "6LcD5CQrAAAAAMIBf_tNX78b6rFE1pvzdbsCCxt8";
                $recaptcha_response = $userData['g-recaptcha-response'];
                $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
                $recaptcha_data = [
                    'secret'   => $recaptcha_secret,
                    'response' => $recaptcha_response,
                    'remoteip' => $_SERVER['REMOTE_ADDR']
                ];

                $recaptcha_options = [
                    'http' => [
                        'method'  => 'POST',
                        'content' => http_build_query($recaptcha_data)
                    ]
                ];

                $recaptcha_context = stream_context_create($recaptcha_options);
                $recaptcha_result = file_get_contents($recaptcha_url, false, $recaptcha_context);
                $recaptcha_json = json_decode($recaptcha_result);

                if (!$recaptcha_json->success) {
                    throw new Exception("Please complete the CAPTCHA verification");
                }
            }

            // Prepare user data
            $name     = $userData['name'];
            $age      = $userData['age'] ?? null;
            $gender   = $userData['gender'] ?? null;
            $email    = $userData['email'] ?? null;
            $password = password_hash($userData['password'], PASSWORD_DEFAULT);

            // Insert new user
            $stmt = $this->pdo->prepare("INSERT INTO users (username, age, gender, email, password) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $age, $gender, $email, $password]);

            return $this->pdo->lastInsertId();

        } catch (PDOException $e) {
            error_log("User registration failed: " . $e->getMessage());
            throw new Exception("User registration failed: " . $e->getMessage());
        }
    }

    /**
     * Get user information
     */
    public function getUser($username) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Fetching user information failed: " . $e->getMessage());
            throw new Exception("Fetching user information failed");
        }
    }

    // Preserve existing conversation and message handling methods
    public function createConversation($user_id, $title) {
        try {
            // First validate user exists
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            if (!$stmt->fetch()) {
                throw new Exception("User does not exist");
            }

            error_log("Attempting to create a new conversation: user_id = $user_id, title = $title");
            
            // Create new conversation
            $stmt = $this->pdo->prepare("INSERT INTO conversations (user_id, title) VALUES (?, ?)");
            $stmt->execute([$user_id, $title]);
            $id = $this->pdo->lastInsertId();
            
            error_log("Successfully created new conversation: id = $id");
            return $id;
        } catch (PDOException $e) {
            error_log("Failed to create conversation: " . $e->getMessage());
            throw new Exception("Failed to create conversation: " . $e->getMessage());
        }
    }

    public function saveMessage($conversation_id, $role, $content) {
        try {
            // Validate role is valid
            if (!in_array($role, ['user', 'assistant', 'system'])) {
                throw new Exception("Invalid message role");
            }

            // Validate conversation exists
            $stmt = $this->pdo->prepare("SELECT id FROM conversations WHERE id = ?");
            $stmt->execute([$conversation_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Conversation does not exist");
            }

            error_log("Attempting to save message: conversation_id = $conversation_id, role = $role");
            
            $stmt = $this->pdo->prepare("INSERT INTO messages (conversation_id, role, content) VALUES (?, ?, ?)");
            $stmt->execute([$conversation_id, $role, $content]);
            $id = $this->pdo->lastInsertId();
            
            // Update conversation's updated_at timestamp
            $this->pdo->prepare("UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                      ->execute([$conversation_id]);
            
            error_log("Successfully saved message: id = $id");
            return $id;
        } catch (PDOException $e) {
            error_log("Failed to save message: " . $e->getMessage());
            throw new Exception("Failed to save message: " . $e->getMessage());
        }
    }

    public function getConversations($user_id) {
        try {
            error_log("Fetching user conversation list: user_id = $user_id");
            $stmt = $this->pdo->prepare("SELECT * FROM conversations WHERE user_id = ? ORDER BY updated_at DESC");
            $stmt->execute([$user_id]);
            $result = $stmt->fetchAll();
            error_log("Successfully fetched conversation list: count = " . count($result));
            return $result;
        } catch (PDOException $e) {
            error_log("Failed to fetch conversation list: " . $e->getMessage());
            throw new Exception("Failed to fetch conversation list: " . $e->getMessage());
        }
    }

    public function getMessages($conversation_id) {
        try {
            error_log("Fetching conversation messages: conversation_id = $conversation_id");
            $stmt = $this->pdo->prepare("SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
            $stmt->execute([$conversation_id]);
            $result = $stmt->fetchAll();
            error_log("Successfully fetched messages: count = " . count($result));
            return $result;
        } catch (PDOException $e) {
            error_log("Failed to fetch messages: " . $e->getMessage());
            throw new Exception("Failed to fetch messages: " . $e->getMessage());
        }
    }

    public function adminLogin($username, $password) {
        $sql = "SELECT * FROM admins WHERE username = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$username]);
        $result = $stmt->fetch();
        
        if ($result && password_verify($password, $result['password'])) {
            // 更新最后登录时间
            $updateSql = "UPDATE admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?";
            $updateStmt = $this->pdo->prepare($updateSql);
            $updateStmt->execute([$result['id']]);
            
            return $result;
        }
        return false;
    }

    public function createAdmin($username, $password, $email) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO admins (username, password, email) VALUES (?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$username, $hashedPassword, $email]);
        
        return $stmt->rowCount() > 0;
    }
}
?>