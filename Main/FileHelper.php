<?php
/**
 * Reuse the validation/save logic from ChatBackend::handleFileUpload()
 */
class FileHelper
{
    private array $config;
    private string $uploadDir;

    public function __construct()
    {
        // Load config.json (consistent with ChatBackend)
        $configFile = __DIR__ . '/../config.json';
        if (file_exists($configFile)) {
            $this->config = json_decode(file_get_contents($configFile), true);
        } else {
            // Default configuration
            $this->config = [
                'upload_max_size' => 10 * 1024 * 1024,
                'allowed_types'   => ['application/pdf', 'text/plain']
            ];
        }

        $this->uploadDir = __DIR__ . '/../../uploads/' . date('Y-m-d');
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }

    public function save(array $file): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload failed: {$file['error']}");
        }
        if ($file['size'] > $this->config['upload_max_size']) {
            $mb = $this->config['upload_max_size'] / 1048576;
            throw new Exception("File size exceeds limit (max {$mb}MB)");
        }

        // MIME type validation
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mimeType, $this->config['allowed_types'])) {
            throw new Exception('Unsupported file type (only PDF / TXT allowed)');
        }

        $taskId     = date('YmdHis') . '_' . substr(md5(uniqid()), 0, 8);
        $fileName   = $taskId . '_' . basename($file['name']);
        $targetPath = "{$this->uploadDir}/{$fileName}";
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Failed to save file');
        }

        return [
            'task_id'   => $taskId,
            'file_name' => $fileName,
            'type'      => $mimeType === 'application/pdf' ? 'pdf' : 'text',
            'path'      => $targetPath
        ];
    }
}