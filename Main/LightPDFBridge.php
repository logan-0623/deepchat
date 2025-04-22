<?php
/**
 * PHP ↔︎ Python 桥接：调用 runtxt.py 生成 PDF 摘要
 * 保证 python3 在 PATH；如需虚拟环境，请写绝对路径
 */
class LightPDFBridge
{
    private string $pythonCmd;

    public function __construct(string $pythonCmd = 'python3')
    {
        $this->pythonCmd = $pythonCmd;
    }

    /**
     * @param string $pdfPath 上传后文件的绝对路径
     * @return string 摘要文本或错误提示
     */
    public function getSummary(string $pdfPath): string
    {
        $script = __DIR__ . '/../../python/runtxt.py';
        $cmd    = escapeshellcmd("{$this->pythonCmd} {$script} \"{$pdfPath}\"");
        $output = shell_exec($cmd . ' 2>&1');

        // runtxt.py 最后会打印 "# 文档摘要\n<content>"
        if (preg_match('/# 文档摘要\s*(.+)$/s', $output, $m)) {
            return trim($m[1]);
        }
        return '【摘要生成失败】' . PHP_EOL . $output;
    }
}
