<?php

declare(strict_types=1);

namespace Demeve\Template\Modifier;

use Demeve\Template\FileModifierInterface;
use Demeve\Template\Template;

class JsFileModifier extends JsModifier implements FileModifierInterface
{
    /**
     * @var array{output_dir: string, public_url_prefix: string, read_file: bool}
     */
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'output_dir' => '',
            'public_url_prefix' => '/',
            'read_file' => false,
        ], $config);
    }

    public function processFiles(array $files, Template $template): string
    {
        if (empty($files)) {
            return '';
        }

        // Hash of sorted filenames (the keys are ComponentNames)
        $keys = array_keys($files);
        sort($keys);
        $hash = md5(implode('|', $keys));
        
        $filename = $hash . '.js';
        $outputDir = rtrim($this->config['output_dir'], '/\\');
        if ($outputDir === '') {
            throw new \RuntimeException('JsFileModifier: output_dir is not configured.');
        }
        $targetFile = $outputDir . '/' . $filename;

        if (!$this->isFresh($targetFile, array_values($files))) {
            $parts = [];
            foreach ($files as $component => $f) {
                $content = $template->fetchFile($f);
                // Strip <script> wrapper tags
                $content = (string) preg_replace('/<\/?script[^>]*>/i', '', $content);
                // Minify and add comment
                $parts[] = "/* {$component} */\n" . $this->minify($content);
            }
            $js = implode("\n", $parts);

            if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $outputDir));
            }

            file_put_contents($targetFile, $js);
        }

        if ($this->config['read_file']) {
            return (string) file_get_contents($targetFile);
        }

        $url = rtrim($this->config['public_url_prefix'], '/') . '/' . $filename;
        $url .= '?v=' . filemtime($targetFile);
        return $url;
    }

    /**
     * @param string[] $cacheFiles
     */
    private function isFresh(string $targetFile, array $cacheFiles): bool
    {
        if (!file_exists($targetFile)) {
            return false;
        }

        $targetMtime = filemtime($targetFile);
        foreach ($cacheFiles as $file) {
            if (file_exists($file) && filemtime($file) > $targetMtime) {
                return false;
            }
        }

        return true;
    }
}
