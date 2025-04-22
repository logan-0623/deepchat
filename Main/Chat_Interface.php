<?php
session_start();

// Detailed session debugging information
error_log("========== Session Debug Information ==========");
error_log("Session ID: " . session_id());
error_log("All session variables: " . print_r($_SESSION, true));
error_log("Cookie information: " . print_r($_COOKIE, true));
error_log("=================================");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("Warning: User not logged in, redirecting to login page");
    header('Location: user_login.php');
    exit();
}

// Get and verify user information
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'unknown';

// Log user information
error_log("Current user information:");
error_log("- User ID: " . $user_id);
error_log("- Username: " . $username);

$conversation_id = isset($_SESSION['current_conversation_id']) ? $_SESSION['current_conversation_id'] : null;
error_log("Current conversation ID: " . ($conversation_id ?? 'null'));

// If no current conversation ID, get user's latest conversation
if (!$conversation_id && $user_id) {
    require 'DatabaseHelper.php';
    $db = new DatabaseHelper();
    try {
        $pdo = $db->getConnection();
        $stmt = $pdo->prepare("SELECT id FROM conversations WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        if ($result) {
            $conversation_id = $result['id'];
            $_SESSION['current_conversation_id'] = $conversation_id;
        }
    } catch (Exception $e) {
        error_log("Failed to get latest conversation: " . $e->getMessage());
    }
}

// Log session information
error_log("Current session state - User ID: $user_id, Conversation ID: " . ($conversation_id ?? 'null'));

// Receive message from Main Page
$initial_message = isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deepchat AI Chat Interface</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'PingFang SC', 'Microsoft YaHei', sans-serif;
            background: url('../Background/background.jpg') center center/cover no-repeat fixed;
            display: flex;
            height: 100vh;
            overflow: hidden;
            color: #333;
        }

        .container {
            display: flex;
            width: 100%;
            margin: 20px;
            height: calc(100vh - 40px);
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .sidebar {
            width: 250px;
            background-color: #f4f4f8;
            padding: 20px;
            display: flex;
            flex-direction: column;
            border-radius: 15px 0 0 15px;
            border-right: 1px solid #e0e0e0;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100%;
            position: relative;
        }

        .chat-box {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        .input-area {
            border-top: 1px solid #e0e0e0;
            padding: 20px;
            background-color: rgba(255, 255, 255, 0.7);
            border-radius: 0 0 15px 0;
        }

        .message-input {
            display: flex;
            position: relative;
        }

        #messageInput {
            flex: 1;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            font-size: 16px;
            outline: none;
            resize: none;
            height: 50px;
            max-height: 200px;
            transition: border-color 0.3s;
        }

        #messageInput:focus {
            border-color: #4a6cf7;
        }

        .send-button {
            background-color: #4a6cf7;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0 20px;
            margin-left: 10px;
            cursor: pointer;
            font-size: 16px;
            height: 50px;
            transition: background-color 0.3s;
        }

        .send-button:hover {
            background-color: #3a5be6;
        }

        .button-row {
            display: flex;
            margin-top: 10px;
            gap: 10px;
        }

        .aux-button {
            background-color: #f8f9fa;
            color: #333;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px 15px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
            flex: 1;
            text-align: center;
        }

        .aux-button:hover {
            background-color: #e9ecef;
        }

        .aux-button.active {
            background-color: #4a6cf7;
            color: white;
            border-color: #4a6cf7;
        }

        .user-message, .bot-message, .system-message {
            max-width: 80%;
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 15px;
            line-height: 1.5;
            word-wrap: break-word;
            position: relative;
            white-space: pre-wrap;
            overflow-wrap: break-word;
            word-break: break-word;
        }

        .user-message {
            align-self: flex-end;
            background-color: #4a6cf7;
            color: white;
            border-bottom-right-radius: 5px;
        }

        .bot-message {
            align-self: flex-start;
            background-color: #f0f0f0;
            color: #333;
            border-bottom-left-radius: 5px;
        }

        .system-message {
            align-self: center;
            background-color: #ffeeba;
            color: #856404;
            font-size: 14px;
            max-width: 90%;
            text-align: center;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
        }

        .message-content {
            white-space: pre-wrap;
            overflow-wrap: break-word;
            word-break: break-word;
            width: 100%;
        }

        .brand {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 30px;
            color: #4a6cf7;
            text-align: center;
        }

        .feature-button {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            margin: 5px 0;
            background-color: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .feature-button:hover {
            background-color: #f8f9fa;
        }

        .feature-button i {
            margin-right: 10px;
            font-size: 18px;
            color: #4a6cf7;
        }

        .spacer {
            flex: 1;
        }

        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #888;
            text-align: center;
        }

        .typing-indicator {
            align-self: flex-start;
            background-color: #f0f0f0;
            color: #333;
            padding: 10px 15px;
            border-radius: 15px;
            margin-bottom: 15px;
            font-size: 14px;
            display: none;
        }

        .typing-indicator span {
            display: inline-block;
            width: 8px;
            height: 8px;
            background-color: #666;
            border-radius: 50%;
            animation: typing 1s infinite;
            margin: 0 2px;
        }

        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }

        .stop-thinking-button {
            background-color: #ff4d4d;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 15px;
            margin-left: 10px;
            cursor: pointer;
            font-size: 14px;
            display: none;
            vertical-align: middle;
        }

        .stop-thinking-button:hover {
            background-color: #e60000;
        }

        .system-message.pdf-processing {
            background-color: #e6f7ff;
            border-left: 3px solid #1890ff;
            color: #0050b3;
            font-size: 14px;
            padding: 12px 15px;
            align-self: center;
            max-width: 90%;
            text-align: center;
            margin-bottom: 15px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 0.7; }
            50% { opacity: 1; }
            100% { opacity: 0.7; }
        }

        @keyframes typing {
            0% { opacity: 0.3; }
            50% { opacity: 1; }
            100% { opacity: 0.3; }
        }

        .file-drop-area {
            border: 2px dashed #ccc;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            margin-bottom: 15px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .file-drop-area:hover {
            background-color: rgba(74, 108, 247, 0.05);
        }

        .file-drop-area.active {
            border-color: #4a6cf7;
            background-color: rgba(74, 108, 247, 0.1);
        }

        #fileInput {
            display: none;
        }

        .progress-container {
            width: 100%;
            height: 4px;
            background-color: #f0f0f0;
            border-radius: 2px;
            margin: 10px 0;
        }

        .progress-bar {
            height: 100%;
            border-radius: 2px;
            background-color: #4a6cf7;
            width: 0%;
            transition: width 0.3s ease;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 15px;
            width: 100%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #aaa;
        }

        .close-modal:hover {
            color: #333;
        }

        .modal-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .form-group input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
        }

        .form-group input[type="text"]:focus {
            outline: none;
            border-color: #4a6cf7;
        }

        .modal-button {
            display: block;
            width: 100%;
            padding: 15px;
            background-color: #4a6cf7;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .modal-button:hover {
            background-color: #3a5be6;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                margin: 10px;
                height: calc(100vh - 20px);
            }

            .sidebar {
                width: auto;
                height: auto;
                border-radius: 15px 15px 0 0;
                border-right: none;
                border-bottom: 1px solid #e0e0e0;
                padding: 15px;
            }

            .brand {
                margin-bottom: 15px;
            }

            .feature-buttons {
                display: flex;
                overflow-x: auto;
                gap: 10px;
                padding-bottom: 10px;
            }

            .feature-button {
                flex: 0 0 auto;
                white-space: nowrap;
            }

            .main-content {
                border-radius: 0 0 15px 15px;
            }

            .user-message, .bot-message {
                max-width: 90%;
            }
        }

        .conversations-list {
            margin: 10px 0;
            overflow-y: auto;
            max-height: calc(100vh - 300px);
        }

        .conversation-item {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            margin: 5px 0;
            background-color: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .conversation-item:hover {
            background-color: #f8f9fa;
        }

        .conversation-item.active {
            background-color: #4a6cf7;
            color: white;
            border-color: #4a6cf7;
        }

        .conversation-title {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .delete-conversation {
            margin-left: 8px;
            opacity: 0.6;
            transition: opacity 0.2s;
        }

        .delete-conversation:hover {
            opacity: 1;
        }

        .empty-conversations-message {
            padding: 10px;
            text-align: center;
            color: #666;
            font-style: italic;
        }

        .conversations-list {
            margin: 10px 0;
            overflow-y: auto;
            max-height: calc(100vh - 300px);
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .conversation-item {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            background-color: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .conversation-item:hover {
            background-color: #f8f9fa;
        }

        .conversation-item.active {
            background-color: #4a6cf7;
            color: white;
            border-color: #4a6cf7;
        }

        .conversation-title {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 14px;
        }

        .delete-conversation {
            opacity: 0.6;
            transition: opacity 0.2s;
            margin-left: 8px;
            font-size: 12px;
        }

        .delete-conversation:hover {
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="brand">Deepchat AI</div>

            <div class="feature-button" id="newChatButton">
                <i>✚</i> New Chat
            </div>

            <div class="conversations-list" id="conversationsList">
                <!-- Conversation list will be dynamically loaded via JavaScript -->
            </div>

            <div class="feature-button" id="clearChatButton">
                <i>🗑️</i> Clear Chat
            </div>

            <div class="feature-button" id="uploadButton">
                <i>📁</i> Upload File
            </div>

            <div class="feature-button" id="morethinkButton">
                <i>💭</i> Morethink
            </div>

            <div class="feature-button" id="searchButton">
                <i>🔍</i> Search
            </div>

            <div class="spacer"></div>

            <div class="feature-button" id="configButton">
                <i>⚙️</i> API Settings
            </div>

            <div class="feature-button" id="testApiButton">
                <i>🔄</i> Test API
            </div>

            <div class="footer">
                Powered by Deepchat AI<br>
                Version v1.0.0
            </div>
        </div>

        <div class="main-content">
            <div class="chat-box" id="chatBox">
                <div class="system-message">
                    Welcome to Deepchat AI Chat System! Please enter your question or upload a file to start the conversation.
                </div>
            </div>

            <div class="input-area">
                <div class="file-drop-area" id="fileDropArea">
                    Drag and drop files here or click to upload (Supports PDF and text files, max 10MB)
                    <input type="file" id="fileInput" accept=".pdf,.txt,.md,.csv">
                </div>

                <div class="message-input">
                    <textarea id="messageInput" placeholder="Enter message..." onkeydown="if(event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); sendMessage(); }"></textarea>
                    <button class="send-button" id="sendButton">Send</button>
                </div>

                <div class="button-row">
                    <div class="aux-button" id="regenerateButton">Regenerate</div>
                    <div class="aux-button" id="copyButton">Copy Reply</div>
                    <div class="aux-button" id="clearButton">Clear Input</div>
                </div>
            </div>

            <div class="typing-indicator" id="typingIndicator">
                AI is thinking<span></span><span></span><span></span>
                <button class="stop-thinking-button" id="stopThinkingButton">Stop Thinking</button>
            </div>
        </div>
    </div>

    <!-- Configuration Modal -->
    <div class="modal" id="configModal">
        <div class="modal-content">
            <div class="close-modal" id="closeConfigModal">&times;</div>
            <div class="modal-title">API Configuration</div>

            <div class="form-group">
                <label for="apiKey">API Key</label>
                <input type="text" id="apiKey" placeholder="sk-...">
            </div>

            <div class="form-group">
                <label for="apiBaseUrl">API Base URL</label>
                <input type="text" id="apiBaseUrl" placeholder="http://127.0.0.1:8000">
            </div>

            <div class="form-group">
                <label for="wsBaseUrl">WebSocket Base URL</label>
                <input type="text" id="wsBaseUrl" placeholder="ws://127.0.0.1:8000">
            </div>

            <div class="form-group">
                <label for="modelName">Model Name</label>
                <input type="text" id="modelName" placeholder="deepseek-chat">
            </div>

            <button class="modal-button" id="saveConfigButton">Save Configuration</button>
        </div>
    </div>

    <!-- Test Result Modal -->
    <div class="modal" id="testResultModal">
        <div class="modal-content">
            <div class="close-modal" id="closeTestResultModal">&times;</div>
            <div class="modal-title">API Test Results</div>
            <div id="testResultContent" style="white-space: pre-wrap; max-height: 400px; overflow-y: auto;"></div>
        </div>
    </div>

    <script>
        // Configuration variables
        let config = {
            apiKey: localStorage.getItem('apiKey') || '',
            apiBaseUrl: localStorage.getItem('apiBaseUrl') || 'http://127.0.0.1:8000',
            wsBaseUrl: localStorage.getItem('wsBaseUrl') || 'ws://127.0.0.1:8000',
            modelName: localStorage.getItem('modelName') || 'deepseek-chat',
            userId: <?php echo json_encode($user_id); ?>,
            username: <?php echo json_encode($username); ?>,
            conversationId: <?php echo $conversation_id ? json_encode($conversation_id) : 'null'; ?>
        };

        // Global variables
        let activeWs = null;
        let currentTaskId = null;
        let lastBotMessage = null;
        let chatHistory = [];
        let pendingFile = null;
        let isInitialized = false;

        // DOM elements
        const chatBox = document.getElementById('chatBox');
        const messageInput = document.getElementById('messageInput');
        const sendButton = document.getElementById('sendButton');
        const typingIndicator = document.getElementById('typingIndicator');
        const fileInput = document.getElementById('fileInput');
        const fileDropArea = document.getElementById('fileDropArea');
        const configModal = document.getElementById('configModal');
        const configButton = document.getElementById('configButton');
        const closeConfigModal = document.getElementById('closeConfigModal');
        const apiKeyInput = document.getElementById('apiKey');
        const apiBaseUrlInput = document.getElementById('apiBaseUrl');
        const wsBaseUrlInput = document.getElementById('wsBaseUrl');
        const modelNameInput = document.getElementById('modelName');
        const saveConfigButton = document.getElementById('saveConfigButton');
        const newChatButton = document.getElementById('newChatButton');
        const clearChatButton = document.getElementById('clearChatButton');
        const uploadButton = document.getElementById('uploadButton');
        const morethinkButton = document.getElementById('morethinkButton');
        const regenerateButton = document.getElementById('regenerateButton');
        const copyButton = document.getElementById('copyButton');
        const clearButton = document.getElementById('clearButton');
        const testApiButton = document.getElementById('testApiButton');
        const testResultModal = document.getElementById('testResultModal');
        const closeTestResultModal = document.getElementById('closeTestResultModal');
        const testResultContent = document.getElementById('testResultContent');
        const stopThinkingButton = document.getElementById('stopThinkingButton');
        const finishedTasks = new Set();

        // Check server connection
        async function checkServerConnection() {
            try {
                const response = await fetch(`${config.apiBaseUrl}/api/ping`, {
                    method: 'GET',
                    headers: { 'Content-Type': 'application/json' }
                });

                if (response.ok) {
                    console.log('Server connection normal');
                    return true;
                } else {
                    console.error('Server connection abnormal:', response.status);
                    return false;
                }
            } catch (error) {
                console.error('Server connection failed:', error);
                return false;
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', async () => {
            console.log('Page loaded, checking configuration status:');
            console.log('User ID:', config.userId);
            console.log('Current conversation ID:', config.conversationId);
            console.log('PHP session ID:', '<?php echo session_id(); ?>');

            // Reset state
            chatBox.innerHTML = '';
            chatHistory = [];
            lastBotMessage = null;
            currentTaskId = null;
            if (activeWs) {
                activeWs.close();
                activeWs = null;
            }

            if (!config.userId) {
                console.error('User ID not found, redirecting to login page');
                window.location.href = 'user_login.php';
                return;
            }

            // Verify current conversation ID belongs to current user
            if (config.conversationId) {
                try {
                    const response = await fetch(`db_verify_conversation.php?conversation_id=${config.conversationId}&user_id=${config.userId}`);
                    const result = await response.json();
                    if (!result.valid) {
                        console.error('Current conversation does not belong to this user, resetting conversation ID');
                        config.conversationId = null;
                        // Update session status
                        await fetch('update_session.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                conversation_id: null
                            })
                        });
                    }
                } catch (error) {
                    console.error('Failed to verify conversation ownership:', error);
                    config.conversationId = null;
                }
            }

            // First load conversation list
            await loadConversations();

            // If there is a current conversation ID, load conversation history
            if (config.conversationId) {
                await loadChatHistory();
            } else {
                // If there is no current conversation, show welcome message, not saved to database
                addMessageToChat('system', 'Welcome to Deepchat AI Chat System! Please enter your question or upload a file to start the conversation.', false, false);
            }

            // Fill configuration form
            apiKeyInput.value = config.apiKey;
            apiBaseUrlInput.value = config.apiBaseUrl;
            wsBaseUrlInput.value = config.wsBaseUrl;
            modelNameInput.value = config.modelName;

            // Set event listeners
            setupEventListeners();
            
            isInitialized = true;
        });

        // Set event listeners
        function setupEventListeners() {
            // Remove existing event listeners (if any)
            sendButton.removeEventListener('click', sendMessage);
            fileInput.removeEventListener('change', handleFileChange);
            uploadButton.removeEventListener('click', () => fileInput.click());
            configButton.removeEventListener('click', () => configModal.style.display = 'flex');
            closeConfigModal.removeEventListener('click', () => configModal.style.display = 'none');
            saveConfigButton.removeEventListener('click', saveConfig);
            newChatButton.removeEventListener('click', startNewChat);
            clearChatButton.removeEventListener('click', clearChat);
            morethinkButton.removeEventListener('click', toggleModel);
            regenerateButton.removeEventListener('click', regenerateLastMessage);
            copyButton.removeEventListener('click', copyLastReply);
            clearButton.removeEventListener('click', clearInputField);
            testApiButton.removeEventListener('click', testApi);
            closeTestResultModal.removeEventListener('click', () => testResultModal.style.display = 'none');
            stopThinkingButton.removeEventListener('click', stopThinking);

            // Add new event listeners
            sendButton.addEventListener('click', sendMessage);
            fileInput.addEventListener('change', handleFileChange);
            uploadButton.addEventListener('click', () => fileInput.click());
            configButton.addEventListener('click', () => configModal.style.display = 'flex');
            closeConfigModal.addEventListener('click', () => configModal.style.display = 'none');
            saveConfigButton.addEventListener('click', saveConfig);
            newChatButton.addEventListener('click', startNewChat);
            clearChatButton.addEventListener('click', clearChat);
            morethinkButton.addEventListener('click', toggleModel);
            regenerateButton.addEventListener('click', regenerateLastMessage);
            copyButton.addEventListener('click', copyLastReply);
            clearButton.addEventListener('click', clearInputField);
            testApiButton.addEventListener('click', testApi);
            closeTestResultModal.addEventListener('click', () => testResultModal.style.display = 'none');
            stopThinkingButton.addEventListener('click', stopThinking);

            // File drag and drop area events
            setupFileDropArea();
        }

        // Set file drag and drop area events
        function setupFileDropArea() {
            fileDropArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                fileDropArea.classList.add('active');
            });

            fileDropArea.addEventListener('dragleave', () => {
                fileDropArea.classList.remove('active');
            });

            fileDropArea.addEventListener('drop', (e) => {
                e.preventDefault();
                fileDropArea.classList.remove('active');

                if (e.dataTransfer.files.length) {
                    fileInput.files = e.dataTransfer.files;
                    handleFileChange();
                }
            });

            fileDropArea.addEventListener('click', () => {
                fileInput.click();
            });
        }

        // Load chat history
        async function loadChatHistory() {
            if (!config.conversationId) return;

            try {
                const response = await fetch(`db_get_messages.php?conversation_id=${config.conversationId}`);
                const messages = await response.json();

                // Clear chat box and history
                chatBox.innerHTML = '';
                chatHistory = [];

                if (messages.length === 0) {
                    // If no history messages, show welcome message
                    addMessageToChat('system', 'Welcome to Deepchat AI Chat System! Please enter your question or upload a file to start the conversation.', false, false);
                    return;
                }

                // Add history messages to chat box, setting shouldSaveToDb to false
                messages.forEach(msg => {
                    const type = msg.role === 'assistant' ? 'bot' : msg.role;
                    addMessageToChat(type, msg.content, false, false); // Last parameter false means not saved to database
                    chatHistory.push({type: type, content: msg.content});
                });

                // If there are bot messages, save the last one
                const botMessages = messages.filter(msg => msg.role === 'assistant');
                if (botMessages.length > 0) {
                    lastBotMessage = botMessages[botMessages.length - 1].content;
                }
            } catch (error) {
                console.error('Failed to load chat history:', error);
                addMessageToChat('system', 'Failed to load chat history', true, false);
            }
        }

        // Send message
        async function sendMessage() {
            if (!isInitialized) {
                console.error('System not fully initialized');
                addMessageToChat('system', 'System is initializing, please try again later', true);
                return;
            }

            const message = messageInput.value.trim();
            if (!message) return;

            // Disable send button to prevent duplicate sends
            sendButton.disabled = true;
            
            try {
                // Check user ID
                if (!config.userId) {
                    console.error('User ID not set');
                    addMessageToChat('system', 'Session expired, please log in again', true);
                    window.location.href = 'user_login.php';
                    return;
                }

                // First check server connection
                if (!await checkServerConnection()) {
                    addMessageToChat('system', 'Unable to connect to server, please check if server is running', true);
                    return;
                }

                // Add user message to chat interface
                addMessageToChat('user', message);

                // Clear input field and reset height
                messageInput.value = '';
                messageInput.style.height = 'auto';

                try {
                    // If no current conversation, create new one
                    if (!config.conversationId) {
                        console.log('Creating new conversation...');
                        
                        const createResponse = await fetch('db_start_conversation.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                user_id: config.userId,
                                title: message.substring(0, 50)
                            })
                        });

                        const createResult = await createResponse.json();
                        console.log('Create conversation response:', createResult);

                        if (createResult.status !== 'success' || !createResult.conversation_id) {
                            throw new Error(createResult.message || 'Failed to create conversation');
                        }

                        config.conversationId = createResult.conversation_id;
                        console.log('New conversation ID:', config.conversationId);

                        // Update session ID in PHP session
                        await fetch('update_session.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                conversation_id: createResult.conversation_id
                            })
                        });

                        // Create new conversation button
                        await updateConversationsList(createResult.conversation_id, message.substring(0, 50));
                    }

                    // Get last four messages
                    const messagesResponse = await fetch(`db_get_messages.php?conversation_id=${config.conversationId}`);
                    const messages = await messagesResponse.json();
                    const recentMessages = messages.slice(-4).map(msg => msg.content).join('\n');
                    
                    // Combine recent messages and new message
                    const combinedMessage = recentMessages ? `${recentMessages}\n\nNew question: ${message}` : message;

                    // Save user message to database
                    console.log('Saving message to database:', {
                        conversation_id: config.conversationId,
                        role: 'user',
                        content: message
                    });

                    const saveResponse = await fetch('db_add_message.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            conversation_id: config.conversationId,
                            role: 'user',
                            content: message
                        })
                    });

                    const saveResult = await saveResponse.json();
                    console.log('Save message response:', saveResult);

                    if (saveResult.status !== 'success') {
                        throw new Error(saveResult.message || 'Failed to save message');
                    }

                    // Show "typing" indicator
                    typingIndicator.style.display = 'block';
                    stopThinkingButton.style.display = 'inline-block';

                    // Generate task ID and call API
                    const taskId = generateUUID();
                    currentTaskId = taskId;
                    console.log(`Generated new task ID: ${taskId}`);

                    // Create WebSocket connection
                    createWebSocketConnection(taskId);

                    // Wait to ensure WebSocket connection is established
                    await new Promise(resolve => setTimeout(resolve, 500));

                    // Send chat message to API
                    console.log(`Sending message to API, task ID: ${taskId}`);
                    const response = await fetch(`${config.apiBaseUrl}/api/chat`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            message: combinedMessage,
                            task_id: taskId
                        })
                    });

                    if (!response.ok) {
                        throw new Error(`API error: ${response.status}`);
                    }

                    const result = await response.json();
                    console.log(`API response successful: ${JSON.stringify(result)}`);

                } catch (error) {
                    console.error('Failed to send message:', error);
                    addMessageToChat('system', `Error: ${error.message}`, true);
                    typingIndicator.style.display = 'none';
                    stopThinkingButton.style.display = 'none';
                }
            } finally {
                // Re-enable send button
                sendButton.disabled = false;
            }
        }

        // Update conversation list
        async function updateConversationsList(conversationId, title) {
            const conversationsList = document.getElementById('conversationsList');
            
            // Remove "no history conversations" message
            const emptyMessage = conversationsList.querySelector('.empty-conversations-message');
            if (emptyMessage) {
                emptyMessage.remove();
            }

            // Create new conversation item
            const item = document.createElement('div');
            item.className = 'conversation-item active';
            
            const titleDiv = document.createElement('div');
            titleDiv.className = 'conversation-title';
            titleDiv.textContent = title || 'New Conversation';
            titleDiv.dataset.id = conversationId;
            
            const deleteBtn = document.createElement('span');
            deleteBtn.className = 'delete-conversation';
            deleteBtn.innerHTML = '🗑️';
            deleteBtn.onclick = (e) => {
                e.stopPropagation();
                deleteConversation(conversationId);
            };
            
            item.appendChild(titleDiv);
            item.appendChild(deleteBtn);
            item.onclick = () => switchConversation(conversationId);
            
            // Remove active class from other conversation items
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Add new conversation to list at the beginning
            if (conversationsList.firstChild) {
                conversationsList.insertBefore(item, conversationsList.firstChild);
            } else {
                conversationsList.appendChild(item);
            }
        }

        // Create WebSocket connection
        function createWebSocketConnection(taskId) {
            // Close previous connection
            if (activeWs) {
                activeWs.close();
                activeWs = null;
            }

            // Create new connection
            const ws = new WebSocket(`${config.wsBaseUrl}/ws/${taskId}`);
            activeWs = ws;

            ws.onopen = () => {
                console.log(`WebSocket connection opened: ${taskId}`);
            };

            ws.onmessage = async (event) => {
                const data = JSON.parse(event.data);
                console.log('WebSocket message:', data);

                if (data.status === 'completed' && data.reply) {
                    // Received complete reply
                    if (finishedTasks.has(taskId)) return;
                    typingIndicator.style.display = 'none';
                    stopThinkingButton.style.display = 'none';
                    
                    // Show AI reply, setting shouldSaveToDb to true because this is new message
                    addMessageToChat('bot', data.reply, false, true);
                    finishedTasks.add(taskId);
                    
                    // Update conversation list
                    await loadConversations();
                } else if (data.type === 'connection_status') {
                    // Handle connection status message
                    console.log(`Connection status: ${data.status} - ${data.task_id}`);
                } else if (data.status && data.progress !== undefined) {
                    // Progress update
                    console.log(`Task progress: ${data.progress}% - ${data.status}`);

                    // Handle PDF special message
                    if (data.message && data.status.includes("PDF")) {
                        // Show PDF processing status message
                        const existingMessage = document.querySelector('.system-message.pdf-processing');
                        if (existingMessage) {
                            // Update existing message
                            const contentElem = existingMessage.querySelector('.message-content');
                            if (contentElem) {
                                contentElem.textContent = data.message;
                            }
                        } else {
                            // Create new message
                            const messageElem = document.createElement('div');
                            messageElem.className = 'system-message pdf-processing';

                            const contentElem = document.createElement('div');
                            contentElem.className = 'message-content';
                            contentElem.textContent = data.message;
                            messageElem.appendChild(contentElem);

                            chatBox.appendChild(messageElem);
                            chatBox.scrollTop = chatBox.scrollHeight;
                        }
                    }
                } else if (data.error) {
                    // Handle error
                    typingIndicator.style.display = 'none';
                    stopThinkingButton.style.display = 'none';
                    addMessageToChat('system', `Error: ${data.error}`, true);
                } else if (data.status && (data.type === 'pdf' || data.type === 'pdf_error' || data.type === 'pdf_timeout' || data.type === 'pdf_unsupported')) {
                    // Handle PDF file response
                    console.log('Received PDF processing result:', data);
                    typingIndicator.style.display = 'none';
                    stopThinkingButton.style.display = 'none';

                    // Show different messages based on different states of PDF processing
                    if (data.type === 'pdf_timeout') {
                        // PDF processing timed out, show friendly notice
                        addMessageToChat('system', data.content);
                        addMessageToChat('system', 'System is continuing to process PDF in the background, you can reopen the chat later to view the result.', false);
                    } else if (data.type === 'pdf_error') {
                        // PDF processing failed, show friendly notice instead of error
                        addMessageToChat('system', data.content);
                    } else if (data.type === 'pdf_unsupported') {
                        // PDF不支持，正常显示消息
                        addMessageToChat('system', data.content);
                    } else {
                        // 正常处理的PDF内容
                        addMessageToChat('system', `已上传: ${data.file_name}`);
                        // 检查content是否存在并且不为空
                        if (data.content && data.content.trim() !== '') {
                            console.log('添加PDF内容到聊天, 长度:', data.content.length);
                            addMessageToChat('bot', data.content);
                        } else {
                            console.error('PDF内容为空或不存在');
                            addMessageToChat('system', '无法显示PDF内容，内容为空', true);
                        }
                    }

                    // 移除之前的PDF处理消息
                    const pdfProcessingMsg = document.querySelector('.system-message.pdf-processing');
                    if (pdfProcessingMsg) {
                        pdfProcessingMsg.remove();
                    }
                } else {
                    // 未处理的消息类型，记录以便调试
                    console.log('未处理的WebSocket消息类型:', data);
                }
            };

            ws.onerror = (error) => {
                console.error('WebSocket错误:', error);
                // 不要立即显示错误，而是尝试重新连接
                console.log('尝试通过API获取结果...');
                setTimeout(() => retrieveResult(taskId), 1000);
            };

            ws.onclose = (event) => {
                console.log(`WebSocket连接已关闭, 代码: ${event.code}, 原因: ${event.reason}`);
                if (activeWs === ws) {
                    activeWs = null;
                }

                // 如果连接过早关闭且没有收到任何回复，尝试重新连接或获取结果
                if (typingIndicator.style.display === 'block') {
                    console.log(`WebSocket连接已关闭但任务可能仍在进行, 尝试重新连接或获取结果...`);

                    // 先尝试重新连接WebSocket
                    setTimeout(() => {
                        if (typingIndicator.style.display === 'block') {
                            console.log(`尝试重新连接WebSocket: ${taskId}`);
                            createWebSocketConnection(taskId);

                            // 如果重连后短时间内仍无结果，尝试通过API获取
                            setTimeout(() => {
                                if (typingIndicator.style.display === 'block') {
                                    console.log('WebSocket重连后仍无回复，尝试通过API获取结果...');
                                    if (!finishedTasks.has(taskId)) retrieveResult(taskId);
                                }
                            }, 3000);
                        }
                    }, 1000);
                }
            };

            // 增加ping来保持连接活跃
            const pingInterval = setInterval(() => {
                if (ws.readyState === WebSocket.OPEN) {
                    console.log('发送ping来保持连接');
                    ws.send(JSON.stringify({type: 'ping'}));
                } else {
                    clearInterval(pingInterval);
                }
            }, 30000); // 每30秒ping一次

            return ws;
        }

        // 通过API获取任务结果
        async function retrieveResult(taskId) {
            try {
                // === 防抖：一旦开始请求就立即标记，避免并发调用 ===;
                if (finishedTasks.has(taskId)) return;
                finishedTasks.add(taskId);
                console.log(`[RESULT] pull ${taskId}`);
                const response = await fetch(`${config.apiBaseUrl}/api/result/${taskId}`);

                if (!response.ok) {
                    throw new Error(`API错误: ${response.status}`);
                }

                const result = await response.json();
                console.log(`获取到任务结果:`, result);

                // 隐藏加载指示器
                typingIndicator.style.display = 'none';
                stopThinkingButton.style.display = 'none';

                // 检查是否是PDF结果
                if (result.type && (result.type === 'pdf' || result.type.startsWith('pdf_'))) {
                    console.log('通过API获取到PDF结果');
                    // 使用PDF显示函数
                    displayPdfResult(result);
                    return;
                }

                // 处理普通文本回复
                if (result.reply) {
                    // 收到有效回复
                    addMessageToChat('bot', result.reply);
                    console.log('通过API成功获取到回复');

                } else if (result.content) {
                    // 内容字段中可能包含结果
                    addMessageToChat('bot', result.content);
                    console.log('通过API成功获取到内容');
                } else {
                    // 结果格式不正确
                    addMessageToChat('system', '无法获取完整回复，请重试', true);
                }
            } catch (error) {
                finishedTasks.delete(taskId);
                console.error('获取结果失败:', error);
                typingIndicator.style.display = 'none';
                stopThinkingButton.style.display = 'none';
                addMessageToChat('system', `获取结果失败: ${error.message}`, true);
            }
        }

        // Handle file upload
        async function handleFileChange() {
            const file = fileInput.files[0];
            if (!file) return;

            // File type check
            const validTypes = [
                'application/pdf',
                'text/plain',
                'text/csv',
                'text/markdown'
            ];

            if (!validTypes.includes(file.type) &&
                !file.name.endsWith('.pdf') &&
                !file.name.endsWith('.txt') &&
                !file.name.endsWith('.md') &&
                !file.name.endsWith('.csv')) {
                addMessageToChat('system', 'Error: Unsupported file type. Please upload PDF or text files.', true);
                return;
            }

            // File size check (10MB)
            if (file.size > 10 * 1024 * 1024) {
                addMessageToChat('system', 'Error: File too large. Please upload files smaller than 10MB.', true);
                return;
            }

            // First check server connection
            if (!await checkServerConnection()) {
                addMessageToChat('system', 'Unable to connect to server, please check if server is running', true);
                return;
            }

            // Show uploading message
            addMessageToChat('system', `Uploading file: ${file.name}...`);

            // If PDF file, show special notice
            if (file.type === 'application/pdf' || file.name.endsWith('.pdf')) {
                addMessageToChat('system', 'Note: PDF processing may take longer depending on file size and content complexity. Please be patient.');
            }

            // Show "typing" indicator
            typingIndicator.style.display = 'block';
            stopThinkingButton.style.display = 'inline-block';

            try {
                // Generate task ID
                const taskId = generateUUID();
                currentTaskId = taskId;
                console.log(`Generated new task ID: ${taskId} (file upload)`);

                // Create WebSocket connection
                createWebSocketConnection(taskId);

                // Wait to ensure WebSocket connection is established
                await new Promise(resolve => setTimeout(resolve, 500));

                // Create FormData object
                const formData = new FormData();
                formData.append('file', file);
                formData.append('task_id', taskId);  // Add task ID

                // Send upload request
                console.log(`Sending file upload request, task ID: ${taskId}`);
                const response = await fetch(`${config.apiBaseUrl}/api/upload`, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`Upload failed: ${response.status} ${await response.text()}`);
                }

                const result = await response.json();
                console.log(`File upload API response successful: ${JSON.stringify(result)}`);

                // Set a longer timeout specifically for PDF files
                if (file.type === 'application/pdf' || file.name.endsWith('.pdf')) {
                    // For PDF files, use 5-minute timeout
                    let resultReceived = false;

                    // Set up polling mechanism to check results periodically
                    const pollInterval = setInterval(async () => {
                        if (resultReceived || typingIndicator.style.display !== 'block') {
                            clearInterval(pollInterval);
                            return;
                        }

                        console.log(`Polling check for task ${taskId} PDF processing results...`);
                        try {
                            const pollResponse = await fetch(`${config.apiBaseUrl}/api/result/${taskId}`);
                            if (pollResponse.ok) {
                                const pollResult = await pollResponse.json();

                                // Check for valid PDF result
                                if (pollResult.type === 'pdf' && pollResult.content && pollResult.content.trim() !== '') {
                                    console.log('PDF result found through polling');
                                    resultReceived = true;

                                    // Use displayPdfResult to show result
                                    typingIndicator.style.display = 'none';
                                    stopThinkingButton.style.display = 'none';
                                    displayPdfResult(pollResult);

                                    // Automatically send extracted text as question
                                    if (pollResult.content && pollResult.content.trim() !== '') {
                                        messageInput.value = pollResult.content;
                                        sendMessage();
                                    }

                                    // Clear polling
                                    clearInterval(pollInterval);
                                }
                            }
                        } catch (pollError) {
                            console.error('Polling error:', pollError);
                        }
                    }, 10000); // Poll every 10 seconds

                    // Main timeout control
                    setTimeout(() => {
                        if (!resultReceived && typingIndicator.style.display === 'block') {
                            console.log("PDF processing in progress, maintaining connection...");
                            // Don't show timeout error, only log in console
                        }
                    }, 300000); // 5 minutes
                } else {
                    // For other file types, use normal timeout
                    setTimeout(() => {
                        if (typingIndicator.style.display === 'block') {
                            console.log("Response timeout, possible WebSocket connection issue");
                            typingIndicator.style.display = 'none';
                            stopThinkingButton.style.display = 'none';
                            addMessageToChat('system', 'Response timeout, please try again or check server status', true);
                        }
                    }, 60000); // 1 minute
                }

            } catch (error) {
                console.error('File upload failed:', error);
                addMessageToChat('system', `Error: ${error.message}`, true);
                typingIndicator.style.display = 'none';
                stopThinkingButton.style.display = 'none';
            }

            // Clear file input
            fileInput.value = '';
        }

        // Add message to chat
        function addMessageToChat(type, content, isError = false, shouldSaveToDb = true) {
            // Check if message already exists
            const existingMessages = chatBox.querySelectorAll(`.${type}-message`);
            for (let msg of existingMessages) {
                if (msg.querySelector('.message-content').textContent === content) {
                    console.log('Message already exists, skipping addition');
                    return;
                }
            }

            const messageElem = document.createElement('div');
            messageElem.className = type + '-message';

            if (isError) {
                messageElem.classList.add('error-message');
            }

            const contentElem = document.createElement('div');
            contentElem.className = 'message-content';
            contentElem.textContent = content;
            messageElem.appendChild(contentElem);

            chatBox.appendChild(messageElem);

            // Auto scroll to bottom
            chatBox.scrollTop = chatBox.scrollHeight;

            // If it's a bot message, save the last reply
            if (type === 'bot') {
                lastBotMessage = content;
            }

            // Add to chat history
            chatHistory.push({type: type, content: content});

            // Only save to database if shouldSaveToDb is true and it's not an error message
            if (shouldSaveToDb && config.conversationId && !isError) {
                const messageRole = type === 'bot' ? 'assistant' : type;
                fetch('db_add_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        conversation_id: config.conversationId,
                        role: messageRole,
                        content: content
                    })
                }).catch(error => {
                    console.error('Failed to save message to database:', error);
                });
            }
        }

        // Clear chat
        async function clearChat() {
            // Clear chat box content and history
            chatBox.innerHTML = '';
            chatHistory = [];
            lastBotMessage = null;
            
            // Add welcome message, not saved to database
            addMessageToChat('system', 'Welcome to Deepchat AI dialogue system! Please enter your question or upload a file to start the conversation.', false, false);
            
            // Reset current conversation ID
            config.conversationId = null;
            
            // Update session ID in PHP session
            await fetch('update_session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    conversation_id: null
                })
            });

            // Remove current active conversation highlight
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.remove('active');
            });
        }

        // Start new chat
        function startNewChat() {
            // Close current WebSocket connection
            if (activeWs) {
                activeWs.close();
                activeWs = null;
            }

            clearChat();
        }

        // Save configuration
        function saveConfig() {
            config.apiKey = apiKeyInput.value.trim();
            config.apiBaseUrl = apiBaseUrlInput.value.trim();
            config.wsBaseUrl = wsBaseUrlInput.value.trim();
            config.modelName = modelNameInput.value.trim();

            // Save to local storage
            localStorage.setItem('apiKey', config.apiKey);
            localStorage.setItem('apiBaseUrl', config.apiBaseUrl);
            localStorage.setItem('wsBaseUrl', config.wsBaseUrl);
            localStorage.setItem('modelName', config.modelName);

            // Close modal
            configModal.style.display = 'none';

            // Show success message
            addMessageToChat('system', 'Configuration saved.');
        }

        // Regenerate last message
        function regenerateLastMessage() {
            const lastUserMessage = chatHistory.filter(msg => msg.type === 'user').pop();
            if (lastUserMessage) {
                // Remove last bot message
                const lastBotIndex = chatHistory.findIndex(msg => msg.type === 'bot');
                if (lastBotIndex !== -1) {
                    chatHistory.splice(lastBotIndex, 1);
                    // Update UI
                    const botMessages = document.querySelectorAll('.bot-message');
                    if (botMessages.length > 0) {
                        botMessages[botMessages.length - 1].remove();
                    }
                }

                // Resend last user message
                messageInput.value = lastUserMessage.content;
                sendMessage();
            } else {
                addMessageToChat('system', 'No message found to regenerate.');
            }
        }

        // Copy last reply
        function copyLastReply() {
            if (lastBotMessage) {
                navigator.clipboard.writeText(lastBotMessage)
                    .then(() => {
                        addMessageToChat('system', 'Reply copied to clipboard.');
                    })
                    .catch(err => {
                        console.error('Failed to copy text:', err);
                        addMessageToChat('system', 'Copy failed. Please manually select text and copy.', true);
                    });
            } else {
                addMessageToChat('system', 'No reply to copy.');
            }
        }

        // Clear input field
        function clearInputField() {
            messageInput.value = '';
            messageInput.focus();
        }

        // Test API connection
        async function testApi() {
            // Show testing message
            addMessageToChat('system', 'Testing API connection...');

            // First check server connection
            if (!await checkServerConnection()) {
                addMessageToChat('system', 'Unable to connect to server, please check if server is running', true);

                // Show test result modal
                testResultContent.textContent = `❌ API connection test failed\n\n🔸 Error information: Unable to connect to server\n\nPossible reasons:\n- Backend service not running\n- Incorrect API base URL (${config.apiBaseUrl})\n- Network connection issue`;
                testResultModal.style.display = 'flex';

                return;
            }

            try {
                const response = await fetch(`${config.apiBaseUrl}/api/test`);

                if (!response.ok) {
                    throw new Error(`API error: ${response.status}`);
                }

                const result = await response.json();
                console.log('API test results:', result);

                // Prepare test result content
                let resultContent = '';

                if (result.status === 'success') {
                    resultContent += `✅ API connection test successful\n\n`;
                    resultContent += `🔹 Server status: ${result.server_info.server_status}\n`;
                    resultContent += `🔹 Model: ${result.server_info.model}\n`;
                    resultContent += `🔹 API base URL: ${result.server_info.api_base}\n`;
                    resultContent += `🔹 Response time: ${result.server_info.api_response_time_ms.toFixed(2)}ms\n\n`;
                    resultContent += `🔹 API reply: "${result.reply}"\n`;
                } else {
                    resultContent += `❌ API connection test failed\n\n`;
                    resultContent += `🔸 Error information: ${result.message}\n`;

                    if (result.server_info) {
                        resultContent += `\nServer information:\n`;
                        resultContent += `🔸 Server status: ${result.server_info.server_status}\n`;
                        resultContent += `🔸 Model: ${result.server_info.model}\n`;
                        resultContent += `🔸 API base URL: ${result.server_info.api_base}\n`;

                        if (result.server_info.api_error) {
                            resultContent += `🔸 API error: ${result.server_info.api_error}\n`;
                        }
                    }

                    if (result.detail) {
                        resultContent += `\nDetailed error information: ${result.detail}\n`;
                    }
                }

                // Show test result modal
                testResultContent.textContent = resultContent;
                testResultModal.style.display = 'flex';

                // Display brief result in chat
                if (result.status === 'success') {
                    addMessageToChat('system', 'API test successful!');
                } else {
                    addMessageToChat('system', `API test failed: ${result.message}`, true);
                }

            } catch (error) {
                console.error('API test failed:', error);

                // Display error in chat
                addMessageToChat('system', `API test failed: ${error.message}`, true);

                // Show test result modal
                testResultContent.textContent = `❌ API connection test failed\n\n🔸 Error information: ${error.message}\n\nPossible reasons:\n- Backend service not running\n- Incorrect API base URL (${config.apiBaseUrl})\n- Network connection issue`;
                testResultModal.style.display = 'flex';
            }
        }

        // Generate UUID
        function generateUUID() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                const r = Math.random() * 16 | 0;
                const v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        }

        // Stop thinking/generating
        function stopThinking() {
            console.log("User clicked stop thinking button");

            // If WebSocket connection exists, send stop message
            if (activeWs && activeWs.readyState === WebSocket.OPEN) {
                try {
                    console.log(`Sending stop thinking message to WebSocket: ${currentTaskId}`);
                    activeWs.send(JSON.stringify({
                        type: "stop_thinking",
                        task_id: currentTaskId
                    }));
                } catch (error) {
                    console.error("Failed to send stop thinking message:", error);
                }
            }

            // Close current WebSocket connection
            if (activeWs) {
                console.log(`Closing WebSocket connection: ${currentTaskId}`);
                activeWs.close();
                activeWs = null;
            }

            // Hide loading indicator
            typingIndicator.style.display = 'none';
            stopThinkingButton.style.display = 'none';

            // Add system message
            addMessageToChat('system', 'Current generation stopped');
        }

        // Display PDF result
        function displayPdfResult(resultJson) {
            try {
                // If input is null or undefined, return failure
                if (!resultJson) {
                    console.error('Failed to display PDF result: result is empty');
                    addMessageToChat('system', 'PDF processing result is empty', true);
                    return false;
                }

                // Parse string as JSON object (if not already parsed)
                const result = typeof resultJson === 'string' ? JSON.parse(resultJson) : resultJson;

                console.log('Manual display of PDF processing result:', result);

                // Validate necessary fields
                if (!result.type) {
                    console.error('PDF result missing type field:', result);
                    addMessageToChat('system', 'Incorrect format of PDF processing result', true);
                    return false;
                }

                // Hide any loading indicators
                typingIndicator.style.display = 'none';
                stopThinkingButton.style.display = 'none';

                // Display uploaded file name
                if (result.file_name) {
                    addMessageToChat('system', `Uploaded: ${result.file_name}`);
                }

                // Handle different responses based on type
                if (result.type === 'pdf_timeout') {
                    addMessageToChat('system', result.content);
                    addMessageToChat('system', 'System is continuing to process PDF in the background, you can reopen the chat later to view the result.', false);
                } else if (result.type === 'pdf_error') {
                    addMessageToChat('system', result.content);
                } else if (result.type === 'pdf_unsupported') {
                    addMessageToChat('system', result.content);
                } else if (result.type === 'pdf') {
                    // Check if content exists
                    if (result.content && result.content.trim() !== '') {
                        console.log('Display PDF content, length:', result.content.length);
                        // Display PDF content as bot reply
                        addMessageToChat('bot', result.content);
                    } else {
                        console.error('PDF content is empty:', result);
                        addMessageToChat('system', 'PDF content is empty, please try uploading again or contact administrator', true);
                    }
                } else {
                    console.warn('Unknown PDF result type:', result.type);
                    // Try to display content, regardless of type
                    if (result.content) {
                        addMessageToChat('bot', result.content);
                    } else {
                        addMessageToChat('system', 'PDF processing result unknown, but no content to display', true);
                    }
                }

                // Remove processing message
                const pdfProcessingMsg = document.querySelector('.system-message.pdf-processing');
                if (pdfProcessingMsg) {
                    pdfProcessingMsg.remove();
                }

                return true;
            } catch (e) {
                console.error('Error parsing or displaying PDF result:', e);
                console.error('Original result:', resultJson);
                addMessageToChat('system', `Error displaying PDF result: ${e.message}`, true);
                return false;
            }
        }

        // Add sample data for testing
        // You can enter displayLatestPdfResult() in browser console to display test results
        function displayLatestPdfResult() {
            const pdfResult = {"status": "success", "type": "pdf", "task_id": "783fe527-7b11-4fc7-9677-afe42220135d", "file_name": "783fe527-7b11-4fc7-9677-afe42220135d_fa9010e257bbb7782f3a4b1b3dacd4be.pdf", "content": "**Abstract**  \n• PG-SAM integrates medical LLMs (Large Language Models) to enhance multi-organ segmentation accuracy  \n• Proposed fine-grained modality prior aligner bridges domain gaps between text and medical images  \n• Multi-level feature fusion and iterative mask optimizer improve boundary precision  \n• Achieves state-of-the-art performance on Synapse dataset with $84.79\\%$ mDice  \n\n**Introduction**  \n• Segment Anything Model (SAM) underperforms in medical imaging due to domain gaps  \n• Existing methods suffer from coarse text priors and misaligned modality fusion  \n• PG-SAM introduces medical LLMs for fine-grained anatomical text prompts  \n• Key innovation: Joint optimization of semantic alignment and pixel-level details  \n\n**Related Work**  \n• Prompt-free SAM variants (e.g., SAMed, H-SAM) lack domain-specific priors  \n• CLIP-based alignment methods (e.g., TP-DRSeg) face granularity limitations  \n• Medical LLMs show potential but require integration with visual features  \n• PG-SAM uniquely combines LoRA-tuned CLIP with hierarchical feature fusion  \n\n**Methodology**  \n• Fine-grained modality prior aligner generates Semantic Guide Matrix $G \\in \\mathbb{R}^{B \\times L \\times L}$  \n• Multi-level feature fusion uses deformable convolution for edge preservation:  \n  $$F_{\\text{fusion}} = \\phi(F_{\\text{up}}^{(2)}) + \\psi(\\text{Align}(G; \\theta))$$  \n• Iterative mask optimizer employs hypernetwork for dynamic kernel generation:  \n  $$\\Omega_i = \\text{MLP}(m_i) \\odot W_{\\text{base}}$$  \n\n**Experiment**  \n• Synapse dataset: 3,779 CT slices with 8 abdominal organs  \n• Achieves $84.79\\%$ mDice (fully supervised) and $75.75\\%$ (10% data)  \n• Reduces HD95 to $7.61$ (↓$5.68$ vs. H-SAM) for boundary precision  \n• Ablation shows $+4.69\\%$ mDice gain from iterative mask optimization  \n\n**Conclusion**  \n• PG-SAM outperforms SOTA by integrating medical LLMs with SAM  \n• Fine-grained priors and multi-level fusion address modality misalignment  \n• Future work: Extend to 3D segmentation and real-time clinical applications  \n• Code available at https://github.com/logan-0623/PG-SAM"};
            displayPdfResult(pdfResult);
        }

        // Comment out automatic display of sample results, so it doesn't run automatically
        // setTimeout(displayLatestPdfResult, 1000);

        // Add function to load conversation list
        async function loadConversations() {
            try {
                console.log('Loading conversation list...');
                const response = await fetch(`db_get_conversations.php?user_id=${config.userId}`);
                const conversations = await response.json();
                console.log('Conversation list loaded:', conversations);
                
                const conversationsList = document.getElementById('conversationsList');
                conversationsList.innerHTML = '';
                
                if (conversations.length === 0) {
                    // If no conversations, show prompt
                    const emptyMessage = document.createElement('div');
                    emptyMessage.className = 'empty-conversations-message';
                    emptyMessage.textContent = 'No historical conversations';
                    conversationsList.appendChild(emptyMessage);
                    return;
                }
                
                conversations.forEach(conv => {
                    // Only show conversations belonging to current user
                    if (parseInt(conv.user_id) === parseInt(config.userId)) {
                        const item = document.createElement('div');
                        item.className = 'conversation-item';
                        if (parseInt(conv.id) === parseInt(config.conversationId)) {
                            item.classList.add('active');
                        }
                        
                        const title = document.createElement('div');
                        title.className = 'conversation-title';
                        title.textContent = conv.title || 'New Conversation';
                        title.dataset.id = conv.id;
                        
                        const deleteBtn = document.createElement('span');
                        deleteBtn.className = 'delete-conversation';
                        deleteBtn.innerHTML = '🗑️';
                        deleteBtn.onclick = (e) => {
                            e.stopPropagation();
                            deleteConversation(conv.id);
                        };
                        
                        item.appendChild(title);
                        item.appendChild(deleteBtn);
                        
                        item.onclick = () => switchConversation(conv.id);
                        
                        conversationsList.appendChild(item);
                    }
                });
            } catch (error) {
                console.error('Failed to load conversation list:', error);
                addMessageToChat('system', 'Failed to load conversation list', true);
            }
        }

        // Switch conversation
        async function switchConversation(conversationId) {
            try {
                // Verify conversation ownership
                const response = await fetch(`db_verify_conversation.php?conversation_id=${conversationId}&user_id=${config.userId}`);
                const result = await response.json();
                if (!result.valid) {
                    console.error('No access to this conversation');
                    addMessageToChat('system', 'No access to this conversation', true);
                    return;
                }

                console.log('Switching to conversation:', conversationId);
                config.conversationId = conversationId;
                
                // Update session ID in PHP session
                await fetch('update_session.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        conversation_id: conversationId
                    })
                });
                
                // Clear current chat box
                chatBox.innerHTML = '';
                chatHistory = [];
                
                // Reload messages
                await loadChatHistory();
                
                // Update conversation list UI
                document.querySelectorAll('.conversation-item').forEach(item => {
                    item.classList.remove('active');
                    const titleElem = item.querySelector('.conversation-title');
                    if (parseInt(titleElem.dataset.id) === parseInt(conversationId)) {
                        item.classList.add('active');
                    }
                });
                
            } catch (error) {
                console.error('Failed to switch conversation:', error);
                addMessageToChat('system', 'Failed to switch conversation', true);
            }
        }

        // Delete conversation
        async function deleteConversation(conversationId) {
            if (!confirm('Are you sure you want to delete this conversation?')) {
                return;
            }
            
            try {
                const response = await fetch('db_delete_conversation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        conversation_id: conversationId
                    })
                });
                
                const result = await response.json();
                if (result.status === 'success') {
                    // Remove conversation button from UI
                    const conversationItems = document.querySelectorAll('.conversation-item');
                    for (let item of conversationItems) {
                        const titleElem = item.querySelector('.conversation-title');
                        if (titleElem && parseInt(titleElem.dataset.id) === parseInt(conversationId)) {
                            item.remove();
                            break;
                        }
                    }

                    // If deleted is current conversation, clear chat content
                    if (parseInt(conversationId) === parseInt(config.conversationId)) {
                        chatBox.innerHTML = '';
                        addMessageToChat('system', 'Welcome to Deepchat AI dialogue system! Please enter your question or upload a file to start the conversation.', false, false);
                        config.conversationId = null;
                    }

                    // Check if there are other conversations
                    const conversationsList = document.getElementById('conversationsList');
                    if (conversationsList.children.length === 0) {
                        // If no conversations, show prompt message
                        const emptyMessage = document.createElement('div');
                        emptyMessage.className = 'empty-conversations-message';
                        emptyMessage.textContent = 'No historical conversations';
                        conversationsList.appendChild(emptyMessage);
                    }
                } else {
                    throw new Error(result.message || 'Failed to delete conversation');
                }
            } catch (error) {
                console.error('Failed to delete conversation:', error);
                addMessageToChat('system', 'Failed to delete conversation', true);
            }
        }

        // Switch model
        function toggleModel() {
            if (config.modelName === 'deepseek-chat') {
                config.modelName = 'deepseek-reasoner';
                morethinkButton.classList.add('active');
                morethinkButton.innerHTML = '<i>💭</i> Morethink (已启用)';
            } else {
                config.modelName = 'deepseek-chat';
                morethinkButton.classList.remove('active');
                morethinkButton.innerHTML = '<i>💭</i> Morethink';
            }
            localStorage.setItem('modelName', config.modelName);
        }
    </script>
</body>
</html>
