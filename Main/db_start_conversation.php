<?php
session_start();
require 'DatabaseHelper.php';

// Log request start
error_log("Starting creation of new conversation");

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
error_log("Received data: " . print_r($data, true));

try {
    // Check for user ID
    $user_id = $data['user_id'] ?? $_SESSION['user_id'] ?? null;
    error_log("Using user ID: " . ($user_id ?? 'null'));
    
    if (!$user_id) {
        throw new Exception('User ID not found, please log in again');
    }

    $title = $data['title'] ?? 'New Conversation';

    $db = new DatabaseHelper();
    
    // Begin transaction
    $pdo = $db->getConnection();
    $pdo->beginTransaction();

    try {
        // Create new conversation
        $conversation_id = $db->createConversation($user_id, $title);
        
        if (!$conversation_id) {
            throw new Exception('Conversation creation failed');
        }

        // Commit transaction
        $pdo->commit();

        // Update session variable
        $_SESSION['current_conversation_id'] = $conversation_id;

        // Log success information
        error_log("Successfully created new conversation: conversation_id = $conversation_id, user_id = $user_id");

        echo json_encode([
            'status' => 'success',
            'conversation_id' => $conversation_id,
            'message' => 'Successfully created new conversation'
        ]);

    } catch (Exception $e) {
        // Roll back transaction on error
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    // Log error
    error_log("Conversation creation failed: " . $e->getMessage() . "\nPOST data: " . print_r($data, true));
    
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

error_log("Create conversation request ended");
?>