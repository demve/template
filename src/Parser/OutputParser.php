<?php

declare(strict_types=1);

namespace Demeve\Template\Parser;

/**
 * Compiles the "output" section of a component into a cacheable PHP template.
 *
 * This runs once per component per load-cache change. The result is a PHP file
 * that, when included inside executeFile(), renders HTML to the output buffer.
 * $builder and any $data variables are available in that scope.
 */
class OutputParser
{
    public function compile(string $content): string
    {
        // @php...@endphp — raw PHP blocks
        $content = (string) preg_replace_callback(
            '/@php(.*?)@endphp/s',
            static fn(array $m): string => '<?php ' . trim($m[1]) . ' ?>',
            $content
        );

        // @foreach(expr) { ... } @endforeach
        $content = (string) preg_replace_callback(
            '/@foreach\s*\((.*?)\)\s*\{?(.*?)\}?@endforeach/s',
            static fn(array $m): string => '<?php foreach(' . trim($m[1]) . '): ?>' . trim($m[2]) . '<?php endforeach; ?>',
            $content
        );

        // <%%expr%%> — raw (unescaped) echo
        $content = (string) preg_replace_callback(
            '/<%%(.+?)%%>/s',
            static fn(array $m): string => '<?php echo ' . trim($m[1]) . '; ?>',
            $content
        );

        // <%expr%> — HTML-escaped echo
        $content = (string) preg_replace_callback(
            '/<%(.+?)%>/s',
            static fn(array $m): string => "<?php echo htmlspecialchars(" . trim($m[1]) . " ?? '', ENT_QUOTES, 'UTF-8'); ?>",
            $content
        );

        // @echo(expr) — HTML-escaped echo (alternate syntax)
        $content = (string) preg_replace_callback(
            '/@echo\((.+?)\)/s',
            static fn(array $m): string => "<?php echo htmlspecialchars(" . trim($m[1]) . " ?? '', ENT_QUOTES, 'UTF-8'); ?>",
            $content
        );

        // <!--@render('Component')--> — comment form first so bare form doesn't double-match
        $content = (string) preg_replace_callback(
            '/<!--@render\((.+?)\)-->/s',
            static fn(array $m): string => '<?php $builder->render(' . trim($m[1]) . '); ?>',
            $content
        );

        // @render('Component') — bare form
        $content = (string) preg_replace_callback(
            '/@render\((.+?)\)/s',
            static fn(array $m): string => '<?php $builder->render(' . trim($m[1]) . '); ?>',
            $content
        );

        // <!--@renderSection('key')--> — comment form
        $content = (string) preg_replace_callback(
            '/<!--@renderSection\((.+?)\)-->/s',
            static fn(array $m): string => '<?php $builder->renderSection(' . trim($m[1]) . '); ?>',
            $content
        );

        // @renderSection('key') / @renderSection('key', 'default') — bare form
        $content = (string) preg_replace_callback(
            '/@renderSection\((.+?)\)/s',
            static fn(array $m): string => '<?php $builder->renderSection(' . trim($m[1]) . '); ?>',
            $content
        );

        // <!--@renderSectionBlock('key')--> — comment form
        $content = (string) preg_replace_callback(
            '/<!--@renderSectionBlock\((.+?)\)-->/s',
            static fn(array $m): string => '<?php $builder->renderSectionBlock(' . trim($m[1]) . '); ?>',
            $content
        );

        // @renderSectionBlock('key') — bare form
        $content = (string) preg_replace_callback(
            '/@renderSectionBlock\((.+?)\)/s',
            static fn(array $m): string => '<?php $builder->renderSectionBlock(' . trim($m[1]) . '); ?>',
            $content
        );

        return trim($content) . PHP_EOL;
    }
}
