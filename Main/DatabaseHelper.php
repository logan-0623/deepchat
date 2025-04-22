<?php
class DatabaseHelper {
    private $pdo;

    public function __construct() {
        // 数据库连接配置
        $host = '127.0.0.1';  // 使用IP地址而不是localhost
        $db   = 'aichat';    // 数据库名
        $user = 'root';      // 数据库用户名
        $pass = 'qianbihua'; // 数据库密码
        $charset = 'utf8mb4';

        // 构建DSN
        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        
        // 连接选项
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            // 尝试连接数据库
            $this->pdo = new PDO($dsn, $user, $pass, $options);
            
            // 测试连接
            $this->pdo->query("SELECT 1");
            
        } catch (PDOException $e) {
            error_log("数据库连接失败: " . $e->getMessage());
            throw new Exception("数据库连接失败，请联系管理员");
        }
    }

    public function getConnection() {
        return $this->pdo;
    }    

    public function createConversation($user_id, $title) {
        try {
            // 首先验证用户是否存在
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            if (!$stmt->fetch()) {
                throw new Exception("用户不存在");
            }

            error_log("尝试创建新对话: user_id = $user_id, title = $title");
            
            // 创建新对话
            $stmt = $this->pdo->prepare("INSERT INTO conversations (user_id, title) VALUES (?, ?)");
            $stmt->execute([$user_id, $title]);
            $id = $this->pdo->lastInsertId();
            
            error_log("成功创建新对话: id = $id");
            return $id;
        } catch (PDOException $e) {
            error_log("创建对话失败: " . $e->getMessage());
            throw new Exception("创建对话失败: " . $e->getMessage());
        }
    }

    public function saveMessage($conversation_id, $role, $content) {
        try {
            // 验证角色是否有效
            if (!in_array($role, ['user', 'assistant', 'system'])) {
                throw new Exception("无效的消息角色");
            }

            // 验证会话是否存在
            $stmt = $this->pdo->prepare("SELECT id FROM conversations WHERE id = ?");
            $stmt->execute([$conversation_id]);
            if (!$stmt->fetch()) {
                throw new Exception("对话不存在");
            }

            error_log("尝试保存消息: conversation_id = $conversation_id, role = $role");
            
            $stmt = $this->pdo->prepare("INSERT INTO messages (conversation_id, role, content) VALUES (?, ?, ?)");
            $stmt->execute([$conversation_id, $role, $content]);
            $id = $this->pdo->lastInsertId();
            
            // 更新对话的更新时间
            $this->pdo->prepare("UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$conversation_id]);
            
            error_log("成功保存消息: id = $id");
            return $id;
        } catch (PDOException $e) {
            error_log("保存消息失败: " . $e->getMessage());
            throw new Exception("保存消息失败: " . $e->getMessage());
        }
    }

    public function getConversations($user_id) {
        try {
            error_log("获取用户对话列表: user_id = $user_id");
            $stmt = $this->pdo->prepare("SELECT * FROM conversations WHERE user_id = ? ORDER BY updated_at DESC");
            $stmt->execute([$user_id]);
            $result = $stmt->fetchAll();
            error_log("成功获取对话列表: count = " . count($result));
            return $result;
        } catch (PDOException $e) {
            error_log("获取对话列表失败: " . $e->getMessage());
            throw new Exception("获取对话列表失败: " . $e->getMessage());
        }
    }

    public function getMessages($conversation_id) {
        try {
            error_log("获取对话消息: conversation_id = $conversation_id");
            $stmt = $this->pdo->prepare("SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
            $stmt->execute([$conversation_id]);
            $result = $stmt->fetchAll();
            error_log("成功获取消息: count = " . count($result));
            return $result;
        } catch (PDOException $e) {
            error_log("获取消息失败: " . $e->getMessage());
            throw new Exception("获取消息失败: " . $e->getMessage());
        }
    }
}
?>
