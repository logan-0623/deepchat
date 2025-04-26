<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deepchat AI</title>
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
            width: 100%;
            max-width: 1200px;
            margin: 40px auto;
            padding: 30px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            display: flex;
            flex-direction: column;
            height: calc(100vh - 80px);
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo {
            font-size: 36px;
            font-weight: bold;
            color: #4a6cf7;
            margin-bottom: 10px;
        }
        
        .subtitle {
            font-size: 18px;
            color: #666;
        }
        
        .card-container {
            display: flex;
            gap: 30px;
            flex: 1;
            overflow-y: auto;
            padding-bottom: 20px;
        }
        
        .feature-card {
            flex: 1;
            background-color: #fff;
            border-radius: 15px;
            padding: 25px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            text-decoration: none;
            color: inherit;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        
        .card-icon {
            font-size: 40px;
            margin-bottom: 20px;
            color: #4a6cf7;
        }
        
        .card-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }
        
        .card-description {
            font-size: 16px;
            line-height: 1.6;
            color: #666;
            margin-bottom: 20px;
            flex: 1;
        }
        
        .card-button {
            background-color: #4a6cf7;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
            align-self: flex-start;
        }
        
        .card-button:hover {
            background-color: #3a5be6;
        }
        
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
            color: #888;
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
                margin: 20px;
                padding: 20px;
                height: calc(100vh - 40px);
            }
            
            .card-container {
                flex-direction: column;
            }
            
            .feature-card {
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">Deepchat AI</div>
            <div class="subtitle">Intelligent Interaction Platform · Empowering Your Work and Study with AI</div>
        </div>
        
        <div class="card-container">
            <div class="feature-card">
                <div class="card-icon">💬</div>
                <div class="card-title">AI Chat</div>
                <div class="card-description">
                    Engage in natural, fluent conversations with AI. Ask questions, discuss topics, solve problems, and get the information and advice you need. Supports multi-turn dialogue, remembering context for a more coherent communication experience.
                </div>
                <button class="card-button" id="chatButton">Start Chat</button>
            </div>
            
            <div class="feature-card">
                <div class="card-icon">📄</div>
                <div class="card-title">Document Analysis</div>
                <div class="card-description">
                    Upload PDF or text documents, and AI will extract key information, answer related questions, and help you process and understand content more efficiently. No manual filtering needed, quickly gain insights from documents.
                </div>
                <button class="card-button" id="documentButton">Analyze Document</button>
            </div>
            
            <div class="feature-card">
                <div class="card-icon">⚙️</div>
                <div class="card-title">System Configuration</div>
                <div class="card-description">
                    Customize your AI experience. Configure API connection parameters, choose your preferred language model, adjust response generation settings to meet your specific needs, and create a personalized AI assistant.
                </div>
                <button class="card-button" id="configButton">Configure System</button>
            </div>
        </div>
        
        <div class="footer">
            © 2024 Deepchat AI · Version v1.0.0
        </div>
    </div>
    
    <!-- Configuration modal -->
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
                <input type="text" id="apiBaseUrl" placeholder="http://127.0.0.1:9000">
            </div>
            
            <div class="form-group">
                <label for="wsBaseUrl">WebSocket Base URL</label>
                <input type="text" id="wsBaseUrl" placeholder="ws://127.0.0.1:9000">
            </div>
            
            <div class="form-group">
                <label for="modelName">Model Name</label>
                <input type="text" id="modelName" placeholder="deepseek-chat">
            </div>
            
            <button class="modal-button" id="saveConfigButton">Save Configuration</button>
        </div>
    </div>
    
    <!-- Test Results Modal -->
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
        apiBaseUrl: localStorage.getItem('apiBaseUrl') || 'http://127.0.0.1:9000',
        wsBaseUrl: localStorage.getItem('wsBaseUrl') || 'ws://127.0.0.1:9000',
        modelName: localStorage.getItem('modelName') || 'deepseek-chat'
    };
    
    // DOM elements
    const chatButton = document.getElementById('chatButton');
    const documentButton = document.getElementById('documentButton');
    const configButton = document.getElementById('configButton');
    const configModal = document.getElementById('configModal');
    const closeConfigModal = document.getElementById('closeConfigModal');
    const apiKeyInput = document.getElementById('apiKey');
    const apiBaseUrlInput = document.getElementById('apiBaseUrl');
    const wsBaseUrlInput = document.getElementById('wsBaseUrl');
    const modelNameInput = document.getElementById('modelName');
    const saveConfigButton = document.getElementById('saveConfigButton');
    const testResultModal = document.getElementById('testResultModal');
    const closeTestResultModal = document.getElementById('closeTestResultModal');
    const testResultContent = document.getElementById('testResultContent');
    
    // Initialization
    document.addEventListener('DOMContentLoaded', function() {
        // Populate configuration form
        apiKeyInput.value = config.apiKey;
        apiBaseUrlInput.value = config.apiBaseUrl;
        wsBaseUrlInput.value = config.wsBaseUrl;
        modelNameInput.value = config.modelName;
        
        // Set up event listeners
        chatButton.addEventListener('click', () => window.location.href = 'Chat_Interface.php');
        documentButton.addEventListener('click', () => window.location.href = 'Chat_Interface.php');
        configButton.addEventListener('click', () => configModal.style.display = 'flex');
        closeConfigModal.addEventListener('click', () => configModal.style.display = 'none');
        saveConfigButton.addEventListener('click', saveConfig);
        closeTestResultModal.addEventListener('click', () => testResultModal.style.display = 'none');
    });
    
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
    
    // Display success message
    alert('Configuration saved.');
}

// Test API connection
async function testApi() {
    try {
        const response = await fetch(`${config.apiBaseUrl}/api/test`);
        
        if (!response.ok) {
            throw new Error(`API error: ${response.status}`);
        }
        
        const result = await response.json();
        console.log('API test result:', result);
                
               // Prepare test results content
let resultContent = '';

if (result.status === 'success') {
    resultContent += `✅ API connection test successful\n\n`;
    resultContent += `🔹 Server status: ${result.server_info.server_status}\n`;
    resultContent += `🔹 Model: ${result.server_info.model}\n`;
    resultContent += `🔹 API base URL: ${result.server_info.api_base}\n`;
    resultContent += `🔹 Response time: ${result.server_info.api_response_time_ms.toFixed(2)}ms\n\n`;
    resultContent += `🔹 API reply: "${result.reply}"\n`;

    alert('API test successful!');
} else {
    resultContent += `❌ API connection test failed\n\n`;
    resultContent += `🔸 Error message: ${result.message}\n`;

    if (result.server_info) {
        resultContent += `\nServer info:\n`;
        resultContent += `🔸 Server status: ${result.server_info.server_status}\n`;
        resultContent += `🔸 Model: ${result.server_info.model}\n`;
        resultContent += `🔸 API base URL: ${result.server_info.api_base}\n`;

        if (result.server_info.api_error) {
            resultContent += `🔸 API error: ${result.server_info.api_error}\n`;
        }
    }

    if (result.detail) {
        resultContent += `\nDetailed error info: ${result.detail}\n`;
    }

    alert(`API test failed: ${result.message}`);
}

               // Display the test result modal
testResultContent.textContent = resultContent;
testResultModal.style.display = 'flex';

} catch (error) {
    console.error('API test failed:', error);

    // Display the test result modal
    testResultContent.textContent = `❌ API connection test failed\n\n🔸 Error message: ${error.message}\n\nPossible reasons:\n- Backend service not running\n- Incorrect API base URL\n- Network connectivity issues`;
    testResultModal.style.display = 'flex';

    alert(`API test failed: ${error.message}`);
}
}
    </script>
</body>
</html>