<?php
session_start();
require 'DatabaseHelper.php';

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit();
}

// Get POST data
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

    // Verify conversation ownership
    $stmt = $pdo->prepare("SELECT user_id FROM conversations WHERE id = ?");
    $stmt->execute([$conversation_id]);
    $conversation = $stmt->fetch();

    if (!$conversation || $conversation['user_id'] != $_SESSION['user_id']) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized access to conversation']);
        exit();
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Check for duplicate messages (based on content and timestamp)
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
            // If a duplicate message is found, return its ID
            $pdo->commit();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Message already exists', 'message_id' => $existingMessage['id']]);
            exit();
        }

        // Insert new message
        $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, role, content) VALUES (?, ?, ?)");
        $stmt->execute([$conversation_id, $role, $content]);
        $newMessageId = $pdo->lastInsertId();

        // Update conversation's last modified time
        $stmt = $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
        $stmt->execute([$conversation_id]);

        // Commit transaction
        $pdo->commit();

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message_id' => $newMessageId]);
    } catch (Exception $e) {
        // Roll back transaction on error
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}