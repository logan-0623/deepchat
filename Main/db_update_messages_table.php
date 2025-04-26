<?php
require 'DatabaseHelper.php';

try {
    $db = new DatabaseHelper();
    $pdo = $db->getConnection();

    // Check if the field already exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM messages LIKE 'message_id'");
    $stmt->execute();
    $exists = $stmt->fetch();

    if (!$exists) {
        // Add message_id column
        $sql = "ALTER TABLE messages ADD COLUMN message_id VARCHAR(36) NULL AFTER content";
        $pdo->exec($sql);
        echo "Successfully added message_id column\n";

        // Add index to improve query performance
        $sql = "CREATE INDEX idx_message_id ON messages(message_id)";
        $pdo->exec($sql);
        echo "Successfully created message_id index\n";
    } else {
        echo "message_id column already exists\n";
    }

    echo "Database update completed";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}