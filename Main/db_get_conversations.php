<?php
require 'DatabaseHelper.php';

$db = new DatabaseHelper();
$user_id = $_GET['user_id'];

$conversations = $db->getConversations($user_id);
echo json_encode($conversations);
?>