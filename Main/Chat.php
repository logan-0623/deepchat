<?php
require 'vendor/autoload.php';

use Smalot\PdfParser\Parser;

class LightPDFProcessor {
    private $pdfPath;
    private $taskId;
    private $cacheDir;
    private $apiKey = "";
    private $apiUrl = "https://api.deepseek.com/v1/chat/completions";

    public function __construct($pdfPath) {
        $this->pdfPath = $pdfPath;
        $this->taskId = md5_file($pdfPath);
        $this->cacheDir = "pdf_cache/" . $this->taskId;
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }

    private function parsePDF() {
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($this->pdfPath);
            $text = [];
            foreach ($pdf->getPages() as $page) {
                $pageText = $page->getText();
                $cleanedText = preg_replace('/\s+/', ' ', trim($pageText));
                if (!empty($cleanedText)) {
                    $text[] = $cleanedText;
                }
            }
            return implode("\n\n", $text);
        } catch (Exception $e) {
            throw new Exception("PDF解析失败: " . $e->getMessage());
        }
    }

    public function generateSummary() {
        $cacheFile = $this->cacheDir . "/structured_abstract.json";

        // 检查缓存
        if (file_exists($cacheFile)) {
            return json_decode(file_get_contents($cacheFile), true)['abstract'];
        }

        // 解析PDF
        $rawText = $this->parsePDF();
        if (empty($rawText)) {
            throw new Exception("无法提取有效文本内容");
        }

        // 构建提示词
        $structuredPrompt = "请根据以下研究内容生成结构化学术摘要，严格遵循以下格式要求：\n\n"
            . "# 格式规范\n"
            . "1. 使用以下六级标题结构（加粗）：\n"
            . "**Abstract**\n**Introduction**\n**Related Work**\n**Methodology**\n**Experiment**\n**Conclusion**\n\n"
            . "# 研究内容输入\n" . $rawText;

        // 调用API
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer " . $this->apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode([
                "model" => "deepseek-chat",
                "messages" => [["role" => "user", "content" => $structuredPrompt]],
                "temperature" => 0.3,
                "max_tokens" => 1500,
                "top_p" => 0.9
            ])
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception("API请求失败: " . curl_error($ch));
        }
        curl_close($ch);

        $result = json_decode($response, true);
        if (!isset($result['choices'][0]['message']['content'])) {
            throw new Exception("API响应格式错误");
        }

        $abstract = $result['choices'][0]['message']['content'];

        // 保存缓存
        file_put_contents($cacheFile, json_encode(["abstract" => $abstract]));

        return $abstract;
    }

    public function saveSummaryToFile() {
        $summary = $this->generateSummary();
        $dir = dirname($this->pdfPath);
        $filename = pathinfo($this->pdfPath, PATHINFO_FILENAME) . "_structured_abstract.txt";
        $path = $dir . "/" . $filename;
        file_put_contents($path, $summary);
        return $path;
    }
}

// Web API 使用示例
header("Content-Type: application/json");

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("仅支持POST请求");
    }

    if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("文件上传失败");
    }

    $tmpPath = $_FILES['pdf']['tmp_name'];
    $processor = new LightPDFProcessor($tmpPath);

    $summary = $processor->generateSummary();
    $savePath = $processor->saveSummaryToFile();

    echo json_encode([
        "status" => "success",
        "summary" => $summary,
        "saved_path" => $savePath
    ]);
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}