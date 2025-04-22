<?php
session_start();
require 'DatabaseHelper.php';

// 记录请求开始
error_log("开始创建新对话");

// 获取POST数据
$data = json_decode(file_get_contents('php://input'), true);
error_log("接收到的数据: " . print_r($data, true));

try {
    // 检查是否有用户ID
    $user_id = $data['user_id'] ?? $_SESSION['user_id'] ?? null;
    error_log("使用的用户ID: " . ($user_id ?? 'null'));
    
    if (!$user_id) {
        throw new Exception('未找到用户ID，请重新登录');
    }

    $title = $data['title'] ?? '新的对话';

    $db = new DatabaseHelper();
    
    // 开始事务
    $pdo = $db->getConnection();
    $pdo->beginTransaction();

    try {
        // 创建新对话
        $conversation_id = $db->createConversation($user_id, $title);
        
        if (!$conversation_id) {
            throw new Exception('创建对话失败');
        }

        // 提交事务
        $pdo->commit();

        // 更新会话变量
        $_SESSION['current_conversation_id'] = $conversation_id;

        // 记录成功信息
        error_log("成功创建新对话: conversation_id = $conversation_id, user_id = $user_id");

        echo json_encode([
            'status' => 'success',
            'conversation_id' => $conversation_id,
            'message' => '成功创建新对话'
        ]);

    } catch (Exception $e) {
        // 如果出现错误，回滚事务
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    // 记录错误
    error_log("创建对话失败: " . $e->getMessage() . "\nPOST数据: " . print_r($data, true));
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'debug_info' => [
            'user_id' => $user_id ?? 'not set',
            'session_user_id' => $_SESSION['user_id'] ?? 'not set',
            'post_data' => $data
        ]
    ]);
}

error_log("创建对话请求结束");
?>