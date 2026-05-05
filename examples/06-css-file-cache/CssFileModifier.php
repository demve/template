<?php

/**
 * FileModifierInterface implementation that:
 *   1. Checks whether the output CSS file is newer than every section cache file.
 *   2. If fresh: returns a <link> tag immediately — no file reading, no minification.
 *   3. If stale: reads, minifies, writes the CSS file, then returns a cache-busted <link>.
 *
 * Receives file paths directly via processFiles(), skipping file_get_contents
 * entirely on cache hits.
 */

use Demeve\Template\FileModifierInterface;

class CssFileModifier implements FileModifierInterface
{
    public function __construct(
        private string $outputFile,
        private string $publicUrl = 'styles.css',
    ) {}

    /** @param array<string, string> $files ComponentName => absolute cache file path */
    public function processFiles(array $files): string
    {
        if (file_exists($this->outputFile) && $this->outputFileIsFresh(array_values($files))) {
            return $this->linkTag();
        }

        $css = implode("\n", array_map(fn(string $f): string => (string) file_get_contents($f), $files));

        // Strip <style> wrapper tags left by the section capture
        $css = (string) preg_replace('/<\/?style[^>]*>/i', '', $css);
        // Remove /* block comments */
        $css = (string) preg_replace('/\/\*.*?\*\//s', '', $css);
        // Collapse whitespace
        $css = (string) preg_replace('/\s+/', ' ', $css);
        // Remove spaces around structural characters
        $css = (string) preg_replace('/\s*([{}:;,>~+])\s*/', '$1', $css);
        $css = trim($css);

        $dir = dirname($this->outputFile);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create directory: {$dir}");
        }
        file_put_contents($this->outputFile, $css);

        return $this->linkTag();
    }

    /** @param string[] $files */
    private function outputFileIsFresh(array $files): bool
    {
        $outputMtime = (int) filemtime($this->outputFile);
        foreach ($files as $file) {
            if (file_exists($file) && filemtime($file) > $outputMtime) {
                return false;
            }
        }
        return true;
    }

    private function linkTag(): string
    {
        return '<link rel="stylesheet" href="' . $this->publicUrl . '?v=' . filemtime($this->outputFile) . '">';
    }
}
