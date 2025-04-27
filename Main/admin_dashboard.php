<?php
session_start();
require 'DatabaseHelper.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$db = new DatabaseHelper();
$pdo = $db->getConnection();

// Handle delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_user'])) {
        $userId = $_POST['delete_user'];
        // First delete all conversations and messages of the user
        $pdo->prepare("DELETE FROM messages WHERE conversation_id IN (SELECT id FROM conversations WHERE user_id = ?)")->execute([$userId]);
        $pdo->prepare("DELETE FROM conversations WHERE user_id = ?")->execute([$userId]);
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
        header("Location: admin_dashboard.php");
        exit();
    } elseif (isset($_POST['delete_conversation'])) {
        $conversationId = $_POST['delete_conversation'];
        $pdo->prepare("DELETE FROM messages WHERE conversation_id = ?")->execute([$conversationId]);
        $pdo->prepare("DELETE FROM conversations WHERE id = ?")->execute([$conversationId]);
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
}

// Get all users
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

// Get conversations for a specific user if requested
$selectedUserId = $_GET['user_id'] ?? null;
$selectedConversationId = $_GET['conversation_id'] ?? null;

$conversations = [];
$messages = [];

if ($selectedUserId) {
    $stmt = $pdo->prepare("SELECT * FROM conversations WHERE user_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$selectedUserId]);
    $conversations = $stmt->fetchAll();
}

if ($selectedConversationId) {
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
    $stmt->execute([$selectedConversationId]);
    $messages = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            display: flex;
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .panel {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .users-panel {
            width: 300px;
        }
        .conversations-panel {
            width: 300px;
        }
        .messages-panel {
            flex: 1;
        }
        .panel-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .user-item, .conversation-item {
            padding: 10px;
            margin-bottom: 5px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .user-item:hover, .conversation-item:hover {
            background-color: #f0f0f0;
        }
        .user-item.active, .conversation-item.active {
            background-color: #e3f2fd;
        }
        .item-content {
            flex: 1;
            cursor: pointer;
        }
        .delete-icon {
            color: #f44336;
            cursor: pointer;
            padding: 5px;
            margin-left: 10px;
            opacity: 0.7;
            transition: opacity 0.2s;
        }
        .delete-icon:hover {
            opacity: 1;
        }
        .message {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 8px;
        }
        .message.user {
            background-color: #e3f2fd;
            margin-left: 20px;
        }
        .message.assistant {
            background-color: #f5f5f5;
            margin-right: 20px;
        }
        .message-header {
            font-weight: bold;
            margin-bottom: 5px;
            color: #666;
        }
        .message-content {
            white-space: pre-wrap;
        }
        .message-time {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
            text-align: right;
        }
        .logout-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 8px 16px;
            background-color: #f44336;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }
        .logout-button:hover {
            background-color: #d32f2f;
        }
        .delete-form {
            display: inline;
        }
    </style>
</head>
<body>
    <a href="logout.php" class="logout-button">Logout</a>
    
    <div class="container">
        <!-- Users Panel -->
        <div class="panel users-panel">
            <div class="panel-title">Users</div>
            <?php foreach ($users as $user): ?>
                <div class="user-item <?php echo $selectedUserId == $user['id'] ? 'active' : ''; ?>">
                    <div class="item-content" onclick="window.location.href='?user_id=<?php echo $user['id']; ?>'">
                        <div><strong><?php echo htmlspecialchars($user['username']); ?></strong></div>
                        <div style="font-size: 12px; color: #666;">
                            Created: <?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?>
                        </div>
                    </div>
                    <form class="delete-form" method="POST" onsubmit="return confirm('Are you sure you want to delete this user and all their conversations?');">
                        <input type="hidden" name="delete_user" value="<?php echo $user['id']; ?>">
                        <button type="submit" class="delete-icon" style="background: none; border: none;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Conversations Panel -->
        <?php if ($selectedUserId): ?>
            <div class="panel conversations-panel">
                <div class="panel-title">Conversations</div>
                <?php foreach ($conversations as $conversation): ?>
                    <div class="conversation-item <?php echo $selectedConversationId == $conversation['id'] ? 'active' : ''; ?>">
                        <div class="item-content" onclick="window.location.href='?user_id=<?php echo $selectedUserId; ?>&conversation_id=<?php echo $conversation['id']; ?>'">
                            <div><strong><?php echo htmlspecialchars($conversation['title']); ?></strong></div>
                            <div style="font-size: 12px; color: #666;">
                                Updated: <?php echo date('Y-m-d H:i', strtotime($conversation['updated_at'])); ?>
                            </div>
                        </div>
                        <form class="delete-form" method="POST" onsubmit="return confirm('Are you sure you want to delete this conversation?');">
                            <input type="hidden" name="delete_conversation" value="<?php echo $conversation['id']; ?>">
                            <button type="submit" class="delete-icon" style="background: none; border: none;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Messages Panel -->
        <?php if ($selectedConversationId): ?>
            <div class="panel messages-panel">
                <div class="panel-title">Messages</div>
                <?php foreach ($messages as $message): ?>
                    <div class="message <?php echo $message['role']; ?>">
                        <div class="message-header">
                            <?php echo ucfirst($message['role']); ?>
                        </div>
                        <div class="message-content">
                            <?php echo htmlspecialchars($message['content']); ?>
                        </div>
                        <div class="message-time">
                            <?php echo date('Y-m-d H:i:s', strtotime($message['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 