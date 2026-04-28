<?php

declare(strict_types=1);

namespace Demeve\Template\Modifier;

use Demeve\Template\ModifierInterface;

/**
 * Minifies each CSS section, then prepends a block comment with the component name.
 * Minification runs first so it strips existing comments without removing the
 * component identifier that is added afterwards.
 *
 * Output format:
 *   /* ComponentName *\/
 *   .minified{css:here}
 */
class CssModifier implements ModifierInterface
{
    public function process(array $sections): string
    {
        $parts = [];
        foreach ($sections as $component => $content) {
            $parts[] = "/* {$component} */\n" . $this->minify($content);
        }
        return implode("\n", $parts);
    }

    private function minify(string $css): string
    {
        $css = (string) preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        $css = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $css);
        $css = (string) preg_replace('/ {2,}/', ' ', $css);
        $css = (string) preg_replace('/\s*([\{\}\:\;\,])\s*/', '$1', $css);
        $css = str_replace(';}', '}', $css);
        return trim($css);
    }
}
