<?php
/**
 * 复用原 ChatBackend::handleFileUpload() 里的校验 / 保存逻辑
 */
class FileHelper
{
    private array $config;
    private string $uploadDir;

    public function __construct()
    {
        // 读取 config.json（与 ChatBackend 保持一致）
        $configFile = __DIR__ . '/../config.json';
        if (file_exists($configFile)) {
            $this->config = json_decode(file_get_contents($configFile), true);
        } else {
            // 默认配置
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
            throw new Exception("文件上传失败: {$file['error']}");
        }
        if ($file['size'] > $this->config['upload_max_size']) {
            $mb = $this->config['upload_max_size'] / 1048576;
            throw new Exception("文件大小超过限制（最大 {$mb}MB）");
        }

        // MIME 校验
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mimeType, $this->config['allowed_types'])) {
            throw new Exception('不支持的文件类型（仅支持 PDF / TXT）');
        }

        $taskId     = date('YmdHis') . '_' . substr(md5(uniqid()), 0, 8);
        $fileName   = $taskId . '_' . basename($file['name']);
        $targetPath = "{$this->uploadDir}/{$fileName}";
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('文件保存失败');
        }

        return [
            'task_id'   => $taskId,
            'file_name' => $fileName,
            'type'      => $mimeType === 'application/pdf' ? 'pdf' : 'text',
            'path'      => $targetPath
        ];
    }
}
