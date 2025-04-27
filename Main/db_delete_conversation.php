<?php
session_start();
require 'DatabaseHelper.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

try {
    if (!isset($data['conversation_id'])) {
        throw new Exception('Missing conversation ID');
    }

    $db = new DatabaseHelper();
    $pdo = $db->getConnection();

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // First delete all related messages
        $stmt = $pdo->prepare("DELETE FROM messages WHERE conversation_id = ?");
        $stmt->execute([$data['conversation_id']]);

        // Then delete the conversation
        $stmt = $pdo->prepare("DELETE FROM conversations WHERE id = ?");
        $stmt->execute([$data['conversation_id']]);

        // Commit transaction
        $pdo->commit();

        echo json_encode([
            'status'  => 'success',
            'message' => 'Conversation deleted successfully'
        ]);
    } catch (Exception $e) {
        // If an error occurs, roll back the transaction
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}
?>