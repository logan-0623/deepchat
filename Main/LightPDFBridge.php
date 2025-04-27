<?php
/**
 * PHP ↔︎ Python bridge: calls runtxt.py to generate a PDF summary
 * Ensure python3 is in PATH; if you need a virtual environment, use its absolute path
 */
class LightPDFBridge
{
    private string $pythonCmd;

    public function __construct(string $pythonCmd = 'python3')
    {
        $this->pythonCmd = $pythonCmd;
    }

    /**
     * @param string $pdfPath The absolute path to the uploaded PDF file
     * @return string The summary text or an error message
     */
    public function getSummary(string $pdfPath): string
    {
        $script = __DIR__ . '/../../python/runtxt.py';
        $cmd    = escapeshellcmd("{$this->pythonCmd} {$script} \"{$pdfPath}\"");
        $output = shell_exec($cmd . ' 2>&1');

        // runtxt.py will output "# Document Summary\n<content>"
        if (preg_match('/# Document Summary\s*(.+)$/s', $output, $m)) {
            return trim($m[1]);
        }
        return '[Summary generation failed]' . PHP_EOL . $output;
    }
}
