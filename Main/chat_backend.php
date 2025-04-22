<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// 添加错误日志
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

require_once 'Chat.php';  // 引入PDF处理类

class ChatBackend {
    private $apiKey = "";
    private $apiUrl = "https://api.deepseek.com/v1/chat/completions";
    private $uploadDir = "uploads/";

    public function __construct() {
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }

    private function sendJsonResponse($data) {
        if (headers_sent()) {
            error_log('Headers already sent when trying to send JSON response');
        }

        $jsonResponse = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($jsonResponse === false) {
            error_log('JSON encode error: ' . json_last_error_msg());
            $jsonResponse = json_encode([
                'status' => 'error',
                'message' => 'JSON编码错误'
            ]);
        }

        echo $jsonResponse;
        exit;
    }

    private function handleError($message) {
        error_log('Error occurred: ' . $message);
        $this->sendJsonResponse([
            'status' => 'error',
            'message' => $message
        ]);
    }

    private function handleFileUpload() {
        if (!isset($_FILES['file'])) {
            return null;
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("文件上传失败");
        }

        // 添加文件大小限制 (10MB)
        $maxFileSize = 10 * 1024 * 1024; // 10MB in bytes
        if ($file['size'] > $maxFileSize) {
            throw new Exception("文件大小超过限制（最大10MB）");
        }

        // 验证文件类型
        $allowedTypes = ['application/pdf', 'text/plain'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception("不支持的文件类型（仅支持PDF和TXT）");
        }

        $fileName = uniqid() . '_' . basename($file['name']);
        $targetPath = $this->uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // 如果是PDF文件，使用PDF处理类
            if ($mimeType === 'application/pdf') {
                $processor = new LightPDFProcessor($targetPath);
                return $processor->generateSummary();
            }
            // 如果是文本文件，直接返回上传成功消息
            return "文件 {$fileName} 上传成功。请问有什么需要我帮助的吗？";
        }

        throw new Exception("文件保存失败");
    }

    private function generateAIResponse($message) {
        try {
            $ch = curl_init($this->apiUrl);

            $data = [
                "model" => "deepseek-chat",
                "messages" => [
                    ["role" => "user", "content" => $message]
                ],
                "temperature" => 0.7,
                "max_tokens" => 1000
            ];

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "Authorization: Bearer " . $this->apiKey
                ],
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_SSL_VERIFYPEER => false // 添加这行以处理可能的SSL问题
            ]);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                error_log('Curl error: ' . curl_error($ch));
                throw new Exception("API请求失败: " . curl_error($ch));
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                error_log('API returned non-200 status code: ' . $httpCode . ', Response: ' . $response);
                throw new Exception("API返回错误状态码: " . $httpCode);
            }

            if (empty($response)) {
                error_log('Empty response from API');
                throw new Exception("API返回空响应");
            }

            error_log('API Response: ' . $response); // 记录API响应

            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('JSON decode error: ' . json_last_error_msg());
                throw new Exception("API响应解析失败: " . json_last_error_msg());
            }

            if (!isset($result['choices'][0]['message']['content'])) {
                error_log('Invalid API response structure: ' . print_r($result, true));
                throw new Exception("API响应格式错误");
            }

            return $result['choices'][0]['message']['content'];
        } catch (Exception $e) {
            error_log('Generate AI Response error: ' . $e->getMessage());
            throw new Exception("生成回复失败: " . $e->getMessage());
        }
    }

    public function handleRequest() {
        try {
            // 获取输入数据
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

            // 处理文件上传
            $fileResponse = $this->handleFileUpload();
            if ($fileResponse) {
                $this->sendJsonResponse([
                    'status' => 'success',
                    'reply' => $fileResponse
                ]);
                return;
            }

            // 生成AI响应
            $reply = $this->generateAIResponse($message);

            // 返回成功响应
            $this->sendJsonResponse([
                'status' => 'success',
                'reply' => $reply
            ]);

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