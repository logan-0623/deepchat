<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

require_once 'Chat.php';  // Include the PDF handling class

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
                'message' => 'JSON encoding error'
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
    throw new Exception("File upload failed");
}

// Add file size limit (10MB)
$maxFileSize = 10 * 1024 * 1024; // 10MB in bytes
if ($file['size'] > $maxFileSize) {
    throw new Exception("File size exceeds limit (max 10MB)");
}

// Validate file type
$allowedTypes = ['application/pdf', 'text/plain'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    throw new Exception("Unsupported file type (only PDF and TXT allowed)");
}

$fileName = uniqid() . '_' . basename($file['name']);
$targetPath = $this->uploadDir . $fileName;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // If it's a PDF file, use the PDF processor class
    if ($mimeType === 'application/pdf') {
        $processor = new LightPDFProcessor($targetPath);
        return $processor->generateSummary();
    }
    // If it's a text file, return upload success message directly
    return "File {$fileName} uploaded successfully. How can I assist you further?";
}

throw new Exception("Failed to save file");
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
                CURLOPT_POST        => true,
                CURLOPT_HTTPHEADER  => [
                    "Content-Type: application/json",
                    "Authorization: Bearer " . $this->apiKey
                ],
                CURLOPT_POSTFIELDS     => json_encode($data),
                CURLOPT_SSL_VERIFYPEER => false // Add this line to handle potential SSL issues
            ]);
            
            $response = curl_exec($ch);
            
            if (curl_errno($ch)) {
                error_log('Curl error: ' . curl_error($ch));
                throw new Exception("API request failed: " . curl_error($ch));
            }
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                error_log('API returned non-200 status code: ' . $httpCode . ', Response: ' . $response);
                throw new Exception("API returned error status code: " . $httpCode);
            }
            
            if (empty($response)) {
                error_log('Empty response from API');
                throw new Exception("API returned an empty response");
            }
            
            error_log('API Response: ' . $response); // Log the API response
            
            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('JSON decode error: ' . json_last_error_msg());
                throw new Exception("Failed to parse API response: " . json_last_error_msg());
            }
            
            if (!isset($result['choices'][0]['message']['content'])) {
                error_log('Invalid API response structure: ' . print_r($result, true));
                throw new Exception("API response format error");
            }
            
            return $result['choices'][0]['message']['content'];
            } catch (Exception $e) {
                error_log('Generate AI Response error: ' . $e->getMessage());
                throw new Exception("Failed to generate response: " . $e->getMessage());
        }
    }

    public function handleRequest() {
        try {
            // Get input data
            $rawInput = file_get_contents('php://input');
            if (empty($rawInput)) {
                throw new Exception("No input data received");
            }
    
            $input = json_decode($rawInput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON parse error: " . json_last_error_msg());
            }
    
            $message = isset($input['message']) ? trim($input['message']) : '';
            if (empty($message)) {
                throw new Exception("Message cannot be empty");
            }
    
            // Handle file upload
            $fileResponse = $this->handleFileUpload();
            if ($fileResponse) {
                $this->sendJsonResponse([
                    'status' => 'success',
                    'reply'  => $fileResponse
                ]);
                return;
            }
    
            // Generate AI response
            $reply = $this->generateAIResponse($message);
    
            // Return successful response
            $this->sendJsonResponse([
                'status' => 'success',
                'reply'  => $reply
            ]);
    
        } catch (Exception $e) {
            $this->handleError($e->getMessage());
        }
    }
}
    // Instantiate and handle request
try {
        $chatBackend = new ChatBackend();
        $chatBackend->handleRequest();
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 'error',
            'message' => 'System error: ' . $e->getMessage()
        ]);
    }