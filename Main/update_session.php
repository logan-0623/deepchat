<?php
session_start();

// Log request information
error_log("Update conversation request started");
error_log("Current session ID: " . session_id());
error_log("Current user ID: " . ($_SESSION['user_id'] ?? 'not set'));

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
error_log("Received data: " . print_r($data, true));

if (isset($data['conversation_id'])) {
    // Save old conversation ID for logging
    $old_conversation_id = $_SESSION['current_conversation_id'] ?? 'not set';
    
    // Update conversation ID
    $_SESSION['current_conversation_id'] = $data['conversation_id'];
    
    error_log("Conversation ID updated: from {$old_conversation_id} to {$data['conversation_id']}");
    
    echo json_encode([
        'status'                => 'success',
        'old_conversation_id'   => $old_conversation_id,
        'new_conversation_id'   => $data['conversation_id']
    ]);
} else {
    error_log("Update conversation failed: missing conversation ID");
    http_response_code(400);
    echo json_encode([
        'status'      => 'error',
        'message'     => 'Missing conversation ID',
        'debug_info'  => [
            'session_id'     => session_id(),
            'user_id'        => $_SESSION['user_id'] ?? 'not set',
            'received_data'  => $data
        ]
    ]);
}

error_log("Update conversation request ended");
?>