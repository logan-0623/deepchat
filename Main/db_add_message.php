<?php
session_start();
require 'DatabaseHelper.php';

// 检查是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit();
}

// 获取POST数据
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['conversation_id']) || !isset($data['role']) || !isset($data['content'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit();
}

$conversation_id = intval($data['conversation_id']);
$role = $data['role'];
$content = $data['content'];

try {
    $db = new DatabaseHelper();
    $pdo = $db->getConnection();

    // 验证对话所有权
    $stmt = $pdo->prepare("SELECT user_id FROM conversations WHERE id = ?");
    $stmt->execute([$conversation_id]);
    $conversation = $stmt->fetch();

    if (!$conversation || $conversation['user_id'] != $_SESSION['user_id']) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized access to conversation']);
        exit();
    }

    // 开始事务
    $pdo->beginTransaction();

    try {
        // 检查是否存在重复消息（基于内容和时间戳）
        $stmt = $pdo->prepare("SELECT id FROM messages 
                              WHERE conversation_id = ? 
                              AND role = ? 
                              AND content = ? 
                              AND created_at >= DATE_SUB(NOW(), INTERVAL 5 SECOND)
                              ORDER BY id DESC 
                              LIMIT 1");
        $stmt->execute([$conversation_id, $role, $content]);
        $existingMessage = $stmt->fetch();
        
        if ($existingMessage) {
            // 如果找到重复消息，返回该消息的ID
            $pdo->commit();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Message already exists', 'message_id' => $existingMessage['id']]);
            exit();
        }

        // 插入新消息
        $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, role, content) VALUES (?, ?, ?)");
        $stmt->execute([$conversation_id, $role, $content]);
        $newMessageId = $pdo->lastInsertId();

        // 更新对话的最后修改时间
        $stmt = $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
        $stmt->execute([$conversation_id]);

        // 提交事务
        $pdo->commit();

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message_id' => $newMessageId]);
    } catch (Exception $e) {
        // 如果出现错误，回滚事务
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}