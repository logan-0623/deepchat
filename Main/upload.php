<?php
/**
 * Responsible solely for saving user-uploaded files and (optionally) generating a PDF summary.
 * Returns JSON: {status, task_id, file_name, type, summary?}
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
        throw new Exception('No file detected');
    }

    // 1. Save file
    $helper   = new FileHelper();
    $fileInfo = $helper->save($_FILES['file']);      // [task_id, file_name, type, path]

    // 2. If it's a PDF, generate the summary immediately (comment out this section to make it asynchronous)
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