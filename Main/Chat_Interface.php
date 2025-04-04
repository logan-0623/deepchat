<?php
// 接收从Main Page传来的消息
$initial_message = isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deepchat AI对话界面</title>
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
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="brand">Deepchat AI</div>
            
            <div class="feature-button" id="newChatButton">
                <i>✚</i> 新对话
            </div>
            
            <div class="feature-button" id="clearChatButton">
                <i>🗑️</i> 清空对话
            </div>
            
            <div class="feature-button" id="uploadButton">
                <i>📁</i> 上传文件
            </div>
            
            <div class="feature-button" id="morethinkButton">
                <i>💭</i> Morethink
            </div>
            
            <div class="feature-button" id="searchButton">
                <i>🔍</i> Search
            </div>
            
            <div class="spacer"></div>
            
            <div class="feature-button" id="configButton">
                <i>⚙️</i> API设置
            </div>
            
            <div class="feature-button" id="testApiButton">
                <i>🔄</i> 测试API
            </div>
            
            <div class="footer">
                Powered by Deepchat AI<br>
                版本 v1.0.0
            </div>
        </div>
        
        <div class="main-content">
            <div class="chat-box" id="chatBox">
                <div class="system-message">
                    欢迎使用Deepchat AI对话系统！请输入您的问题或上传文件开始对话。
                </div>
            </div>
            
            <div class="input-area">
                <div class="file-drop-area" id="fileDropArea">
                    拖放文件到这里或点击上传 (支持PDF和文本文件，最大10MB)
                    <input type="file" id="fileInput" accept=".pdf,.txt,.md,.csv">
                </div>
                
                <div class="message-input">
                    <textarea id="messageInput" placeholder="输入消息..." onkeydown="if(event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); sendMessage(); }"></textarea>
                    <button class="send-button" id="sendButton">发送</button>
                </div>
                
                <div class="button-row">
                    <div class="aux-button" id="regenerateButton">重新生成</div>
                    <div class="aux-button" id="copyButton">复制回复</div>
                    <div class="aux-button" id="clearButton">清除输入</div>
                </div>
            </div>
            
            <div class="typing-indicator" id="typingIndicator">
                AI正在思考<span></span><span></span><span></span>
                <button class="stop-thinking-button" id="stopThinkingButton">停止思考</button>
            </div>
        </div>
    </div>
    
    <!-- 配置模态框 -->
    <div class="modal" id="configModal">
        <div class="modal-content">
            <div class="close-modal" id="closeConfigModal">&times;</div>
            <div class="modal-title">API配置</div>
            
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
                <label for="modelName">模型名称</label>
                <input type="text" id="modelName" placeholder="deepseek-chat">
            </div>
            
            <button class="modal-button" id="saveConfigButton">保存配置</button>
        </div>
    </div>
    
    <!-- 测试结果模态框 -->
    <div class="modal" id="testResultModal">
        <div class="modal-content">
            <div class="close-modal" id="closeTestResultModal">&times;</div>
            <div class="modal-title">API测试结果</div>
            <div id="testResultContent" style="white-space: pre-wrap; max-height: 400px; overflow-y: auto;"></div>
        </div>
    </div>
    
    <script>
        // 配置变量
        let config = {
            apiKey: localStorage.getItem('apiKey') || '',
            apiBaseUrl: localStorage.getItem('apiBaseUrl') || 'http://127.0.0.1:8000',
            wsBaseUrl: localStorage.getItem('wsBaseUrl') || 'ws://127.0.0.1:8000',
            modelName: localStorage.getItem('modelName') || 'deepseek-chat'
        };
        
        // 全局变量
        let activeWs = null;
        let currentTaskId = null;
        let lastBotMessage = null;
        let chatHistory = [];
        
        // DOM元素
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
        const regenerateButton = document.getElementById('regenerateButton');
        const copyButton = document.getElementById('copyButton');
        const clearButton = document.getElementById('clearButton');
        const testApiButton = document.getElementById('testApiButton');
        const testResultModal = document.getElementById('testResultModal');
        const closeTestResultModal = document.getElementById('closeTestResultModal');
        const testResultContent = document.getElementById('testResultContent');
        const stopThinkingButton = document.getElementById('stopThinkingButton');
        
        // 初始化
        document.addEventListener('DOMContentLoaded', function() {
            // 填充配置表单
            apiKeyInput.value = config.apiKey;
            apiBaseUrlInput.value = config.apiBaseUrl;
            wsBaseUrlInput.value = config.wsBaseUrl;
            modelNameInput.value = config.modelName;
            
            // 事件监听器设置
            sendButton.addEventListener('click', sendMessage);
            fileInput.addEventListener('change', handleFileChange);
            uploadButton.addEventListener('click', () => fileInput.click());
            configButton.addEventListener('click', () => configModal.style.display = 'flex');
            closeConfigModal.addEventListener('click', () => configModal.style.display = 'none');
            saveConfigButton.addEventListener('click', saveConfig);
            newChatButton.addEventListener('click', startNewChat);
            clearChatButton.addEventListener('click', clearChat);
            regenerateButton.addEventListener('click', regenerateLastMessage);
            copyButton.addEventListener('click', copyLastReply);
            clearButton.addEventListener('click', clearInputField);
            testApiButton.addEventListener('click', testApi);
            closeTestResultModal.addEventListener('click', () => testResultModal.style.display = 'none');
            stopThinkingButton.addEventListener('click', stopThinking);
            
            // 文件拖放区域事件
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
            
            // 从本地存储加载聊天历史
            loadChatHistory();
            
            // 检查URL中是否有pdf_result参数
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('pdf_result')) {
                try {
                    const result = JSON.parse(decodeURIComponent(urlParams.get('pdf_result')));
                    displayPdfResult(result);
                } catch (e) {
                    console.error('从URL解析PDF结果失败:', e);
                }
            }
            
            // 提供一个全局函数供控制台使用
            window.displayPdfResult = displayPdfResult;
            
            // 显示最新返回的PDF结果（如果有）
            try {
                // 检查页面中是否有内联的PDF结果
                const pdfResultElement = document.getElementById('latest_pdf_result');
                if (pdfResultElement && pdfResultElement.textContent) {
                    const result = JSON.parse(pdfResultElement.textContent);
                    displayPdfResult(result);
                }
            } catch (e) {
                console.error('解析内联PDF结果失败:', e);
            }
        });
        
        // 检查服务器连接
        async function checkServerConnection() {
            try {
                const response = await fetch(`${config.apiBaseUrl}/api/ping`, { 
                    method: 'GET',
                    headers: { 'Content-Type': 'application/json' }
                });
                
                if (response.ok) {
                    console.log('服务器连接正常');
                    return true;
                } else {
                    console.error('服务器连接异常:', response.status);
                    return false;
                }
            } catch (error) {
                console.error('服务器连接失败:', error);
                return false;
            }
        }
        
        // 发送消息
        async function sendMessage() {
            const message = messageInput.value.trim();
            if (!message) return;
            
            // 首先检查服务器连接
            if (!await checkServerConnection()) {
                addMessageToChat('system', '无法连接到服务器，请检查服务器是否运行', true);
                return;
            }
            
            // 添加用户消息到聊天
            addMessageToChat('user', message);
            
            // 清空输入框
            messageInput.value = '';
            
            // 显示"正在输入"指示器
            typingIndicator.style.display = 'block';
            stopThinkingButton.style.display = 'inline-block';
            
            try {
                // 生成任务ID并调用API
                const taskId = generateUUID();
                currentTaskId = taskId;
                console.log(`生成新任务ID: ${taskId}`);
                
                // 创建WebSocket连接
                createWebSocketConnection(taskId);
                
                // 等待确保WebSocket连接已建立
                await new Promise(resolve => setTimeout(resolve, 500));
                
                // 发送聊天消息到API
                console.log(`发送消息到API, 任务ID: ${taskId}`);
                const response = await fetch(`${config.apiBaseUrl}/api/chat`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        message: message,
                        task_id: taskId  // 传递相同的taskId给API
                    })
                });
                
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error(`API响应错误: ${response.status}`, errorText);
                    throw new Error(`API错误: ${response.status} ${errorText}`);
                }
                
                const result = await response.json();
                console.log(`API响应成功: ${JSON.stringify(result)}`);
                
                // WebSocket将处理后续的回复流
                console.log("等待WebSocket接收回复...");
                
                // 设置一个超时，如果长时间没有收到响应，显示错误
                setTimeout(() => {
                    if (typingIndicator.style.display === 'block') {
                        console.log("响应超时，可能是WebSocket连接问题");
                        typingIndicator.style.display = 'none';
                        stopThinkingButton.style.display = 'none';
                        addMessageToChat('system', '响应超时，请重试或检查服务器状态', true);
                    }
                }, 30000); // 30秒超时
                
            } catch (error) {
                console.error('发送消息失败:', error);
                addMessageToChat('system', `错误: ${error.message}`, true);
                typingIndicator.style.display = 'none';
                stopThinkingButton.style.display = 'none';
            }
        }
        
        // 创建WebSocket连接
        function createWebSocketConnection(taskId) {
            // 关闭之前的连接
            if (activeWs) {
                activeWs.close();
                activeWs = null;
            }
            
            // 创建新连接
            const ws = new WebSocket(`${config.wsBaseUrl}/ws/${taskId}`);
            activeWs = ws;
            
            ws.onopen = () => {
                console.log(`WebSocket连接已打开: ${taskId}`);
            };
            
            ws.onmessage = (event) => {
                const data = JSON.parse(event.data);
                console.log('WebSocket消息:', data);
                
                if (data.status === '完成' && data.reply) {
                    // 收到完整回复
                    typingIndicator.style.display = 'none';
                    stopThinkingButton.style.display = 'none';
                    addMessageToChat('bot', data.reply);
                } else if (data.type === 'connection_status') {
                    // 处理连接状态消息
                    console.log(`连接状态: ${data.status} - ${data.task_id}`);
                } else if (data.status && data.progress !== undefined) {
                    // 进度更新
                    console.log(`任务进度: ${data.progress}% - ${data.status}`);
                    
                    // 处理PDF特殊消息
                    if (data.message && data.status.includes("PDF")) {
                        // 显示PDF处理状态消息
                        const existingMessage = document.querySelector('.system-message.pdf-processing');
                        if (existingMessage) {
                            // 更新现有消息
                            const contentElem = existingMessage.querySelector('.message-content');
                            if (contentElem) {
                                contentElem.textContent = data.message;
                            }
                        } else {
                            // 创建新消息
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
                    // 处理错误
                    typingIndicator.style.display = 'none';
                    stopThinkingButton.style.display = 'none';
                    addMessageToChat('system', `错误: ${data.error}`, true);
                } else if (data.status && (data.type === 'pdf' || data.type === 'pdf_error' || data.type === 'pdf_timeout' || data.type === 'pdf_unsupported')) {
                    // 处理PDF文件响应
                    console.log('收到PDF处理结果:', data);
                    typingIndicator.style.display = 'none';
                    stopThinkingButton.style.display = 'none';
                    
                    // 根据PDF处理的不同状态显示不同消息
                    if (data.type === 'pdf_timeout') {
                        // PDF处理超时，显示友好的提示
                        addMessageToChat('system', data.content);
                        addMessageToChat('system', '系统在后台继续处理PDF，您可以稍后重新打开聊天查看结果。', false);
                    } else if (data.type === 'pdf_error') {
                        // PDF处理出错，显示友好的提示而不是错误
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
                                    retrieveResult(taskId);
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
                console.log(`通过API获取任务 ${taskId} 的结果`);
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
                console.error('获取结果失败:', error);
                typingIndicator.style.display = 'none';
                stopThinkingButton.style.display = 'none';
                addMessageToChat('system', `获取结果失败: ${error.message}`, true);
            }
        }
        
        // 处理文件上传
        async function handleFileChange() {
            const file = fileInput.files[0];
            if (!file) return;
            
            // 文件类型检查
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
                addMessageToChat('system', '错误: 不支持的文件类型。请上传PDF或文本文件。', true);
                return;
            }
            
            // 文件大小检查 (10MB)
            if (file.size > 10 * 1024 * 1024) {
                addMessageToChat('system', '错误: 文件过大。请上传小于10MB的文件。', true);
                return;
            }
            
            // 首先检查服务器连接
            if (!await checkServerConnection()) {
                addMessageToChat('system', '无法连接到服务器，请检查服务器是否运行', true);
                return;
            }
            
            // 显示上传中消息
            addMessageToChat('system', `正在上传文件: ${file.name}...`);
            
            // 如果是PDF文件，显示特殊提示
            if (file.type === 'application/pdf' || file.name.endsWith('.pdf')) {
                addMessageToChat('system', '请注意：PDF处理可能需要较长时间，取决于文件大小和内容复杂度。请耐心等待。');
            }
            
            // 显示"正在输入"指示器
            typingIndicator.style.display = 'block';
            stopThinkingButton.style.display = 'inline-block';
            
            try {
                // 生成任务ID
                const taskId = generateUUID();
                currentTaskId = taskId;
                console.log(`生成新任务ID: ${taskId} (文件上传)`);
                
                // 创建WebSocket连接
                createWebSocketConnection(taskId);
                
                // 等待确保WebSocket连接已建立
                await new Promise(resolve => setTimeout(resolve, 500));
                
                // 创建FormData对象
                const formData = new FormData();
                formData.append('file', file);
                formData.append('task_id', taskId);  // 添加任务ID
                
                // 发送上传请求
                console.log(`发送文件上传请求, 任务ID: ${taskId}`);
                const response = await fetch(`${config.apiBaseUrl}/api/upload`, {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error(`上传失败: ${response.status} ${await response.text()}`);
                }
                
                const result = await response.json();
                console.log(`文件上传API响应成功: ${JSON.stringify(result)}`);
                
                // 设置一个特别长的超时，专门针对PDF文件
                if (file.type === 'application/pdf' || file.name.endsWith('.pdf')) {
                    // 对于PDF文件，使用5分钟超时
                    let resultReceived = false;
                    
                    // 设置一个轮询机制，定期检查结果
                    const pollInterval = setInterval(async () => {
                        if (resultReceived || typingIndicator.style.display !== 'block') {
                            clearInterval(pollInterval);
                            return;
                        }
                        
                        console.log(`轮询检查任务 ${taskId} 的PDF处理结果...`);
                        try {
                            const pollResponse = await fetch(`${config.apiBaseUrl}/api/result/${taskId}`);
                            if (pollResponse.ok) {
                                const pollResult = await pollResponse.json();
                                
                                // 检查是否有有效的PDF结果
                                if (pollResult.type === 'pdf' && pollResult.content && pollResult.content.trim() !== '') {
                                    console.log('通过轮询发现PDF结果');
                                    resultReceived = true;
                                    
                                    // 使用displayPdfResult显示结果
                                    typingIndicator.style.display = 'none';
                                    stopThinkingButton.style.display = 'none';
                                    displayPdfResult(pollResult);
                                    
                                    // 清除轮询
                                    clearInterval(pollInterval);
                                }
                            }
                        } catch (pollError) {
                            console.error('轮询出错:', pollError);
                        }
                    }, 10000); // 每10秒轮询一次
                    
                    // 主超时控制
                    setTimeout(() => {
                        if (!resultReceived && typingIndicator.style.display === 'block') {
                            console.log("PDF处理中，保持连接...");
                            // 不显示超时错误，只在控制台记录
                        }
                    }, 300000); // 5分钟
                } else {
                    // 对于其他文件类型，使用正常超时
                    setTimeout(() => {
                        if (typingIndicator.style.display === 'block') {
                            console.log("响应超时，可能是WebSocket连接问题");
                            typingIndicator.style.display = 'none';
                            stopThinkingButton.style.display = 'none';
                            addMessageToChat('system', '响应超时，请重试或检查服务器状态', true);
                        }
                    }, 60000); // 1分钟
                }
                
            } catch (error) {
                console.error('文件上传失败:', error);
                addMessageToChat('system', `错误: ${error.message}`, true);
                typingIndicator.style.display = 'none';
                stopThinkingButton.style.display = 'none';
            }
            
            // 清除文件输入
            fileInput.value = '';
        }
        
        // 添加消息到聊天
        function addMessageToChat(type, content, isError = false) {
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
            
            // 自动滚动到底部
            chatBox.scrollTop = chatBox.scrollHeight;
            
            // 如果是机器人消息，保存最后的回复
            if (type === 'bot') {
                lastBotMessage = content;
            }
            
            // 保存聊天历史
            chatHistory.push({
                type: type,
                content: content,
                timestamp: new Date().toISOString()
            });
            
            saveChatHistory();
        }
        
        // 保存聊天历史到本地存储
        function saveChatHistory() {
            localStorage.setItem('chatHistory', JSON.stringify(chatHistory));
        }
        
        // 加载聊天历史
        function loadChatHistory() {
            const savedHistory = localStorage.getItem('chatHistory');
            if (savedHistory) {
                try {
                    chatHistory = JSON.parse(savedHistory);
                    
                    // 清空聊天框
                    chatBox.innerHTML = '';
                    
                    // 添加历史消息到聊天框
                    chatHistory.forEach(msg => {
                        addMessageToChat(msg.type, msg.content, msg.type === 'system' && msg.content.startsWith('错误:'));
                    });
                    
                    // 如果有机器人消息，保存最后一条
                    const botMessages = chatHistory.filter(msg => msg.type === 'bot');
                    if (botMessages.length > 0) {
                        lastBotMessage = botMessages[botMessages.length - 1].content;
                    }
                } catch (e) {
                    console.error('加载聊天历史失败:', e);
                    chatHistory = [];
                }
            }
        }
        
        // 清空聊天
        function clearChat() {
            chatBox.innerHTML = '';
            chatHistory = [];
            saveChatHistory();
            
            // 添加欢迎消息
            addMessageToChat('system', '欢迎使用Deepchat AI对话系统！请输入您的问题或上传文件开始对话。');
        }
        
        // 开始新对话
        function startNewChat() {
            // 关闭当前的WebSocket连接
            if (activeWs) {
                activeWs.close();
                activeWs = null;
            }
            
            clearChat();
        }
        
        // 保存配置
        function saveConfig() {
            config.apiKey = apiKeyInput.value.trim();
            config.apiBaseUrl = apiBaseUrlInput.value.trim();
            config.wsBaseUrl = wsBaseUrlInput.value.trim();
            config.modelName = modelNameInput.value.trim();
            
            // 保存到本地存储
            localStorage.setItem('apiKey', config.apiKey);
            localStorage.setItem('apiBaseUrl', config.apiBaseUrl);
            localStorage.setItem('wsBaseUrl', config.wsBaseUrl);
            localStorage.setItem('modelName', config.modelName);
            
            // 关闭模态框
            configModal.style.display = 'none';
            
            // 显示成功消息
            addMessageToChat('system', '配置已保存。');
        }
        
        // 重新生成最后的消息
        function regenerateLastMessage() {
            const lastUserMessage = chatHistory.filter(msg => msg.type === 'user').pop();
            if (lastUserMessage) {
                // 删除最后一条机器人消息
                const lastBotIndex = chatHistory.findIndex(msg => msg.type === 'bot');
                if (lastBotIndex !== -1) {
                    chatHistory.splice(lastBotIndex, 1);
                    // 更新UI
                    const botMessages = document.querySelectorAll('.bot-message');
                    if (botMessages.length > 0) {
                        botMessages[botMessages.length - 1].remove();
                    }
                }
                
                // 重新发送最后一条用户消息
                messageInput.value = lastUserMessage.content;
                sendMessage();
            } else {
                addMessageToChat('system', '没有找到可以重新生成的消息。');
            }
        }
        
        // 复制最后的回复
        function copyLastReply() {
            if (lastBotMessage) {
                navigator.clipboard.writeText(lastBotMessage)
                    .then(() => {
                        addMessageToChat('system', '回复已复制到剪贴板。');
                    })
                    .catch(err => {
                        console.error('无法复制文本:', err);
                        addMessageToChat('system', '复制失败。请手动选择文本并复制。', true);
                    });
            } else {
                addMessageToChat('system', '没有回复可以复制。');
            }
        }
        
        // 清除输入字段
        function clearInputField() {
            messageInput.value = '';
            messageInput.focus();
        }
        
        // 测试API连接
        async function testApi() {
            // 显示测试中消息
            addMessageToChat('system', '正在测试API连接...');
            
            // 首先检查服务器连接
            if (!await checkServerConnection()) {
                addMessageToChat('system', '无法连接到服务器，请检查服务器是否运行', true);
                
                // 显示测试结果模态框
                testResultContent.textContent = `❌ API连接测试失败\n\n🔸 错误信息: 无法连接到服务器\n\n可能的原因:\n- 后端服务未运行\n- API基础URL不正确 (${config.apiBaseUrl})\n- 网络连接问题`;
                testResultModal.style.display = 'flex';
                
                return;
            }
            
            try {
                const response = await fetch(`${config.apiBaseUrl}/api/test`);
                
                if (!response.ok) {
                    throw new Error(`API错误: ${response.status}`);
                }
                
                const result = await response.json();
                console.log('API测试结果:', result);
                
                // 准备测试结果内容
                let resultContent = '';
                
                if (result.status === 'success') {
                    resultContent += `✅ API连接测试成功\n\n`;
                    resultContent += `🔹 服务器状态: ${result.server_info.server_status}\n`;
                    resultContent += `🔹 模型: ${result.server_info.model}\n`;
                    resultContent += `🔹 API基础URL: ${result.server_info.api_base}\n`;
                    resultContent += `🔹 响应时间: ${result.server_info.api_response_time_ms.toFixed(2)}ms\n\n`;
                    resultContent += `🔹 API回复: "${result.reply}"\n`;
                } else {
                    resultContent += `❌ API连接测试失败\n\n`;
                    resultContent += `🔸 错误信息: ${result.message}\n`;
                    
                    if (result.server_info) {
                        resultContent += `\n服务器信息:\n`;
                        resultContent += `🔸 服务器状态: ${result.server_info.server_status}\n`;
                        resultContent += `🔸 模型: ${result.server_info.model}\n`;
                        resultContent += `🔸 API基础URL: ${result.server_info.api_base}\n`;
                        
                        if (result.server_info.api_error) {
                            resultContent += `🔸 API错误: ${result.server_info.api_error}\n`;
                        }
                    }
                    
                    if (result.detail) {
                        resultContent += `\n详细错误信息: ${result.detail}\n`;
                    }
                }
                
                // 显示测试结果模态框
                testResultContent.textContent = resultContent;
                testResultModal.style.display = 'flex';
                
                // 在聊天中显示简要结果
                if (result.status === 'success') {
                    addMessageToChat('system', 'API测试成功！');
                } else {
                    addMessageToChat('system', `API测试失败: ${result.message}`, true);
                }
                
            } catch (error) {
                console.error('API测试失败:', error);
                
                // 在聊天中显示错误
                addMessageToChat('system', `API测试失败: ${error.message}`, true);
                
                // 显示测试结果模态框
                testResultContent.textContent = `❌ API连接测试失败\n\n🔸 错误信息: ${error.message}\n\n可能的原因:\n- 后端服务未运行\n- API基础URL不正确 (${config.apiBaseUrl})\n- 网络连接问题`;
                testResultModal.style.display = 'flex';
            }
        }
        
        // 生成UUID
        function generateUUID() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                const r = Math.random() * 16 | 0;
                const v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        }
        
        // 停止思考/生成
        function stopThinking() {
            console.log("用户点击停止思考按钮");
            
            // 如果WebSocket连接存在，发送停止消息
            if (activeWs && activeWs.readyState === WebSocket.OPEN) {
                try {
                    console.log(`发送停止思考消息到WebSocket: ${currentTaskId}`);
                    activeWs.send(JSON.stringify({
                        type: "stop_thinking",
                        task_id: currentTaskId
                    }));
                } catch (error) {
                    console.error("发送停止思考消息失败:", error);
                }
            }
            
            // 关闭当前WebSocket连接
            if (activeWs) {
                console.log(`关闭WebSocket连接: ${currentTaskId}`);
                activeWs.close();
                activeWs = null;
            }
            
            // 隐藏加载指示器
            typingIndicator.style.display = 'none';
            stopThinkingButton.style.display = 'none';
            
            // 添加系统消息
            addMessageToChat('system', '已停止当前生成');
        }
        
        // 显示PDF结果
        function displayPdfResult(resultJson) {
            try {
                // 如果传入的是null或undefined，直接返回失败
                if (!resultJson) {
                    console.error('显示PDF结果失败: 结果为空');
                    addMessageToChat('system', 'PDF处理结果为空', true);
                    return false;
                }
                
                // 将字符串解析为JSON对象（如果尚未解析）
                const result = typeof resultJson === 'string' ? JSON.parse(resultJson) : resultJson;
                
                console.log('手动显示PDF处理结果:', result);
                
                // 验证必要的字段
                if (!result.type) {
                    console.error('PDF结果缺少type字段:', result);
                    addMessageToChat('system', 'PDF处理结果格式不正确', true);
                    return false;
                }
                
                // 隐藏任何加载指示器
                typingIndicator.style.display = 'none';
                stopThinkingButton.style.display = 'none';
                
                // 显示上传的文件名
                if (result.file_name) {
                    addMessageToChat('system', `已上传: ${result.file_name}`);
                }
                
                // 根据类型处理不同的响应
                if (result.type === 'pdf_timeout') {
                    addMessageToChat('system', result.content);
                    addMessageToChat('system', '系统在后台继续处理PDF，您可以稍后重新打开聊天查看结果。', false);
                } else if (result.type === 'pdf_error') {
                    addMessageToChat('system', result.content);
                } else if (result.type === 'pdf_unsupported') {
                    addMessageToChat('system', result.content);
                } else if (result.type === 'pdf') {
                    // 检查content是否有内容
                    if (result.content && result.content.trim() !== '') {
                        console.log('显示PDF内容，长度:', result.content.length);
                        // 显示PDF内容作为机器人回复
                        addMessageToChat('bot', result.content);
                    } else {
                        console.error('PDF内容为空:', result);
                        addMessageToChat('system', 'PDF内容为空，请重试上传或联系管理员', true);
                    }
                } else {
                    console.warn('未知的PDF结果类型:', result.type);
                    // 尝试显示内容，无论类型如何
                    if (result.content) {
                        addMessageToChat('bot', result.content);
                    } else {
                        addMessageToChat('system', 'PDF处理结果未知，但没有内容可显示', true);
                    }
                }
                
                // 移除处理中的消息
                const pdfProcessingMsg = document.querySelector('.system-message.pdf-processing');
                if (pdfProcessingMsg) {
                    pdfProcessingMsg.remove();
                }
                
                return true;
            } catch (e) {
                console.error('解析或显示PDF结果时出错:', e);
                console.error('原始结果:', resultJson);
                addMessageToChat('system', `显示PDF结果时出错: ${e.message}`, true);
                return false;
            }
        }

        // 添加用于测试的样本数据
        // 你可以在浏览器控制台中输入 displayLatestPdfResult() 来显示测试结果
        function displayLatestPdfResult() {
            const pdfResult = {"status": "success", "type": "pdf", "task_id": "783fe527-7b11-4fc7-9677-afe42220135d", "file_name": "783fe527-7b11-4fc7-9677-afe42220135d_fa9010e257bbb7782f3a4b1b3dacd4be.pdf", "content": "**Abstract**  \n• PG-SAM integrates medical LLMs (Large Language Models) to enhance multi-organ segmentation accuracy  \n• Proposed fine-grained modality prior aligner bridges domain gaps between text and medical images  \n• Multi-level feature fusion and iterative mask optimizer improve boundary precision  \n• Achieves state-of-the-art performance on Synapse dataset with $84.79\\%$ mDice  \n\n**Introduction**  \n• Segment Anything Model (SAM) underperforms in medical imaging due to domain gaps  \n• Existing methods suffer from coarse text priors and misaligned modality fusion  \n• PG-SAM introduces medical LLMs for fine-grained anatomical text prompts  \n• Key innovation: Joint optimization of semantic alignment and pixel-level details  \n\n**Related Work**  \n• Prompt-free SAM variants (e.g., SAMed, H-SAM) lack domain-specific priors  \n• CLIP-based alignment methods (e.g., TP-DRSeg) face granularity limitations  \n• Medical LLMs show potential but require integration with visual features  \n• PG-SAM uniquely combines LoRA-tuned CLIP with hierarchical feature fusion  \n\n**Methodology**  \n• Fine-grained modality prior aligner generates Semantic Guide Matrix $G \\in \\mathbb{R}^{B \\times L \\times L}$  \n• Multi-level feature fusion uses deformable convolution for edge preservation:  \n  $$F_{\\text{fusion}} = \\phi(F_{\\text{up}}^{(2)}) + \\psi(\\text{Align}(G; \\theta))$$  \n• Iterative mask optimizer employs hypernetwork for dynamic kernel generation:  \n  $$\\Omega_i = \\text{MLP}(m_i) \\odot W_{\\text{base}}$$  \n\n**Experiment**  \n• Synapse dataset: 3,779 CT slices with 8 abdominal organs  \n• Achieves $84.79\\%$ mDice (fully supervised) and $75.75\\%$ (10% data)  \n• Reduces HD95 to $7.61$ (↓$5.68$ vs. H-SAM) for boundary precision  \n• Ablation shows $+4.69\\%$ mDice gain from iterative mask optimization  \n\n**Conclusion**  \n• PG-SAM outperforms SOTA by integrating medical LLMs with SAM  \n• Fine-grained priors and multi-level fusion address modality misalignment  \n• Future work: Extend to 3D segmentation and real-time clinical applications  \n• Code available at https://github.com/logan-0623/PG-SAM"};
            displayPdfResult(pdfResult);
        }

        // 注释掉自动显示样本结果的代码，使其不再自动运行
        // setTimeout(displayLatestPdfResult, 1000);
    </script>
</body>
</html>
