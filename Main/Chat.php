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
            throw new Exception("PDF parsing failed: " . $e->getMessage());
        }
    }

    public function generateSummary() {
        $cacheFile = $this->cacheDir . "/structured_abstract.json";

        // Check cache
        if (file_exists($cacheFile)) {
            return json_decode(file_get_contents($cacheFile), true)['abstract'];
        }

        // Parse PDF
        $rawText = $this->parsePDF();
        if (empty($rawText)) {
            throw new Exception("Unable to extract valid text content");
        }

        // Build prompt
        $structuredPrompt = "Please generate a structured academic abstract based on the following research content, strictly following the format requirements:\n\n"
            . "# Format Specification\n"
            . "1. Use the following six-level heading structure (bold):\n"
            . "**Abstract**\n**Introduction**\n**Related Work**\n**Methodology**\n**Experiment**\n**Conclusion**\n\n"
            . "# Research Content Input\n" . $rawText;

        // Call API
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
            throw new Exception("API request failed: " . curl_error($ch));
        }
        curl_close($ch);

        $result = json_decode($response, true);
        if (!isset($result['choices'][0]['message']['content'])) {
            throw new Exception("API response format error");
        }

        $abstract = $result['choices'][0]['message']['content'];

        // Save cache
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

// Web API usage example
header("Content-Type: application/json");

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Only POST requests are supported");
    }

    if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("File upload failed");
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