<?php
session_start();

// 清除管理员会话变量
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);

// 重定向到管理员登录页面
header('Location: admin_login.php');
exit();
?> 