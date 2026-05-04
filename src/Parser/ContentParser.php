<?php

declare(strict_types=1);

namespace Demeve\Template\Parser;

/**
 * Transforms a raw component HTML file into executable PHP for the load cache.
 *
 * This runs once per component per file change. The result is an intermediate
 * PHP file that, when included, calls $builder->section() / sectionStop() to
 * register the component's sections into the Template instance.
 */
class ContentParser implements SectionParserInterface
{
    public function compile(string $content): string
    {
        return $content;
    }

    public function parse(string $content, string $component): string
    {
        $content = str_replace(
            ['</dmv-template', '<dmv-template', '__self'],
            ['</script', "<script type='text/template' id='template-{$component}'", $component],
            $content
        );

        // Remove triple-dash comments (dev notes that should never reach the cache)
        $content = (string) preg_replace('/<!---.*?--->/s', '', $content);

        // <!--fn::arg1|arg2--> and /*fn::arg1|arg2*/ compile to: $builder->fn("arg1","arg2")
        $content = (string) preg_replace_callback(
            '/(?:<!--|\/\*)(\w+?)::(.*?)(?:-->|\*\/)/s',
            static function (array $m): string {
                $fn   = trim($m[1]);
                $args = array_map(
                    static fn(string $a): string => '"' . addslashes(trim($a)) . '"',
                    explode('|', trim($m[2]))
                );
                return '<?php $builder->' . $fn . '(' . implode(', ', $args) . '); ?>';
            },
            $content
        );

        return $content;
    }
}
