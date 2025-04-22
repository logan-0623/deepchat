<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'chat_logs.log');

class ChatBackend {
    private $config;
    private $uploadDir;
    private $cacheDir;

    public function __construct() {
        $this->loadConfig();
        $this->setupDirectories();
    }

    private function loadConfig() {
        // 从配置文件加载配置
        $configFile = 'config.json';
        if (file_exists($configFile)) {
            $this->config = json_decode(file_get_contents($configFile), true);
        } else {
            // 默认配置
            $this->config = [
                'api_key' => '',
                'api_base' => 'https://api.deepseek.com/v1',
                'model' => 'deepseek-chat',
                'temperature' => 0.7,
                'max_tokens' => 1000,
                'upload_max_size' => 10 * 1024 * 1024, // 10MB
                'allowed_types' => ['application/pdf', 'text/plain']
            ];
            file_put_contents($configFile, json_encode($this->config, JSON_PRETTY_PRINT));
        }
    }

    private function setupDirectories() {
        $this->uploadDir = "uploads/" . date("Y-m-d");
        $this->cacheDir = "cache/" . date("Y-m-d");

        foreach ([$this->uploadDir, $this->cacheDir] as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
        }
    }

    private function generateTaskId() {
        return date("YmdHis") . '_' . substr(md5(uniqid()), 0, 8);
    }

    private function logMessage($message, $level = 'info') {
        $logMessage = date('[Y-m-d H:i:s]') . " [{$level}] " . $message . PHP_EOL;
        error_log($logMessage, 3, 'chat_logs.log');
    }

    private function sendJsonResponse($data) {
        if (headers_sent()) {
            $this->logMessage('Headers already sent when trying to send JSON response', 'error');
        }

        $jsonResponse = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($jsonResponse === false) {
            $this->logMessage('JSON encode error: ' . json_last_error_msg(), 'error');
            $jsonResponse = json_encode([
                'status' => 'error',
                'message' => 'JSON编码错误'
            ]);
        }

        echo $jsonResponse;
        exit;
    }

    private function handleError($message, $code = 500) {
        $this->logMessage($message, 'error');
        $this->sendJsonResponse([
            'status' => 'error',
            'code' => $code,
            'message' => $message
        ]);
    }

    private function callLLMApi($message, $taskId = null) {
        try {
            $ch = curl_init($this->config['api_base'] . '/chat/completions');

            $data = [
                "model" => $this->config['model'],
                "messages" => [
                    ["role" => "user", "content" => $message]
                ],
                "temperature" => $this->config['temperature'],
                "max_tokens" => $this->config['max_tokens']
            ];

            $headers = [
                "Content-Type: application/json",
                "Authorization: Bearer " . $this->config['api_key']
            ];

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            $startTime = microtime(true);
            $response = curl_exec($ch);
            $endTime = microtime(true);

            if (curl_errno($ch)) {
                throw new Exception("API请求失败: " . curl_error($ch));
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // 记录API调用信息
            $this->logMessage(sprintf(
                "API Call - Task: %s, Duration: %.2fs, Status: %d",
                $taskId ?? 'no-task',
                $endTime - $startTime,
                $httpCode
            ));

            if ($httpCode !== 200) {
                throw new Exception("API返回错误状态码: " . $httpCode);
            }

            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("API响应解析失败: " . json_last_error_msg());
            }

            if (!isset($result['choices'][0]['message']['content'])) {
                throw new Exception("API响应格式错误");
            }

            return [
                'status' => 'success',
                'reply' => $result['choices'][0]['message']['content'],
                'task_id' => $taskId,
                'response_time' => round($endTime - $startTime, 3)
            ];

        } catch (Exception $e) {
            $this->logMessage("LLM API Error: " . $e->getMessage(), 'error');
            throw $e;
        }
    }

    private function handleFileUpload($file) {
        try {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("文件上传失败: " . $file['error']);
            }

            if ($file['size'] > $this->config['upload_max_size']) {
                throw new Exception("文件大小超过限制（最大" . ($this->config['upload_max_size'] / 1024 / 1024) . "MB）");
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $this->config['allowed_types'])) {
                throw new Exception("不支持的文件类型（仅支持PDF和TXT）");
            }

            $taskId = $this->generateTaskId();
            $fileName = $taskId . '_' . basename($file['name']);
            $targetPath = $this->uploadDir . '/' . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new Exception("文件保存失败");
            }

            // 如果是PDF文件，使用PDF处理类
            if ($mimeType === 'application/pdf') {
                require_once 'Chat.php';
                $processor = new LightPDFProcessor($targetPath);
                $summary = $processor->generateSummary();
                return [
                    'status' => 'success',
                    'type' => 'pdf',
                    'task_id' => $taskId,
                    'file_name' => $fileName,
                    'summary' => $summary
                ];
            }

            // 如果是文本文件，读取内容
            $content = file_get_contents($targetPath);
            return [
                'status' => 'success',
                'type' => 'text',
                'task_id' => $taskId,
                'file_name' => $fileName,
                'content' => $content
            ];

        } catch (Exception $e) {
            $this->logMessage("File Upload Error: " . $e->getMessage(), 'error');
            throw $e;
        }
    }

    public function handleRequest() {
        try {
            // 获取输入数据
            if (isset($_FILES['file'])) {
                $result = $this->handleFileUpload($_FILES['file']);
                $this->sendJsonResponse($result);
                return;
            }

            $rawInput = file_get_contents('php://input');
            if (empty($rawInput)) {
                throw new Exception("没有接收到输入数据");
            }

            $input = json_decode($rawInput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON解析错误: " . json_last_error_msg());
            }

            $message = isset($input['message']) ? trim($input['message']) : '';
            if (empty($message)) {
                throw new Exception("消息不能为空");
            }

            $taskId = $this->generateTaskId();
            $result = $this->callLLMApi($message, $taskId);
            $this->sendJsonResponse($result);

        } catch (Exception $e) {
            $this->handleError($e->getMessage());
        }
    }
}

// 实例化并处理请求
try {
    $chatBackend = new ChatBackend();
    $chatBackend->handleRequest();
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => '系统错误: ' . $e->getMessage()
    ]);
}