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
            <div class="subtitle">智能交互平台 · 让AI助力您的工作与学习</div>
        </div>
        
        <div class="card-container">
            <div class="feature-card">
                <div class="card-icon">💬</div>
                <div class="card-title">智能对话</div>
                <div class="card-description">
                    与AI进行自然、流畅的对话。提问、讨论、解决问题，获取您需要的信息和建议。支持多轮对话，记忆上下文，提供更加连贯的交流体验。
                </div>
                <button class="card-button" id="chatButton">开始对话</button>
            </div>
            
            <div class="feature-card">
                <div class="card-icon">📄</div>
                <div class="card-title">文档分析</div>
                <div class="card-description">
                    上传PDF文件或文本文档，AI将为您提取关键信息，回答相关问题，帮助您更高效地处理和理解文档内容。无需人工筛选，快速获取文档洞察。
                </div>
                <button class="card-button" id="documentButton">分析文档</button>
            </div>
            
            <div class="feature-card">
                <div class="card-icon">⚙️</div>
                <div class="card-title">系统配置</div>
                <div class="card-description">
                    自定义您的AI体验。配置API连接参数，选择您偏好的语言模型，调整响应生成参数，满足您的特定需求，打造个性化的AI助手。
                </div>
                <button class="card-button" id="configButton">配置系统</button>
            </div>
        </div>
        
        <div class="footer">
            © 2024 Deepchat AI · 版本 v1.0.0
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
                <input type="text" id="apiBaseUrl" placeholder="http://127.0.0.1:9000">
            </div>
            
            <div class="form-group">
                <label for="wsBaseUrl">WebSocket Base URL</label>
                <input type="text" id="wsBaseUrl" placeholder="ws://127.0.0.1:9000">
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
            apiBaseUrl: localStorage.getItem('apiBaseUrl') || 'http://127.0.0.1:9000',
            wsBaseUrl: localStorage.getItem('wsBaseUrl') || 'ws://127.0.0.1:9000',
            modelName: localStorage.getItem('modelName') || 'deepseek-chat'
        };
        
        // DOM元素
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
        
        // 初始化
        document.addEventListener('DOMContentLoaded', function() {
            // 填充配置表单
            apiKeyInput.value = config.apiKey;
            apiBaseUrlInput.value = config.apiBaseUrl;
            wsBaseUrlInput.value = config.wsBaseUrl;
            modelNameInput.value = config.modelName;
            
            // 事件监听器设置
            chatButton.addEventListener('click', () => window.location.href = 'Chat_Interface.php');
            documentButton.addEventListener('click', () => window.location.href = 'Chat_Interface.php');
            configButton.addEventListener('click', () => configModal.style.display = 'flex');
            closeConfigModal.addEventListener('click', () => configModal.style.display = 'none');
            saveConfigButton.addEventListener('click', saveConfig);
            closeTestResultModal.addEventListener('click', () => testResultModal.style.display = 'none');
        });
        
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
            
            // 显示成功提示
            alert('配置已保存。');
        }
        
        // 测试API连接
        async function testApi() {
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
                    
                    alert('API测试成功！');
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
                    
                    alert(`API测试失败: ${result.message}`);
                }
                
                // 显示测试结果模态框
                testResultContent.textContent = resultContent;
                testResultModal.style.display = 'flex';
                
            } catch (error) {
                console.error('API测试失败:', error);
                
                // 显示测试结果模态框
                testResultContent.textContent = `❌ API连接测试失败\n\n🔸 错误信息: ${error.message}\n\n可能的原因:\n- 后端服务未运行\n- API基础URL不正确\n- 网络连接问题`;
                testResultModal.style.display = 'flex';
                
                alert(`API测试失败: ${error.message}`);
            }
        }
    </script>
</body>
</html>