<?php
session_start();
require 'DatabaseHelper.php';

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['valid' => false, 'error' => 'Not logged in']);
    exit();
}

// Get parameters
$conversation_id = isset($_GET['conversation_id']) ? intval($_GET['conversation_id']) : 0;
$user_id         = isset($_GET['user_id'])         ? intval($_GET['user_id'])         : 0;

// Validate parameters
if (!$conversation_id || !$user_id) {
    header('Content-Type: application/json');
    echo json_encode(['valid' => false, 'error' => 'Invalid parameters']);
    exit();
}

// Verify user ID matches session user ID
if ($user_id !== $_SESSION['user_id']) {
    header('Content-Type: application/json');
    echo json_encode(['valid' => false, 'error' => 'User ID mismatch']);
    exit();
}

try {
    $db  = new DatabaseHelper();
    $pdo = $db->getConnection();

    // Check if conversation belongs to the user
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM conversations WHERE id = ? AND user_id = ?");
    $stmt->execute([$conversation_id, $user_id]);
    $count = $stmt->fetchColumn();

    header('Content-Type: application/json');
    echo json_encode(['valid' => ($count > 0)]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['valid' => false, 'error' => $e->getMessage()]);
}
?>