<?php
// 接收从Main Page传来的消息
$initial_message = isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '';
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <title>Deepchat 对话界面</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            height: 100vh;
            margin: 0;
            padding: 0;
            background-color: #343541;
            color: #fff;
            overflow: hidden;
        }
        .sidebar {
            width: 220px;
            background-color: #202123;
            padding: 10px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .menu-item { margin: 15px 0; cursor: pointer; }

        .chat-container {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            padding: 15px;
        }
        .chat-box {
            flex-grow: 1;
            overflow-y: auto;
            padding: 10px;
        }
        .input-area {
            display: flex;
            padding: 10px;
        }
        .input-area input[type="text"] {
            flex-grow: 1;
            padding: 10px;
            border-radius: 5px;
            border: none;
            outline: none;
        }
        .input-area button {
            margin-left: 10px;
            padding: 10px;
            border: none;
            background-color: #4e80ee;
            color: white;
            border-radius: 5px;
            cursor: pointer;
        }
        .message { display: flex; margin-bottom: 15px; }
        .user-message { justify-content: flex-end; }
        .bot-message { justify-content: flex-start; }
        .message-content {
            max-width: 60%;
            padding: 10px;
            border-radius: 8px;
            background-color: #40414f;
        }
        .user-message .message-content { background-color: #4e80ee; }
        .avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            margin: 0 10px;
            background-size: cover;
        }
        .toggle-button {
            cursor: pointer;
            color: #fff;
            padding: 5px;
        }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div>
        <div class="toggle-button" id="hideSidebar"><i class="fas fa-bars"></i></div>
        <div class="menu-item">🔄 New Chat</div>
        <div class="menu-item">📃 Chat History</div>
    </div>
    <div class="menu-item">👤 My Profile</div>
</div>

<div class="chat-container">
    <div class="toggle-button" id="showSidebar" style="display:none;"><i class="fas fa-bars"></i></div>
    <div class="chat-box" id="chatBox">
    <?php if($initial_message): ?>
    <div class="message user-message">
        <div class="message-content"><?= $initial_message ?></div>
        <div class="avatar" style="background-image:url('https://via.placeholder.com/30?text=人');"></div>
    </div>
    <?php endif; ?>
</div>

    <div class="input-area">
        <input type="text" id="messageInput" placeholder="输入消息...">
        <button onclick="sendMessage()">发送</button>
    </div>
</div>

<script>
    async function sendMessage() {
        const input = document.getElementById('messageInput');
        const message = input.value;
        if (!message) return;

        const chatBox = document.getElementById('chatBox');

        const userMessage = document.createElement('div');
        userMessage.className = 'message user-message';
        userMessage.innerHTML = `<div class="message-content">${message}</div><div class="avatar" style="background-image:url('https://via.placeholder.com/30?text=人');"></div>`;
        chatBox.appendChild(userMessage);

        const response = await fetch('chat.php',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify({message})
        });
        const data = await response.json();

        const botMessage = document.createElement('div');
        botMessage.className = 'message bot-message';
        botMessage.innerHTML = `<div class="avatar" style="background-image:url('https://via.placeholder.com/30');"></div><div class="message-content">${data.reply}</div>`;
        chatBox.appendChild(botMessage);

        input.value = '';
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    const hideSidebarBtn = document.getElementById('hideSidebar');
    const showSidebarBtn = document.getElementById('showSidebar');
    const sidebar = document.getElementById('sidebar');

    hideSidebarBtn.onclick = function() {
        sidebar.style.display = 'none';
        showSidebarBtn.style.display = 'block';
    };

    showSidebarBtn.onclick = function() {
        sidebar.style.display = 'flex';
        showSidebarBtn.style.display = 'none';
    };

    document.addEventListener("DOMContentLoaded", function() {
        let initialMessage = <?= json_encode($initial_message) ?>;
        if(initialMessage){
            fetch('chat_backend.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({message: initialMessage})
            })
            .then(res => res.json())
            .then(data => {
                const chatBox = document.getElementById('chatBox');
                const botMessage = document.createElement('div');
                botMessage.className = 'message bot-message';
                botMessage.innerHTML = `<div class="avatar" style="background-image:url('https://via.placeholder.com/30');"></div><div class="message-content">${data.reply}</div>`;
                chatBox.appendChild(botMessage);
                chatBox.scrollTop = chatBox.scrollHeight;
            });
        }
    });
</script>
</body>
</html>
