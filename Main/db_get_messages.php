<?php
require 'DatabaseHelper.php';

$db = new DatabaseHelper();
$conversation_id = $_GET['conversation_id'];

$messages = $db->getMessages($conversation_id);
echo json_encode($messages);
?>