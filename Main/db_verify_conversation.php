<?php
session_start();
require 'DatabaseHelper.php';

// 检查是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['valid' => false, 'error' => 'Not logged in']);
    exit();
}

// 获取参数
$conversation_id = isset($_GET['conversation_id']) ? intval($_GET['conversation_id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// 验证参数
if (!$conversation_id || !$user_id) {
    header('Content-Type: application/json');
    echo json_encode(['valid' => false, 'error' => 'Invalid parameters']);
    exit();
}

// 验证用户ID与会话中的用户ID匹配
if ($user_id !== $_SESSION['user_id']) {
    header('Content-Type: application/json');
    echo json_encode(['valid' => false, 'error' => 'User ID mismatch']);
    exit();
}

try {
    $db = new DatabaseHelper();
    $pdo = $db->getConnection();

    // 查询对话是否属于该用户
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM conversations WHERE id = ? AND user_id = ?");
    $stmt->execute([$conversation_id, $user_id]);
    $count = $stmt->fetchColumn();

    header('Content-Type: application/json');
    echo json_encode(['valid' => ($count > 0)]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['valid' => false, 'error' => $e->getMessage()]);
} 