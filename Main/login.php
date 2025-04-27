<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $isAdmin = isset($_POST['is_admin']) && $_POST['is_admin'] === 'true';

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => '用户名和密码不能为空']);
        exit;
    }

    if ($isAdmin) {
        $admin = $db->adminLogin($username, $password);
        if ($admin) {
            session_start();
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            echo json_encode(['success' => true, 'message' => '管理员登录成功', 'is_admin' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => '管理员用户名或密码错误']);
        }
    } else {
        $user = $db->login($username, $password);
        if ($user) {
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            echo json_encode(['success' => true, 'message' => '登录成功', 'is_admin' => false]);
        } else {
            echo json_encode(['success' => false, 'message' => '用户名或密码错误']);
        }
    }
    exit;
} 