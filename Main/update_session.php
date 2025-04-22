<?php
session_start();

// 记录请求信息
error_log("更新会话请求开始");
error_log("当前会话ID: " . session_id());
error_log("当前用户ID: " . ($_SESSION['user_id'] ?? 'not set'));

// 获取POST数据
$data = json_decode(file_get_contents('php://input'), true);
error_log("接收到的数据: " . print_r($data, true));

if (isset($data['conversation_id'])) {
    // 保存旧的会话ID用于日志
    $old_conversation_id = $_SESSION['current_conversation_id'] ?? 'not set';
    
    // 更新会话ID
    $_SESSION['current_conversation_id'] = $data['conversation_id'];
    
    error_log("会话ID已更新: 从 {$old_conversation_id} 更新到 {$data['conversation_id']}");
    
    echo json_encode([
        'status' => 'success',
        'old_conversation_id' => $old_conversation_id,
        'new_conversation_id' => $data['conversation_id']
    ]);
} else {
    error_log("更新会话失败: 缺少会话ID");
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => '缺少会话ID',
        'debug_info' => [
            'session_id' => session_id(),
            'user_id' => $_SESSION['user_id'] ?? 'not set',
            'received_data' => $data
        ]
    ]);
}

error_log("更新会话请求结束");
?> 