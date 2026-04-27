<?php

/**
 * Example ModifierInterface implementation that strips CSS comments and
 * collapses whitespace. Not production-grade — illustrates the contract.
 */

use Demeve\Template\ModifierInterface;

class CssMinifier implements ModifierInterface
{
    public function process(string $content): string
    {
        // Remove /* block comments */
        $content = (string) preg_replace('/\/\*.*?\*\//s', '', $content);
        // Collapse runs of whitespace to a single space
        $content = (string) preg_replace('/\s+/', ' ', $content);
        // Remove spaces around structural characters
        $content = (string) preg_replace('/\s*([{}:;,>~+])\s*/', '$1', $content);

        return trim($content);
    }
}
