<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deepchat AI互动页面</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            display: flex;
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden;
            font-family: Arial, sans-serif;
            background-image: url('Background/background.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        .sidebar {
            width: 220px;
            background-color: #ffffff;
            border-right: 1px solid #ddd;
            padding: 10px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .menu-item {
            margin: 15px 0;
            cursor: pointer;
        }
        .submenu {
            display: none;
            margin-left: 15px;
        }
        .toggle-button {
            cursor: pointer;
            color: #999;
            margin-bottom: 10px;
        }
        .main {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }
        #showSidebar {
            position: absolute;
            top: 10px;
            left: 10px;
            cursor: pointer;
            z-index: 1000;
            color: #999;
        }
        .input-area {
            width: 60%;
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }
        .text-send-area {
            display: flex;
            align-items: center;
        }
        .text-send-area input[type="text"] {
            flex-grow: 1;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .text-send-area button {
            margin-left: 10px;
            padding: 8px 12px;
            border: none;
            background-color: #007bff;
            color: white;
            border-radius: 4px;
            cursor: pointer;
        }
        .button-area {
            margin-top: 10px;
            display: flex;
            align-items: center;
        }
        .button-area button, .button-area label {
            margin-right: 10px;
            padding: 8px 12px;
            border: none;
            background-color: #007bff;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
        }
        .button-area input[type="file"] {
            display: none;
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
    <div class="menu-item" id="profileMenu">👤 My Profile
        <div class="submenu" id="profileSubmenu">
            <div class="menu-item">⚙️ Settings</div>
            <div class="menu-item">🗑️ Delete All Chats</div>
            <div class="menu-item">✉️ Contact Us</div>
            <div class="menu-item">🚪 Log Out</div>
        </div>
    </div>
</div>

<div class="main">
    <div class="toggle-button" id="showSidebar" style="display:none;"><i class="fas fa-bars"></i></div>
    <h1>Hi, I'm Deepchat.</h1>
    <form class="input-area" action="Chat_Interface.php" method="post">
        <div class="text-send-area">
            <input name="message" type="text" placeholder="Message Deepchat" required>
            <button type="submit">Send</button>
        </div>
        <div class="button-area">
            <button type="button">Morethink</button>
            <button type="button">Search</button>
            <label for="file-upload"><i class="fas fa-paperclip"></i>&nbsp;Attach File</label>
            <input id="file-upload" type="file">
        </div>
    </form>
</div>

<script>
    document.getElementById("profileMenu").onclick = function() {
        var submenu = document.getElementById("profileSubmenu");
        submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
    };

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
</script>
</body>
</html>