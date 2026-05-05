<?php

declare(strict_types=1);

namespace Demeve\Template\Parser;

class OutputParser implements SectionParserInterface
{
    public function compile(string $content): string
    {
        $quotesRegex = '(["\'])(.*?)\1';

        $escape = static fn(string $v): string => str_replace("'", "\\'", trim($v));

        $content = preg_replace_callback(
            '/<dmv-php\s*>/i',
            static fn(): string => '<?php ',
            $content
        );
        $content = preg_replace_callback(
            '/<\/dmv-php\s*>/i',
            static fn(): string => ' ?>',
            $content
        );

        $content = (string) preg_replace_callback(
            '/<dmv-elseif\s+test=' . $quotesRegex . '\s*\/?>/i',
            static fn(array $m): string => '<?php elseif(' . trim($m[2]) . '): ?>',
            $content
        );

        $content = (string) preg_replace_callback(
            '/<dmv-if\s+test=' . $quotesRegex . '\s*>/i',
            static fn(array $m): string => '<?php if(' . trim($m[2]) . '): ?>',
            $content
        );
        $content = preg_replace_callback(
            '/<dmv-else\s*\/?>/i',
            static fn(): string => '<?php else: ?>',
            $content
        );
        $content = preg_replace_callback(
            '/<\/dmv-if\s*>/i',
            static fn(): string => '<?php endif; ?>',
            $content
        );

        $content = (string) preg_replace_callback(
            '/<dmv-foreach\s+on=' . $quotesRegex . '\s*>/i',
            static fn(array $m): string => '<?php foreach(' . trim($m[2]) . '): ?>',
            $content
        );
        $content = preg_replace_callback(
            '/<\/dmv-foreach\s*>/i',
            static fn(): string => '<?php endforeach; ?>',
            $content
        );

        $content = (string) preg_replace_callback(
            '/<dmv-for\s+expr=' . $quotesRegex . '\s*>/i',
            static fn(array $m): string => '<?php for(' . trim($m[2]) . '): ?>',
            $content
        );
        $content = preg_replace_callback(
            '/<\/dmv-for\s*>/i',
            static fn(): string => '<?php endfor; ?>',
            $content
        );

        $content = (string) preg_replace_callback(
            '/<dmv-while\s+test=' . $quotesRegex . '\s*>/i',
            static fn(array $m): string => '<?php while(' . trim($m[2]) . '): ?>',
            $content
        );
        $content = preg_replace_callback(
            '/<\/dmv-while\s*>/i',
            static fn(): string => '<?php endwhile; ?>',
            $content
        );

        $content = (string) preg_replace_callback(
            '/<%%(.+?)%%>/s',
            static fn(array $m): string => '<?php echo ' . trim($m[1]) . '; ?>',
            $content
        );

        $content = (string) preg_replace_callback(
            '/<%(?!%)(.+?)%>/s',
            static fn(array $m): string => "<?php echo htmlspecialchars(" . trim($m[1]) . " ?? '', ENT_QUOTES, 'UTF-8'); ?>",
            $content
        );

        $content = (string) preg_replace_callback(
            '/<dmv-render\s+((?:[^>\'"]|"[^"]*"|\'[^\']*\')*)\s*\/?>/i',
            static function (array $m) use ($quotesRegex): string {
                $attrs = $m[1];

                preg_match('/component=' . $quotesRegex . '/i', $attrs, $c);
                preg_match('/data=' . $quotesRegex . '/i', $attrs, $d);

                $comp = trim($c[2] ?? '');
                $compArg = str_starts_with($comp, '$')
                    ? $comp
                    : "'" . str_replace("'", "\\'", $comp) . "'";

                $args = $compArg;
                if (isset($d[2])) {
                    $args .= ', ' . trim($d[2]);
                }

                return "<?php \$builder->render($args); ?>";
            },
            $content
        );

        $content = (string) preg_replace_callback(
            '/<dmv-renderSection\s+([^>]+?)\s*\/?>/i',
            static function (array $m) use ($quotesRegex, $escape): string {
                $attrs = $m[1];

                preg_match('/name=' . $quotesRegex . '/i', $attrs, $n);
                preg_match('/default=' . $quotesRegex . '/i', $attrs, $d);

                if (!isset($n[2])) {
                    throw new \RuntimeException('name requerido');
                }

                $args = "'" . $escape($n[2]) . "'";

                if (isset($d[2])) {
                    $args .= ", '" . $escape($d[2]) . "'";
                }

                return "<?php \$builder->renderSection($args); ?>";
            },
            $content
        );

        $content = (string) preg_replace_callback(
            '/<dmv-renderSectionBlock\s+([^>]+?)\s*\/?>/i',
            static function (array $m) use ($quotesRegex, $escape): string {
                $attrs = $m[1];

                preg_match('/name=' . $quotesRegex . '/i', $attrs, $n);
                preg_match('/modifier=' . $quotesRegex . '/i', $attrs, $mod);

                if (!isset($n[2])) {
                    throw new \RuntimeException('name requerido');
                }

                $args = "'" . $escape($n[2]) . "'";

                if (isset($mod[2])) {
                    $args .= ", '" . $escape($mod[2]) . "'";
                }

                return "<?php \$builder->renderSectionBlock($args); ?>";
            },
            $content
        );

        $content = (string) preg_replace_callback(
            '/<dmv-renderSectionFiles\s+([^>]+?)\s*\/?>/i',
            static function (array $m) use ($quotesRegex, $escape): string {
                $attrs = $m[1];

                preg_match('/name=' . $quotesRegex . '/i', $attrs, $n);
                preg_match('/modifier=' . $quotesRegex . '/i', $attrs, $mod);

                if (!isset($n[2])) {
                    throw new \RuntimeException('name requerido');
                }

                $args = "'" . $escape($n[2]) . "'";

                if (isset($mod[2])) {
                    $args .= ", '" . $escape($mod[2]) . "'";
                }

                return "<?php \$builder->renderSectionFiles($args); ?>";
            },
            $content
        );

        return trim($content) . PHP_EOL;
    }
}