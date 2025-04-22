<?php
require 'DatabaseHelper.php';

try {
    $db = new DatabaseHelper();
    $pdo = $db->getConnection();

    // 检查字段是否已存在
    $stmt = $pdo->prepare("SHOW COLUMNS FROM messages LIKE 'message_id'");
    $stmt->execute();
    $exists = $stmt->fetch();

    if (!$exists) {
        // 添加message_id字段
        $sql = "ALTER TABLE messages ADD COLUMN message_id VARCHAR(36) NULL AFTER content";
        $pdo->exec($sql);
        echo "成功添加message_id字段\n";

        // 添加索引以提高查询性能
        $sql = "CREATE INDEX idx_message_id ON messages(message_id)";
        $pdo->exec($sql);
        echo "成功创建message_id索引\n";
    } else {
        echo "message_id字段已存在\n";
    }

    echo "数据库更新完成";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage();
} 