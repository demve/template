<?php

/**
 * Example ModifierInterface implementation that strips CSS comments and
 * collapses whitespace. Not production-grade — illustrates the contract.
 */

use Demeve\Template\ModifierInterface;

class CssMinifier implements ModifierInterface
{
    public function process(array $sections): string
    {
        $combined = implode("\n", $sections);

        // Remove /* block comments */
        $combined = (string) preg_replace('/\/\*.*?\*\//s', '', $combined);
        // Collapse runs of whitespace to a single space
        $combined = (string) preg_replace('/\s+/', ' ', $combined);
        // Remove spaces around structural characters
        $combined = (string) preg_replace('/\s*([{}:;,>~+])\s*/', '$1', $combined);

        return trim($combined);
    }
}
