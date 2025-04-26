<?php
session_start();
$lang = $_GET['lang'] 
      ?? ($_COOKIE['lang'] ?? 'zh');
// Whitelist verification
$lang = in_array($lang, ['zh','en']) 
      ? $lang 
      : 'zh';
// Write cookies (30 days)
setcookie('lang', $lang, time() + 86400 * 30, '/');
// Load the corresponding language pack
$T = json_decode(
    file_get_contents(__DIR__ . "/lang/{$lang}.json"),
    true
) ?: [];
// Translation function
function t($key) {
    global $T;
    return $T[$key] ?? $key;
}
// Detailed session debugging information
error_log("========== Session debugging information ==========");
error_log("Session ID: " . session_id());
error_log("All session variables: " . print_r($_SESSION, true));
error_log("Cookie information: " . print_r($_COOKIE, true));
error_log("=================================");

// Check whether the user has logged in
if (!isset($_SESSION['user_id'])) {
    error_log("Warning: The user is not logged in. Redirect to the login page");
    header('Location: user_login.php');
    exit();
}

// Obtain and verify user information
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'unknown';

// Record user information
error_log("Current user information：");
error_log("- userid: " . $user_id);
error_log("- username: " . $username);

$conversation_id = isset($_SESSION['current_conversation_id']) ? $_SESSION['current_conversation_id'] : null;
error_log("Current dialogue ID: " . ($conversation_id ?? 'null'));

// If there is no current conversation ID, obtain the user's latest conversation
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
        error_log("Failed to obtain the latest conversation: " . $e->getMessage());
    }
}

// Record the conversation information
error_log(" Current session state - User ID: $user_id, dialogue ID: ". ($conversation_id?? 'null'));

// Receive the messages sent from the Main Page
$initial_message = isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '';
?>

<!DOCTYPE html>
<html lang="<?= $lang==='zh' ? 'zh-CN' : 'en-US' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deepchat AI Dialog Interface</title>
    <script>
  // Expose the $T language array in PHP to JS
  window.i18n = <?= json_encode($T, JSON_UNESCAPED_UNICODE) ?>;
  function tjs(key) { return window.i18n[key] || key; }
    </script>
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

        .pdf-quick-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 15px 0;
            padding: 10px;
            background-color: rgba(74, 108, 247, 0.1);
            border-radius: 10px;
            justify-content: center;
        }

        .pdf-quick-button {
            padding: 8px 15px;
            background-color: #4a6cf7;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .pdf-quick-button:hover {
            background-color: #3a5be6;
            transform: translateY(-2px);
        }

        .search-box {
            margin: 10px 0;
            position: relative;
            width: 82%;
            margin-left: 0px;
        }

        .search-input {
            width: 100%;
            padding: 8px 12px;
            padding-left: 30px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 13px;
            outline: none;
            transition: all 0.3s;
            background-color: rgba(255, 255, 255, 0.9);
        }

        .search-input:focus {
            border-color: #4a6cf7;
            box-shadow: 0 0 0 2px rgba(74, 108, 247, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 14px;
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background-color: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-top: 5px;
            max-height: 300px;
            overflow-y: auto;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            display: none;
            z-index: 1000;
        }

        .search-result-item {
            padding: 8px 12px;
            cursor: pointer;
            transition: background-color 0.2s;
            font-size: 13px;
            border-bottom: 1px solid #f0f0f0;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-item:hover {
            background-color: #f5f5f5;
        }

        .search-result-item.active {
            background-color: #e6f0ff;
            color: #4a6cf7;
        }

        .no-results {
            padding: 8px 12px;
            color: #666;
            font-size: 13px;
            text-align: center;
        }

        .feature-button.logout {
            background-color: #ff4d4d;
            color: white;
            border: none;
            margin-top: auto;
            margin-bottom: 20px;
        }

        .feature-button.logout:hover {
            background-color: #e60000;
        }

        .feature-button.logout i {
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
    <div class="sidebar">
    <!-- brand -->
    <div class="brand"><?= t('brand_name') ?></div>

    <!-- language switch -->
    <div class="lang-switch" style="margin: 10px 0; text-align: center;">
        <select id="langSwitch">
            <option value="zh" <?= $lang==='zh'?'selected':'' ?>>中文</option>
            <option value="en" <?= $lang==='en'?'selected':'' ?>>English</option>
        </select>
    </div>

    <!-- search-box -->
    <div class="search-box">
        <i class="search-icon">🔍</i>
        <input type="text"
               class="search-input"
               id="searchInput"
               placeholder="<?= t('placeholder_search') ?>">
        <div class="search-results" id="searchResults"></div>
    </div>

    <!-- feature-button -->
    <div class="feature-button" id="newChatButton">
        <i>✚</i> <?= t('button_new_chat') ?>
    </div>
    <div class="conversations-list" id="conversationsList">
        <!-- conversations-list -->
    </div>
    <div class="feature-button" id="clearChatButton">
        <i>🗑</i> <?= t('button_clear_chat') ?>
    </div>
    <div class="feature-button" id="uploadButton">
        <i>📁</i> <?= t('button_upload') ?>
    </div>
    <div class="feature-button" id="morethinkButton">
        <i>💭</i> <?= t('button_morethink') ?>
    </div>

    <div class="spacer"></div>

    <div class="feature-button" id="configButton">
        <i>⚙</i> <?= t('button_config') ?>
    </div>
    <div class="feature-button" id="testApiButton">
        <i>🔄</i> <?= t('button_test_api') ?>
    </div>

    <!-- footer -->
    <div class="footer">
        <?= t('footer_powered') ?><br>
        <?= t('footer_version') ?>
    </div>

    <!-- logout button -->
    <div class="feature-button" id="logoutButton" style="margin-top: auto; margin-bottom: 20px;">
        <i>🚪</i> <?= t('button_logout') ?>
    </div>
</div>

        <div class="main-content">
            <div class="chat-box" id="chatBox">
                <div class="system-message">
                <?=t('chat_welcome')?>
                </div>
            </div>

            <div class="pdf-quick-actions" id="pdfQuickActions" style="display: none;">
                <button class="pdf-quick-button" onclick="sendQuickQuestion('<?= t('pdf_action_overview') ?>')"><?=t('pdf_action_overview')?></button>
                <button class="pdf-quick-button" onclick="sendQuickQuestion('<?= t('pdf_action_extract_toc') ?>')"><?=t('pdf_action_extract_toc')?></button>
                <button class="pdf-quick-button" onclick="sendQuickQuestion('<?= t('pdf_action_key_points') ?>')"><?=t('pdf_action_key_points')?></button>
                <button class="pdf-quick-button" onclick="sendQuickQuestion('<?= t('pdf_action_mcq') ?>')"><?=t('pdf_action_mcq')?></button>
                <button class="pdf-quick-button" onclick="sendQuickQuestion('<?= t('pdf_action_pros_cons') ?>')"><?=t('pdf_action_pros_cons')?></button>
                <button class="pdf-quick-button" onclick="sendQuickQuestion('<?= t('pdf_action_expand') ?>')"><?=t('pdf_action_expand')?></button>
            </div>

            <div class="input-area">
                <div class="file-drop-area" id="fileDropArea">
                    <?=t('file_drop_area')?>
                    <input type="file" id="fileInput" accept=".pdf,.txt,.md,.csv">
                </div>

                <div class="message-input">
                    <textarea id="messageInput" placeholder="<?=t('placeholder_message')?>" onkeydown="if(event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); sendMessage(); }"></textarea>
                    <button class="send-button" id="sendButton"> <?=t('button_send')?></button>
                </div>

                <div class="button-row">
                    <div class="aux-button" id="regenerateButton"> <?=t('button_regenerate')?></div>
                    <div class="aux-button" id="copyButton"> <?=t('button_copy')?></div>
                    <div class="aux-button" id="clearButton"> <?=t('button_clear_input')?></div>
                </div>
            </div>

            <div class="typing-indicator" id="typingIndicator">
            <?=t('typing_indicator')?><span></span><span></span><span></span>
                <button class="stop-thinking-button" id="stopThinkingButton"><?=t('button_stop_thinking')?></button>
            </div>
        </div>
    </div>

    <!-- Configure the modal box -->
    <div class="modal" id="configModal">
        <div class="modal-content">
            <div class="close-modal" id="closeConfigModal">&times;</div>
            <div class="modal-title"><?=t('modal_title_config')?></div>

            <div class="form-group">
                <label for="apiKey"><?=t('label_api_key')?></label>
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
                <label for="modelName"><?=t('label_model_name')?></label>
                <input type="text" id="modelName" placeholder="deepseek-chat">
            </div>

            <button class="modal-button" id="saveConfigButton"><?=t('button_save_config')?></button>
        </div>
    </div>

    <!-- Test Result modal box -->
    <div class="modal" id="testResultModal">
        <div class="modal-content">
            <div class="close-modal" id="closeTestResultModal">&times;</div>
            <div class="modal-title"><?= t('modal_title_test_result') ?></div>
            <div id="testResultContent" style="white-space: pre-wrap; max-height: 400px; overflow-y: auto;"></div>
        </div>
    </div>

    <script>
        // Configuration variable
        let config = {
            apiKey: localStorage.getItem('apiKey') || '',
            apiBaseUrl: localStorage.getItem('apiBaseUrl') || 'http://127.0.0.1:8000',
            wsBaseUrl: localStorage.getItem('wsBaseUrl') || 'ws://127.0.0.1:8000',
            modelName: localStorage.getItem('modelName') || 'deepseek-chat',
            userId: <?php echo json_encode($user_id); ?>,
            username: <?php echo json_encode($username); ?>,
            conversationId: <?php echo $conversation_id ? json_encode($conversation_id) : 'null'; ?>
        };

        // Global variable
        let activeWs = null;
        let currentTaskId = null;
        let lastBotMessage = null;
        let chatHistory = [];
        let pendingFile = null;
        let isInitialized = false;

        // DOM element
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
        const searchInput = document.getElementById('searchInput');
        const finishedTasks = new Set();

        // chech service connection
        async function checkServerConnection() {
            try {
                const response = await fetch(`${config.apiBaseUrl}/api/ping`, {
                    method: 'GET',
                    headers: { 'Content-Type': 'application/json' }
                });

                if (response.ok) {
                    console.log('The server connection is normal.');
                    return true;
                } else {
                    console.error('Server connection anomaly:', response.status);
                    return false;
                }
            } catch (error) {
                console.error('Server connection failed:', error);
                return false;
            }
        }

        // Initialization
        document.addEventListener('DOMContentLoaded', async () => {
            console.log('Page loading and checking the configuration status：');
            console.log('User ID:', config.userId);
            console.log('Current Dialog ID:', config.conversationId);
            console.log('PHP Dialog ID:', '<?php echo session_id(); ?>');

            // Reset Status
            chatBox.innerHTML = '';
            chatHistory = [];
            lastBotMessage = null;
            currentTaskId = null;
            if (activeWs) {
                activeWs.close();
                activeWs = null;
            }

            if (!config.userId) {
                console.error('The user ID was not found. Redirect to the login page');
                window.location.href = 'user_login.php';
                return;
            }

            // Verify whether the current dialogue ID belongs to the current user
            if (config.conversationId) {
                try {
                    const response = await fetch(`db_verify_conversation.php?conversation_id=${config.conversationId}&user_id=${config.userId}`);
                    const result = await response.json();
                    if (!result.valid) {
                        console.error('The current conversation does not belong to this user. Reset the conversation ID');
                        config.conversationId = null;
                        // Update the session status
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
                    console.error('The verification of dialogue ownership failed:', error);
                    config.conversationId = null;
                }
            }

            // First, load the list of conversations
            await loadConversations();

            // If there is a current dialogue ID, load the dialogue history
            if (config.conversationId) {
                await loadChatHistory();
            } else {
                // If there is no current conversation, display a welcome message and do not save it to the database
                addMessageToChat('system', '<?= t('chat_welcome') ?>', false, false);
            }

            // Fill in the configuration form
            apiKeyInput.value = config.apiKey;
            apiBaseUrlInput.value = config.apiBaseUrl;
            wsBaseUrlInput.value = config.wsBaseUrl;
            modelNameInput.value = config.modelName;

            // Set the event listener
            setupEventListeners();
            
            isInitialized = true;
        });

        // Set the event listener
        function setupEventListeners() {
            // Remove the existing event listeners (if any)
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
            searchInput.removeEventListener('input', () => searchConversations(searchInput.value));
            document.removeEventListener('click', handleClickOutside);

            // Add a new event listener
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
            searchInput.addEventListener('input', () => searchConversations(searchInput.value));
            document.addEventListener('click', handleClickOutside);

            // Set the file drag-and-drop area event
            setupFileDropArea();
        }

        // Set the file drag-and-drop area event
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

        // Load the chat history
        async function loadChatHistory() {
            if (!config.conversationId) return;

            try {
                const response = await fetch(`db_get_messages.php?conversation_id=${config.conversationId}`);
                const messages = await response.json();

                // Clear the chat box and history records
                chatBox.innerHTML = '';
                chatHistory = [];

                if (messages.length === 0) {
                    // If there is no historical message, display the welcome message
                    addMessageToChat('system', '<?= t('chat_welcome') ?>', false, false);
                    return;
                }

                // Add historical messages to the chat box and set shouldSaveToDb to false
                messages.forEach(msg => {
                    const type = msg.role === 'assistant' ? 'bot' : msg.role;
                    addMessageToChat(type, msg.content, false, false); // The last parameter, false, indicates that it is not saved to the database
                    chatHistory.push({type: type, content: msg.content});
                });

                // If there are robot messages, save the last one
                const botMessages = messages.filter(msg => msg.role === 'assistant');
                if (botMessages.length > 0) {
                    lastBotMessage = botMessages[botMessages.length - 1].content;
                }
            } catch (error) {
                console.error('Failed to load the chat history:', error);
                addMessageToChat('system', '<?= t('fail_to_reload_history') ?>', true, false);
            }
        }

        // Send message
        async function sendMessage() {
            if (!isInitialized) {
                console.error('The system has not completed initialization');
                addMessageToChat('system', '<?= t('system_initialization') ?>', true);
                return;
            }

            const message = messageInput.value.trim();
            if (!message) return;

            // Disable the send button to prevent duplicate sending
            sendButton.disabled = true;
            
            try {
                // Chech user ID
                if (!config.userId) {
                    console.error('The user ID has not been set');
                    addMessageToChat('system', '<?= t('chat_expired') ?>', true);
                    window.location.href = 'user_login.php';
                    return;
                }

                // First, check the server connection
                if (!await checkServerConnection()) {
                    addMessageToChat('system', '<?= t('unable_connect') ?>', true);
                    return;
                }

                // Add user messages to the chat interface
                addMessageToChat('user', message);

                // Empty the input box and reset the height
                messageInput.value = '';
                messageInput.style.height = 'auto';

                try {
                    // If there is no current conversation, create a new one first
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
                        console.log('Create a conversation response:', createResult);

                        if (createResult.status !== 'success' || !createResult.conversation_id) {
                            throw new Error(createResult.message || 'Create conversation failed');
                        }

                        config.conversationId = createResult.conversation_id;
                        console.log('new conversation id:', config.conversationId);

                        // Update the session ID to the PHP session
                        await fetch('update_session.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                conversation_id: createResult.conversation_id
                            })
                        });

                        // Create a new dialogue button
                        await updateConversationsList(createResult.conversation_id, message.substring(0, 50));
                    }

                    // Get the latest four pieces of news
                    const messagesResponse = await fetch(`db_get_messages.php?conversation_id=${config.conversationId}`);
                    const messages = await messagesResponse.json();
                    const recentMessages = messages.slice(-4).map(msg => msg.content).join('\n');
                    
                    // Combine the latest news and new news
                    const combinedMessage = recentMessages ? `${recentMessages}\n\n new question：${message}` : message;

                    // Save user messages to the database
                    console.log('Save messages to the database:', {
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
                    console.log('Save the message response:', saveResult);

                    if (saveResult.status !== 'success') {
                        throw new Error(saveResult.message || 'Failed to save the message');
                    }

                    // Display the "Inputting" indicator
                    typingIndicator.style.display = 'block';
                    stopThinkingButton.style.display = 'inline-block';

                    // Generate the task ID and call the API
                    const taskId = generateUUID();
                    currentTaskId = taskId;
                    console.log(`Generate a new task ID: ${taskId}`);

                    // Create a WebSocket connection
                    createWebSocketConnection(taskId);

                    // Wait to ensure that the WebSocket connection has been established
                    await new Promise(resolve => setTimeout(resolve, 500));

                    // Send chat messages to the API
                    console.log(`Send a message to the API, task ID: ${taskId}`);
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
                    console.log(`The API response was successful.: ${JSON.stringify(result)}`);

                } catch (error) {
                    console.error('Send message failed:', error);
                    addMessageToChat('system', `<?= t('fault') ?>: ${error.message}`, true);
                    typingIndicator.style.display = 'none';
                    stopThinkingButton.style.display = 'none';
                }
            } finally {
                // Re-enable the send button
                sendButton.disabled = false;
            }
        }

        // Update Conversations List
        async function updateConversationsList(conversationId, title) {
            const conversationsList = document.getElementById('conversationsList');
            
            // Remove the prompt "No historical Dialogue"
            const emptyMessage = conversationsList.querySelector('.empty-conversations-message');
            if (emptyMessage) {
                emptyMessage.remove();
            }

            // Create a new dialogue item
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
            
            // Remove the active class of other conversations
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Add the new conversation to the top of the list
            if (conversationsList.firstChild) {
                conversationsList.insertBefore(item, conversationsList.firstChild);
            } else {
                conversationsList.appendChild(item);
            }
        }

        // Create a WebSocket connection
        function createWebSocketConnection(taskId) {
            // Close the previous connection
            if (activeWs) {
                activeWs.close();
                activeWs = null;
            }

            // Create a new connection
            const ws = new WebSocket(`${config.wsBaseUrl}/ws/${taskId}`);
            activeWs = ws;

            ws.onopen = () => {
                console.log(`The WebSocket connection has been opened: ${taskId}`);
            };

            ws.onmessage = async (event) => {
                const data = JSON.parse(event.data);
                console.log('WebSocket message:', data);

                if (data.status === 'Done' && data.reply) {
                    // Received a complete reply
                    if (finishedTasks.has(taskId)) return;
                    typingIndicator.style.display = 'none';
                    stopThinkingButton.style.display = 'none';
                    
                    // To display the AI reply, set shouldSaveToDb to true because this is a new message
                    addMessageToChat('bot', data.reply, false, true);
                    finishedTasks.add(taskId);
                    
                    // Update the dialogue list
                    await loadConversations();
                } else if (data.type === 'connection_status') {
                    // Handle the connection status message
                    console.log(`Connection Status: ${data.status} - ${data.task_id}`);
                } else if (data.status && data.progress !== undefined) {8
                    // Progress update
                    console.log(`progress status: ${data.progress}% - ${data.status}`);

                    // Handle special messages in PDFS
                    if (data.message && data.status.includes("PDF")) {
                        // Display the PDF processing status message
                        const existingMessage = document.querySelector('.system-message.pdf-processing');
                        if (existingMessage) {
                            // Update the existing messages
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
                    // Handle errors
                    typingIndicator.style.display = 'none';
                    stopThinkingButton.style.display = 'none';
                    addMessageToChat('system', `<?= t('fault') ?>: ${data.error}`, true);
                } else if (data.status && (data.type === 'pdf' || data.type === 'pdf_error' || data.type === 'pdf_timeout' || data.type === 'pdf_unsupported')) {
                    // Handle PDF file responses
                    console.log('Received PDF processing result:', data);
                    typingIndicator.style.display = 'none';
                    stopThinkingButton.style.display = 'none';

                    // Display different messages according to the different states of PDF processing
                    if (data.type === 'pdf_timeout') {
                        // PDF processing timeout, display friendly prompt
                        addMessageToChat('system', data.content);
                        addMessageToChat('system', '<?= t('continue_deal') ?>', false);
                    } else if (data.type === 'pdf_error') {
                        // There was an error in the PDF processing. Display friendly prompts instead of errors
                        addMessageToChat('system', data.content);
                    } else if (data.type === 'pdf_unsupported') {
                        // PDF is not supported. Messages are displayed normally
                        addMessageToChat('system', data.content);
                    } else {
                        //  Normally processed PDF content
                        addMessageToChat('system', `Uploaded: ${data.file_name}`);
                        // Check whether the content exists and is not empty
                        if (data.content && data.content.trim() !== '') {
                            console.log('Add PDF content to chat, length:', data.content.length);
                            addMessageToChat('bot', data.content);
                        } else {
                            console.error('The PDF content is empty or does not exist');
                            addMessageToChat('system', '<?= t('unable_display') ?>', true);
                        }
                    }

                    // Remove the previous PDF processing message
                    const pdfProcessingMsg = document.querySelector('.system-message.pdf-processing');
                    if (pdfProcessingMsg) {
                        pdfProcessingMsg.remove();
                    }
                } else {
                    // Unprocessed message types, recorded for debugging
                    console.log('Unprocessed WebSocket message types:', data);
                }
            };

            ws.onerror = (error) => {
                console.error('WebSocket error:', error);
                // Don't display the error immediately. Instead, try to reconnect
                console.log('Try to obtain the result through the API...');
                setTimeout(() => retrieveResult(taskId), 1000);
            };

            ws.onclose = (event) => {
                console.log(`WebSocket Connection Closed, code: ${event.code}, reason: ${event.reason}`);
                if (activeWs === ws) {
                    activeWs = null;
                }

                // If the connection is closed too early and no response is received, try reconnecting or getting the result
                if (typingIndicator.style.display === 'block') {
                    console.log(`The WebSocket connection has been closed but the task may still be ongoing. Try to reconnect or obtain the result...`);

                    // Try reconnecting the WebSocket first
                    setTimeout(() => {
                        if (typingIndicator.style.display === 'block') {
                            console.log(`Try reconnecting the WebSocket: ${taskId}`);
                            createWebSocketConnection(taskId);

                            // If there is still no result within a short period after reconnection, try to obtain it through the API
                            setTimeout(() => {
                                if (typingIndicator.style.display === 'block') {
                                    console.log('After reconnecting with WebSocket, there was still no response. I attempted to obtain the result through the API...');
                                    if (!finishedTasks.has(taskId)) retrieveResult(taskId);
                                }
                            }, 3000);
                        }
                    }, 1000);
                }
            };

            // Increase ping to keep the connection active
            const pingInterval = setInterval(() => {
                if (ws.readyState === WebSocket.OPEN) {
                    console.log('Send ping to maintain connection');
                    ws.send(JSON.stringify({type: 'ping'}));
                } else {
                    clearInterval(pingInterval);
                }
            }, 30000); // ping once every 30 seconds

            return ws;
        }

        // Obtain the task results through the API
        async function retrieveResult(taskId) {
            try {
                // === Anti-shake: Mark immediately once a request begins to avoid concurrent calls ===;
                if (finishedTasks.has(taskId)) return;
                finishedTasks.add(taskId);
                console.log(`[RESULT] pull ${taskId}`);
                const response = await fetch(`${config.apiBaseUrl}/api/result/${taskId}`);

                if (!response.ok) {
                    throw new Error(`API error: ${response.status}`);
                }

                const result = await response.json();
                console.log(`Obtain the task result:`, result);

                // Hidden loading indicator
                typingIndicator.style.display = 'none';
                stopThinkingButton.style.display = 'none';

                // Check if it is a PDF results
                if (result.type && (result.type === 'pdf' || result.type.startsWith('pdf_'))) {
                    console.log('The PDF result was obtained through the API');
                    // Use the PDF display function
                    displayPdfResult(result);
                    return;
                }

                // Handle ordinary text responses
                if (result.reply) {
                    // Received a valid reply
                    addMessageToChat('bot', result.reply);
                    console.log('The reply was successfully obtained through the API');

                } else if (result.content) {
                    // The content field may contain the result
                    addMessageToChat('bot', result.content);
                    console.log('The content was successfully obtained through the API');
                } else {
                    // The result format is incorrect
                    addMessageToChat('system', '<?= t('fail_to_replay') ?>', true);
                }
            } catch (error) {
                finishedTasks.delete(taskId);
                console.error('Getting result failed:', error);
                typingIndicator.style.display = 'none';
                stopThinkingButton.style.display = 'none';
                addMessageToChat('system', `Fail to get result: ${error.message}`, true);
            }
        }

        // Handle File Upload
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
                addMessageToChat('system', '<?= t('Unsupported_file_type') ?>', true);
                return;
            }

            // File size check (10MB)
            if (file.size > 10 * 1024 * 1024) {
                addMessageToChat('system', '<?= t('file_too_large') ?>', true);
                return;
            }

            // Check server connection
            if (!await checkServerConnection()) {
                addMessageToChat('system', '<?= t('check_server') ?>', true);
                return;
            }

            // Display the message being uploaded
            addMessageToChat('system', `<?= t('upload_file') ?>: ${file.name}...`);

            // If it is a PDF file, special prompts will be displayed
            if (file.type === 'application/pdf' || file.name.endsWith('.pdf')) {
                addMessageToChat('system', '<?= t('note_wait_time') ?>');
            }

            // Display the "Inputting" indicator
            typingIndicator.style.display = 'block';
            stopThinkingButton.style.display = 'inline-block';

            try {
                // generate task ID
                const taskId = generateUUID();
                currentTaskId = taskId;
                console.log(`generate new task ID: ${taskId} (file upload)`);

                // create WebSocket connection
                createWebSocketConnection(taskId);

                // Wait to ensure that the WebSocket connection has been established
                await new Promise(resolve => setTimeout(resolve, 500));

                // Create the FormData object
                const formData = new FormData();
                formData.append('file', file);
                formData.append('task_id', taskId);  // Add task ID

                // Send the upload request
                console.log(`Send file upload request, task ID: ${taskId}`);
                const response = await fetch(`${config.apiBaseUrl}/api/upload`, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`Upload failed: ${response.status} ${await response.text()}`);
                }

                const result = await response.json();
                console.log(`The file upload API response was successful: ${JSON.stringify(result)}`);

                // Set a particularly long timeout specifically for PDF files
                if (file.type === 'application/pdf' || file.name.endsWith('.pdf')) {
                    // For PDF files, use a 5-minute timeout
                    let resultReceived = false;

                    // Set up a polling mechanism to check the results regularly
                    const pollInterval = setInterval(async () => {
                        if (resultReceived || typingIndicator.style.display !== 'block') {
                            clearInterval(pollInterval);
                            return;
                        }

                        console.log(`Poll the inspection task ${taskId}  PDF processing result...`);
                        try {
                            const pollResponse = await fetch(`${config.apiBaseUrl}/api/result/${taskId}`);
                            if (pollResponse.ok) {
                                const pollResult = await pollResponse.json();

                                // Check if there are valid PDF results
                                if (pollResult.type === 'pdf' && pollResult.content && pollResult.content.trim() !== '') {
                                    console.log('Discover the PDF results through polling');
                                    resultReceived = true;

                                    // Display the result using displayPdfResult
                                    typingIndicator.style.display = 'none';
                                    stopThinkingButton.style.display = 'none';
                                    displayPdfResult(pollResult);

                                    // Automatically send the extracted text as the question
                                    if (pollResult.content && pollResult.content.trim() !== '') {
                                        messageInput.value = pollResult.content;
                                        sendMessage();
                                    }

                                    // clear Interval
                                    clearInterval(pollInterval);
                                }
                            }
                        } catch (pollError) {
                            console.error('Poll Error:', pollError);
                        }
                    }, 10000); // Poll every 10 second

                    // Main timeout control
                    setTimeout(() => {
                        if (!resultReceived && typingIndicator.style.display === 'block') {
                            console.log("PDF processing in progress. Keep the connection...");
                            // Do not display the timeout error. Only record it in the console
                        }
                    }, 300000); // 5 minutes
                } else {
                    // For other file types, use the normal timeout
                    setTimeout(() => {
                        if (typingIndicator.style.display === 'block') {
                            console.log("The response timeout might be a WebSocket connection issue");
                            typingIndicator.style.display = 'none';
                            stopThinkingButton.style.display = 'none';
                            addMessageToChat('system', '<?= t('response_timeout') ?>', true);
                        }
                    }, 60000); // 1 minutes
                }

            } catch (error) {
                console.error('File Upload failed:', error);
                addMessageToChat('system', `<?= t('fault') ?>: ${error.message}`, true);
                typingIndicator.style.display = 'none';
                stopThinkingButton.style.display = 'none';
            }

            // Clear the file input
            fileInput.value = '';
        }

        // Add messages to the chat
        function addMessageToChat(type, content, isError = false, shouldSaveToDb = true) {
            // Check whether the same message already exists
            const existingMessages = chatBox.querySelectorAll(`.${type}-message`);
            for (let msg of existingMessages) {
                if (msg.querySelector('.message-content').textContent === content) {
                    console.log('The message already exists. Skip adding it');
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

            // Automatically scroll to the bottom
            chatBox.scrollTop = chatBox.scrollHeight;

            // If it is a robot message, save the final reply
            if (type === 'bot') {
                lastBotMessage = content;
            }

            // Add to chat history
            chatHistory.push({type: type, content: content});

            // It is saved to the database only when shouldSaveToDb is true and it is not an error message
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
                    console.error('Failed to save the message to the database:', error);
                });
            }
        }

        // Clean Chat
        async function clearChat() {
            // Clear the content of the chat box and the historical records
            chatBox.innerHTML = '';
            chatHistory = [];
            lastBotMessage = null;
            
            // Hide the PDF shortcut button
            hidePdfQuickActions();
            
            // Add a welcome message and do not save it to the database
            addMessageToChat('system', '<?= t('chat_welcome') ?>', false, false);
            
            // Reset the current dialogue ID
            config.conversationId = null;
            
            // Update the session ID to the PHP session
            await fetch('update_session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    conversation_id: null
                })
            });

            //  Remove the highlighting of the current active conversation
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.remove('active');
            });
        }

        // Start a new conversation
        function startNewChat() {
            // Close the current WebSocket connection
            if (activeWs) {
                activeWs.close();
                activeWs = null;
            }

            clearChat();
        }

        // Save the configuration
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

            // Close the modal box
            configModal.style.display = 'none';

            // Display the success message
            addMessageToChat('system', '<?= t('button_save_config') ?>');
        }

        // Regenerate the final message
        function regenerateLastMessage() {
            const lastUserMessage = chatHistory.filter(msg => msg.type === 'user').pop();
            if (lastUserMessage) {
                // Delete the last robot message
                const lastBotIndex = chatHistory.findIndex(msg => msg.type === 'bot');
                if (lastBotIndex !== -1) {
                    chatHistory.splice(lastBotIndex, 1);
                    // Update UI
                    const botMessages = document.querySelectorAll('.bot-message');
                    if (botMessages.length > 0) {
                        botMessages[botMessages.length - 1].remove();
                    }
                }

                // Resend the last user message
                messageInput.value = lastUserMessage.content;
                sendMessage();
            } else {
                addMessageToChat('system', '<?= t('unable_regenerated') ?>');
            }
        }

        // Copy the final reply
        function copyLastReply() {
            if (lastBotMessage) {
                navigator.clipboard.writeText(lastBotMessage)
                    .then(() => {
                        addMessageToChat('system', '<?= t('been_copyed') ?>');
                    })
                    .catch(err => {
                        console.error('The text cannot be copied:', err);
                        addMessageToChat('system', '<?= t('fail_to_copy') ?>', true);
                    });
            } else {
                addMessageToChat('system', '<?= t('empty_to_copy') ?>');
            }
        }

        // Clear the input fields
        function clearInputField() {
            messageInput.value = '';
            messageInput.focus();
        }

        //  Test API connection
        async function testApi() {
            //  Display the messages in the test
            addMessageToChat('system', '<?= t('test_api') ?>');

            // Check the server connection
            if (!await checkServerConnection()) {
                addMessageToChat('system', '<?= t('check_server') ?>', true);

                // Display the modal box of the test results
                testResultContent.textContent = `<?= t('api_unconnect') ?> (${config.apiBaseUrl})\n`;
                testResultModal.style.display = 'flex';

                return;
            }

            try {
                const response = await fetch(`${config.apiBaseUrl}/api/test`);

                if (!response.ok) {
                    throw new Error(`API Error: ${response.status}`);
                }

                const result = await response.json();
                console.log('API test result:', result);

                // Prepare the content of the test results
                let resultContent = '';

                if (result.status === 'success') {
                    resultContent += `<?= t('connect_test') ?>\n\n`;
                    resultContent += `🔹 <?= t('server_status') ?>: ${result.server_info.server_status}\n`;
                    resultContent += `🔹 Model: ${result.server_info.model}\n`;
                    resultContent += `🔹 <?= t('api_url') ?>: ${result.server_info.api_base}\n`;
                    resultContent += `🔹 <?= t('response_time') ?>: ${result.server_info.api_response_time_ms.toFixed(2)}ms\n\n`;
                    resultContent += `🔹 <?= t('api_reply') ?>: "${result.reply}"\n`;
                } else {
                    resultContent += `❌ <?= t('api_unconnect2') ?>\n\n`;
                    resultContent += `🔸 <?= t('error_message') ?>: ${result.message}\n`;

                    if (result.server_info) {
                        resultContent += `\n<?= t('server_info') ?>:\n`;
                        resultContent += `🔸 <?= t('server_status') ?>: ${result.server_info.server_status}\n`;
                        resultContent += `🔸 Model: ${result.server_info.model}\n`;
                        resultContent += `🔸 <?= t('api_url') ?>: ${result.server_info.api_base}\n`;

                        if (result.server_info.api_error) {
                            resultContent += `🔸 API error: ${result.server_info.api_error}\n`;
                        }
                    }

                    if (result.detail) {
                        resultContent += `\n<?= t('detailed_error') ?>: ${result.detail}\n`;
                    }
                }

                // Display the modal box of the test results
                testResultContent.textContent = resultContent;
                testResultModal.style.display = 'flex';

                // Display the brief results in the chat
                if (result.status === 'success') {
                    addMessageToChat('system', '<?= t('api_connect') ?>');
                } else {
                    addMessageToChat('system', `<?= t('api_connect_fail') ?>: ${result.message}`, true);
                }

            } catch (error) {
                console.error('<?= t('API connection failed') ?>:', error);

                // Display the error in the chat
                addMessageToChat('system', `<?= t('api_connect_fail') ?>: ${error.message}`, true);

                // Display the modal box of the test results
                testResultContent.textContent = `❌ API connection test failed \n\n🔸 error message: ${error.message}\n\n Possible cause :\n- Backend service not running \n- Incorrect API base URL (${config.apiBaseUrl})\n- network connection issue`;
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

        // Stop thinking / generating
        function stopThinking() {
            console.log("The user clicks the 'Stop Thinking' button");

            // If the WebSocket connection exists, send a stop message
            if (activeWs && activeWs.readyState === WebSocket.OPEN) {
                try {
                    console.log(`Send the stop thinking message to WebSocket: ${currentTaskId}`);
                    activeWs.send(JSON.stringify({
                        type: "stop_thinking",
                        task_id: currentTaskId
                    }));
                } catch (error) {
                    console.error("Failed to send the stop thinking message:", error);
                }
            }

            // Close the current WebSocket connection
            if (activeWs) {
                console.log(`Close the WebSocket connection: ${currentTaskId}`);
                activeWs.close();
                activeWs = null;
            }

            // Hide the loading indicator
            typingIndicator.style.display = 'none';
            stopThinkingButton.style.display = 'none';

            // Add system messages
            addMessageToChat('system', '<?= t('stop_generating') ?>');
        }

        // Display the PDF result
        function displayPdfResult(resultJson) {
            try {
                // If null or undefined is passed in, return failure directly
                if (!resultJson) {
                    console.error('Failed to display PDF result: Result is empty');
                    addMessageToChat('system', '<?= t('unable_display') ?>', true);
                    return false;
                }

                // Parse the string into a JSON object (if it has not been parsed yet)
                const result = typeof resultJson === 'string' ? JSON.parse(resultJson) : resultJson;

                console.log('Manually display PDF processing result:', result);

                // Verify the necessary fields
                if (!result.type) {
                    console.error('The PDF result is missing the type field:', result);
                    addMessageToChat('system', '<?= t('pdf_deal') ?>', true);
                    return false;
                }

                //  Hide any loading indicators
                typingIndicator.style.display = 'none';
                stopThinkingButton.style.display = 'none';

                // Display the uploaded file name
                if (result.file_name) {
                    addMessageToChat('system', `<?= t('uploaded') ?>: ${result.file_name}`);
                }

                // Handle different responses according to the type
                if (result.type === 'pdf_timeout') {
                    addMessageToChat('system', result.content);
                    addMessageToChat('system', '<?= t('continue_deal') ?>', false);
                } else if (result.type === 'pdf_error') {
                    addMessageToChat('system', result.content);
                } else if (result.type === 'pdf_unsupported') {
                    addMessageToChat('system', result.content);
                } else if (result.type === 'pdf') {
                    // Check if the content contains any content
                    if (result.content && result.content.trim() !== '') {
                        console.log('Display PDF content，length:', result.content.length);
                        // Display the PDF content as the robot's reply
                        addMessageToChat('bot', result.content);
                        // Display quick buttons
                        showPdfQuickActions();
                    } else {
                        console.error('PDF content is empty:', result);
                        addMessageToChat('system', '<?= t('unable_display') ?>', true);
                    }
                } else {
                    console.warn('Unknown PDF result type:', result.type);
                    // Try to display the content, regardless of the type
                    if (result.content) {
                        addMessageToChat('bot', result.content);
                        // Display quick buttons
                        showPdfQuickActions();
                    } else {
                        addMessageToChat('system', '<?= t('unknown_deal') ?>', true);
                    }
                }

                // Remove the message in processing
                const pdfProcessingMsg = document.querySelector('.system-message.pdf-processing');
                if (pdfProcessingMsg) {
                    pdfProcessingMsg.remove();
                }

                return true;
            } catch (e) {
                console.error('An error occurred when parsing or displaying the PDF result:', e);
                console.error('original result:', resultJson);
                addMessageToChat('system', `<?= t('display_pdf_error') ?>: ${e.message}`, true);
                return false;
            }
        }

        // Add sample data for testing
        // The test results can be displayed by entering "displayLatestPdfResult()" in the browser console
        function displayLatestPdfResult() {
            const pdfResult = {"status": "success", "type": "pdf", "task_id": "783fe527-7b11-4fc7-9677-afe42220135d", "file_name": "783fe527-7b11-4fc7-9677-afe42220135d_fa9010e257bbb7782f3a4b1b3dacd4be.pdf", "content": "**Abstract**  \n• PG-SAM integrates medical LLMs (Large Language Models) to enhance multi-organ segmentation accuracy  \n• Proposed fine-grained modality prior aligner bridges domain gaps between text and medical images  \n• Multi-level feature fusion and iterative mask optimizer improve boundary precision  \n• Achieves state-of-the-art performance on Synapse dataset with $84.79\\%$ mDice  \n\n**Introduction**  \n• Segment Anything Model (SAM) underperforms in medical imaging due to domain gaps  \n• Existing methods suffer from coarse text priors and misaligned modality fusion  \n• PG-SAM introduces medical LLMs for fine-grained anatomical text prompts  \n• Key innovation: Joint optimization of semantic alignment and pixel-level details  \n\n**Related Work**  \n• Prompt-free SAM variants (e.g., SAMed, H-SAM) lack domain-specific priors  \n• CLIP-based alignment methods (e.g., TP-DRSeg) face granularity limitations  \n• Medical LLMs show potential but require integration with visual features  \n• PG-SAM uniquely combines LoRA-tuned CLIP with hierarchical feature fusion  \n\n**Methodology**  \n• Fine-grained modality prior aligner generates Semantic Guide Matrix $G \\in \\mathbb{R}^{B \\times L \\times L}$  \n• Multi-level feature fusion uses deformable convolution for edge preservation:  \n  $$F_{\\text{fusion}} = \\phi(F_{\\text{up}}^{(2)}) + \\psi(\\text{Align}(G; \\theta))$$  \n• Iterative mask optimizer employs hypernetwork for dynamic kernel generation:  \n  $$\\Omega_i = \\text{MLP}(m_i) \\odot W_{\\text{base}}$$  \n\n**Experiment**  \n• Synapse dataset: 3,779 CT slices with 8 abdominal organs  \n• Achieves $84.79\\%$ mDice (fully supervised) and $75.75\\%$ (10% data)  \n• Reduces HD95 to $7.61$ (↓$5.68$ vs. H-SAM) for boundary precision  \n• Ablation shows $+4.69\\%$ mDice gain from iterative mask optimization  \n\n**Conclusion**  \n• PG-SAM outperforms SOTA by integrating medical LLMs with SAM  \n• Fine-grained priors and multi-level fusion address modality misalignment  \n• Future work: Extend to 3D segmentation and real-time clinical applications  \n• Code available at https://github.com/logan-0623/PG-SAM"};
            displayPdfResult(pdfResult);
        }

        // Comment out the code that automatically displays the sample results to make it no longer run automatically
        // setTimeout(displayLatestPdfResult, 1000);

        // Add the function for loading the conversation list
        async function loadConversations() {
            try {
                console.log('loading the list of conversations...');
                const response = await fetch(`db_get_conversations.php?user_id=${config.userId}`);
                const conversations = await response.json();
                console.log('Get the list of conversations:', conversations);
                
                const conversationsList = document.getElementById('conversationsList');
                conversationsList.innerHTML = '';
                
                if (conversations.length === 0) {
                    // If there is no dialogue, display a prompt
                    const emptyMessage = document.createElement('div');
                    emptyMessage.className = 'empty-conversations-message';
                    emptyMessage.textContent = '<?=t('message_no_history')?>';
                    conversationsList.appendChild(emptyMessage);
                    return;
                }
                
                conversations.forEach(conv => {
                    // Only display the conversations belonging to the current user
                    if (parseInt(conv.user_id) === parseInt(config.userId)) {
                        const item = document.createElement('div');
                        item.className = 'conversation-item';
                        if (parseInt(conv.id) === parseInt(config.conversationId)) {
                            item.classList.add('active');
                        }
                        
                        const title = document.createElement('div');
                        title.className = 'conversation-title';
                        title.textContent = conv.title || 'New Cconversation';
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
                console.error('Failed to load the dialogue list:', error);
                addMessageToChat('system', '<?= t('load_diolog_error') ?>', true);
            }
        }

        // Switch conversation
        async function switchConversation(conversationId) {
            try {
                // Verify dialogue ownership
                const response = await fetch(`db_verify_conversation.php?conversation_id=${conversationId}&user_id=${config.userId}`);
                const result = await response.json();
                if (!result.valid) {
                    console.error('No right to access this conversation');
                    addMessageToChat('system', '<?= t('No_access_conversations') ?>', true);
                    return;
                }

                console.log('Switch to Conversation:', conversationId);
                config.conversationId = conversationId;
                
                // Update the session ID to the PHP session
                await fetch('update_session.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        conversation_id: conversationId
                    })
                });
                
                // Clear the current chat box
                chatBox.innerHTML = '';
                chatHistory = [];
                
                // Reload the message
                await loadChatHistory();
                
                // Update the dialogue list UI
                document.querySelectorAll('.conversation-item').forEach(item => {
                    item.classList.remove('active');
                    const titleElem = item.querySelector('.conversation-title');
                    if (parseInt(titleElem.dataset.id) === parseInt(conversationId)) {
                        item.classList.add('active');
                    }
                });
                
            } catch (error) {
                console.error('Conversation switching failed:', error);
                addMessageToChat('system', '<?= t('fail_switch_dialog') ?>', true);
            }
        }

        // Delete the conversation
        async function deleteConversation(conversationId) {
            if (!confirm('<?= t('determined_delete') ?>')) {
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
                    // Remove the dialogue button from the interface
                    const conversationItems = document.querySelectorAll('.conversation-item');
                    for (let item of conversationItems) {
                        const titleElem = item.querySelector('.conversation-title');
                        if (titleElem && parseInt(titleElem.dataset.id) === parseInt(conversationId)) {
                            item.remove();
                            break;
                        }
                    }

                    // If what is deleted is the current conversation, clear the chat content
                    if (parseInt(conversationId) === parseInt(config.conversationId)) {
                        chatBox.innerHTML = '';
                        addMessageToChat('system', '<?= t('chat_welcome') ?>', false, false);
                        config.conversationId = null;
                    }

                    // Check if there are any other conversations
                    const conversationsList = document.getElementById('conversationsList');
                    if (conversationsList.children.length === 0) {
                        // If there is no dialogue, display a prompt message
                        const emptyMessage = document.createElement('div');
                        emptyMessage.className = 'empty-conversations-message';
                        emptyMessage.textContent = '<?=t('message_no_history')?>';
                        conversationsList.appendChild(emptyMessage);
                    }
                } else {
                    throw new Error(result.message || 'Failed to delete the conversation');
                }
            } catch (error) {
                console.error('Failed to delete the conversation:', error);
                addMessageToChat('system', '<?= t('fail_delete_dialog') ?>', true);
            }
        }

        // Switch the model
        function toggleModel() {
            if (config.modelName === 'deepseek-chat') {
                config.modelName = 'deepseek-reasoner';
                morethinkButton.classList.add('active');
                morethinkButton.innerHTML = '<i>💭</i> Morethink (<?= t('been_used') ?>)';
            } else {
                config.modelName = 'deepseek-chat';
                morethinkButton.classList.remove('active');
                morethinkButton.innerHTML = '<i>💭</i> Morethink';
            }
            localStorage.setItem('modelName', config.modelName);
        }

        // Display the PDF shortcut button
        function showPdfQuickActions() {
            const quickActions = document.getElementById('pdfQuickActions');
            quickActions.style.display = 'flex';
        }

        // Hide the PDF shortcut button
        function hidePdfQuickActions() {
            const quickActions = document.getElementById('pdfQuickActions');
            quickActions.style.display = 'none';
        }

        // Send quick questions
        function sendQuickQuestion(questionType) {
            messageInput.value = questionType;
            sendMessage();
        }

        //  Search the conversation
        function searchConversations(searchText) {
            const searchResults = document.getElementById('searchResults');
            const conversationItems = document.querySelectorAll('.conversation-item');
            const searchLower = searchText.toLowerCase();
            let hasResults = false;
            
            // Clear the previous results
            searchResults.innerHTML = '';
            
            if (searchText.trim() === '') {
                searchResults.style.display = 'none';
                return;
            }
            
            conversationItems.forEach(item => {
                const title = item.querySelector('.conversation-title').textContent;
                const conversationId = item.querySelector('.conversation-title').dataset.id;
                
                if (title.toLowerCase().includes(searchLower)) {
                    hasResults = true;
                    const resultItem = document.createElement('div');
                    resultItem.className = 'search-result-item';
                    resultItem.textContent = title;
                    resultItem.onclick = () => {
                        switchConversation(conversationId);
                        searchResults.style.display = 'none';
                        searchInput.value = '';
                    };
                    searchResults.appendChild(resultItem);
                }
            });
            
            if (!hasResults) {
                const noResults = document.createElement('div');
                noResults.className = 'no-results';
                noResults.textContent = '<?= t('no_diolog') ?>';
                searchResults.appendChild(noResults);
            }
            
            searchResults.style.display = 'block';
        }

        // Handle clicking on the external area to close the drop-down box
        function handleClickOutside(event) {
            const searchBox = document.querySelector('.search-box');
            if (!searchBox.contains(event.target)) {
                document.getElementById('searchResults').style.display = 'none';
            }
        }

        // Add event listeners for the logout button
        const logoutButton = document.getElementById('logoutButton');
				logoutButton.addEventListener('click', async () => {
    				if (confirm('<?= t('confirm_logout') ?>')) {
        				try {
            				const response = await fetch('user_logout.php', {
                				method: 'POST',
                				headers: {
                    				'Content-Type': 'application/json',
                				}
            				});

            				const result = await response.json();
            
            				if (result.success) {
                				localStorage.clear();
                				// Use the redirect or default path returned by the server
                				window.location.href = result.redirect || 'user_login.php';
            				} else {
                				throw new Error(result.message || '<?= t('logout_failed') ?>');
            				}
        				} catch (error) {
            				console.error('Logout failed:', error);
            				addMessageToChat('system', `<?= t('fault') ?>: ${error.message}`, true);
        				}
    				}
				});
    </script>
   <script>
  document.getElementById('langSwitch').addEventListener('change', e => {
    const params = new URLSearchParams(window.location.search);
    params.set('lang', e.target.value);
    window.location.search = params.toString();
  });
</script>
</body>
</html>
