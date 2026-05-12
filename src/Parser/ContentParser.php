<?php

declare(strict_types=1);

namespace Demeve\Template\Parser;

/**
 * Statically parses a component .php file into section cache files.
 *
 * Runs once per component per file change. No eval — reads the source as text,
 * splits on <!--section::name--> markers, and writes each block to its own
 * cache file. Returns the component's metadata (extends, deps) so load() can
 * wire up the inheritance/dependency graph without executing any PHP.
 */
class ContentParser
{
    /**
     * Parse $source, write section cache files to $cacheDir, and return metadata.
     *
     * @return array{extends: string|null, deps: string[]}
     */
    public function parse(string $source, string $component, string $cacheDir): array
    {
        // Strip dev-only triple-dash comments
        $source = (string) preg_replace('/<!---.*?--->/s', '', $source);

        // <dmv-template> → <script type="text/template">  (kept for inline template support)
        $source = str_replace(
            ['</dmv-template', '<dmv-template', '__self'],
            ['</script', "<script type='text/template' id='template-{$component}'", $component],
            $source
        );

        // Transform <!--fn::args--> or <!--=fn::args--> into PHP calls
        $source = (string) preg_replace_callback(
            '/<!--(=?)(\w+)::([^>]+)-->/',
            function ($m) {
                $echo    = $m[1] === '=';
                $fn      = $m[2];
                $args    = explode('|', $m[3]);
                
                if (in_array($fn, ['section', 'extends', 'load'])) {
                    return $m[0]; // skip core markers
                }
                
                $encoded = implode(', ', array_map(fn($a) => var_export(trim($a), true), $args));
                $prefix  = $echo ? 'echo ' : '';
                return "<?php {$prefix}\$this->{$fn}({$encoded}); ?>";
            },
            $source
        );

        // Extract <!--extends::Name--> — must appear before any section marker
        $extends = null;
        if (preg_match('/<!--extends::([\w.]+)-->/', $source, $m)) {
            $extends = $m[1];
        }

        // Extract all <!--load::Name--> directives
        $deps = [];
        preg_match_all('/<!--load::([\w.]+)-->/', $source, $matches);
        foreach ($matches[1] as $dep) {
            $deps[] = $dep;
        }

        // Split on <!--section::name--> markers and write each block to its cache file
        $parts = (array) preg_split('/<!--section::(\w+)-->/', $source, -1, PREG_SPLIT_DELIM_CAPTURE);
        for ($i = 1; isset($parts[$i]); $i += 2) {
            $name    = (string) $parts[$i];
            $content = trim((string) ($parts[$i + 1] ?? ''));
            file_put_contents($cacheDir . '/' . $component . '.' . $name . '.cache.php', $content);
        }

        return ['extends' => $extends, 'deps' => $deps];
    }
}
