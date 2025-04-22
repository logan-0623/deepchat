<?php
session_start();
require 'DatabaseHelper.php';

// 获取POST数据
$data = json_decode(file_get_contents('php://input'), true);

try {
    if (!isset($data['conversation_id'])) {
        throw new Exception('缺少对话ID');
    }

    $db = new DatabaseHelper();
    $pdo = $db->getConnection();

    // 开始事务
    $pdo->beginTransaction();

    try {
        // 首先删除所有相关的消息
        $stmt = $pdo->prepare("DELETE FROM messages WHERE conversation_id = ?");
        $stmt->execute([$data['conversation_id']]);

        // 然后删除对话
        $stmt = $pdo->prepare("DELETE FROM conversations WHERE id = ?");
        $stmt->execute([$data['conversation_id']]);

        // 提交事务
        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => '对话删除成功'
        ]);
    } catch (Exception $e) {
        // 如果出现错误，回滚事务
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?> 