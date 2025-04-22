<?php
/**
 * 仅负责保存用户上传的文件，并（可选）生成 PDF 摘要。
 * 返回 JSON: {status, task_id, file_name, type, summary?}
 */
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'chat_logs.log');

require_once __DIR__ . '/utils/FileHelper.php';
require_once __DIR__ . '/utils/LightPDFBridge.php';

try {
    if (!isset($_FILES['file'])) {
        throw new Exception('没有检测到文件');
    }

    // 1. 保存文件
    $helper   = new FileHelper();
    $fileInfo = $helper->save($_FILES['file']);      // [task_id, file_name, type, path]

    // 2. 若是 PDF，立刻生成摘要（可注释掉此段改为异步）
    $summary = null;
    if ($fileInfo['type'] === 'pdf') {
        $bridge  = new LightPDFBridge();
        $summary = $bridge->getSummary($fileInfo['path']);
    }

    echo json_encode([
        'status'    => 'success',
        'task_id'   => $fileInfo['task_id'],
        'file_name' => $fileInfo['file_name'],
        'type'      => $fileInfo['type'],
        'summary'   => $summary
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
