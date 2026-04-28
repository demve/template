<?php

declare(strict_types=1);

namespace Demeve\Template\Modifier;

use Demeve\Template\ModifierInterface;

/**
 * Prepends an HTML comment with the component name before each section item.
 *
 * Output format:
 *   <!-- ComponentName -->
 *   <original content>
 */
class HtmlModifier implements ModifierInterface
{
    public function process(array $sections): string
    {
        $parts = [];
        foreach ($sections as $component => $content) {
            $parts[] = "<!-- {$component} -->\n{$content}";
        }
        return implode("\n", $parts);
    }
}
