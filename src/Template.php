<?php

declare(strict_types=1);

namespace Demeve\Template;

use Demeve\Template\Modifier\CssModifier;
use Demeve\Template\Modifier\HtmlModifier;
use Demeve\Template\Modifier\JsModifier;
use Demeve\Template\Parser\ContentParser;

class Template
{
    private string $componentsDir;
    private string $cacheDir;
    private ?string $publicDir;
    private string $publicUrl;

    /** @var array<string, mixed> */
    private array $params = [];

    /** @var array<string, array<string, string>> sections[name][ComponentName] = '/path/to/cache.php' */
    private array $sections = [];

    /** @var array<string> */
    private array $errors = [];

    /** @var array<string, true> */
    private array $loadedComponents = [];

    /** @var array<string, string> extendsMap[Child] = Layout */
    private array $extendsMap = [];

    private string $currentComponent = '__root__';

    /** @var array<string, ModifierInterface|FileModifierInterface> */
    private array $modifiers = [];

    /** @var array<string, array{type: string, section: string, modifier: string|null}> */
    private array $sectionQueue = [];

    private bool $isRendering = false;

    private ContentParser $contentParser;

    public function __construct(
        string $path,
        string $cache,
        ?string $public = null,
        string $url = '/',
    ) {
        $this->componentsDir = rtrim($path, '/\\');
        $this->cacheDir      = rtrim($cache, '/\\');
        $this->publicDir     = $public !== null ? rtrim($public, '/\\') : null;
        $this->publicUrl     = rtrim($url, '/');
        $this->contentParser = new ContentParser();
        $this->modifiers     = [
            'html' => new HtmlModifier(),
            'css'  => new CssModifier(),
            'js'   => new JsModifier(),
        ];
        $this->ensureCacheDir();
    }

    // -------------------------------------------------------------------------
    // Configuration (fluent)
    // -------------------------------------------------------------------------

    public function addModifier(string $key, ModifierInterface|FileModifierInterface $modifier): static
    {
        $this->modifiers[$key] = $modifier;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Template variables
    // -------------------------------------------------------------------------

    public function set(string $key, mixed $val = null): void
    {
        $this->params[$key] = $val;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->params) ? $this->params[$key] : $default;
    }

    // -------------------------------------------------------------------------
    // Core API
    // -------------------------------------------------------------------------

    /**
     * Load a component: parse its .php file (if stale), write section caches,
     * and register its section paths. Never produces output.
     */
    public function load(string $component): void
    {
        if (isset($this->loadedComponents[$component])) {
            return;
        }

        $fullPath = $this->resolveComponentPath($component);
        if ($fullPath === null) {
            $this->errors[] = "'{$this->currentComponent}' tried to load '{$component}' which does not exist";
            return;
        }

        $parentComponent        = $this->currentComponent;
        $this->currentComponent = $component;
        $this->loadedComponents[$component] = true;

        $loadFile = $this->cacheDir . "/{$component}.load.cache.php";

        if (!$this->isCacheValid($loadFile, $fullPath)) {
            $this->clearSectionCacheFiles($component);
            $depsBefore = array_keys($this->loadedComponents);

            $meta = $this->contentParser->parse(
                (string) file_get_contents($fullPath),
                $component,
                $this->cacheDir
            );

            if ($meta['extends'] !== null) {
                $this->extendsMap[$component] = $meta['extends'];
                $this->load($meta['extends']);
            }

            foreach ($meta['deps'] as $dep) {
                $this->load($dep);
            }

            $outputCache = $this->cacheDir . "/{$component}.output.cache.php";
            if (file_exists($outputCache)) {
                foreach ($this->extractRenderDeps((string) file_get_contents($outputCache)) as $dep) {
                    $this->load($dep);
                }
            }

            $deps = array_values(array_diff(array_keys($this->loadedComponents), $depsBefore));
            $this->writeLoadCache($loadFile, $deps, $meta['extends']);
        } else {
            $cacheData = include $loadFile;
            if (!empty($cacheData['extends'])) {
                $this->extendsMap[$component] = $cacheData['extends'];
            }
            foreach ($cacheData['deps'] as $dep) {
                $this->load($dep);
            }
        }

        $this->loadSectionPathsFromCache($component);
        $this->currentComponent = $parentComponent;
    }

    /**
     * Render a component to an HTML string. Auto-loads if needed.
     *
     * The outermost render() call wraps execution in an output buffer so that
     * block() placeholders emitted during nested renders are resolved with the
     * fully-accumulated section content once execution completes.
     */
    public function render(string $component, array $data = []): string
    {
        $this->load($component);
        if (!isset($this->loadedComponents[$component])) {
            return '';
        }

        $renderTarget = $component;
        while (isset($this->extendsMap[$renderTarget])) {
            $renderTarget = $this->extendsMap[$renderTarget];
        }

        $outputCache = $this->sections['output'][$renderTarget] ?? null;
        if ($outputCache === null || !file_exists($outputCache)) {
            return '';
        }

        $isOutermost       = !$this->isRendering;
        $this->isRendering = true;

        $oldParams = $this->params;
        $this->params = array_merge($this->params, $data);

        ob_start();
        $this->executeFile($outputCache, $data);
        $html = (string) ob_get_clean();

        $this->params = $oldParams;

        if ($isOutermost) {
            $this->isRendering = false;
            $html = $this->inject($html);
            $this->sectionQueue = [];
        }

        return $html;
    }

    /**
     * Replace all deferred block() placeholders with fully-accumulated content.
     * Safe to call manually (e.g. in tests that build $html outside render()).
     */
    public function inject(string $html): string
    {
        foreach ($this->sectionQueue as $key => $entry) {
            $html = str_replace(
                "<!-- __DMV:{$key}__ -->",
                $this->resolveBlock($entry),
                $html
            );
        }
        return $html;
    }

    /**
     * Emit all accumulated sections of $name as one block, optionally post-processed
     * by a modifier. Returns a placeholder string that inject() will replace once
     * all dynamic child components have finished rendering.
     */
    public function block(string $name, ?string $modifier = null): string
    {
        if ($modifier === null && isset($this->modifiers[$name])) {
            $modifier = $name;
        }
        $key = "block:{$name}:{$modifier}";
        $this->sectionQueue[$key] = ['type' => 'block', 'section' => $name, 'modifier' => $modifier];
        return "<!-- __DMV:{$key}__ -->";
    }

    /**
     * Renders a section block as a list of files for a FileModifierInterface.
     */
    public function files(string $name, ?string $modifier = null): string
    {
        if ($modifier === null && isset($this->modifiers[$name])) {
            $modifier = $name;
        }
        $key = "files:{$name}:{$modifier}";
        $this->sectionQueue[$key] = ['type' => 'files', 'section' => $name, 'modifier' => $modifier];
        return "<!-- __DMV:{$key}__ -->";
    }

    /**
     * Like block() but passes file paths to the modifier instead of reading content.
     * Use with FileModifierInterface for memory-efficient bundlers.
     */
    public function blockFiles(string $name, ?string $modifier = null): string
    {
        return $this->files($name, $modifier);
    }

    /**
     * Return the content of a single named section slot, or $default if nothing
     * was registered. Intended for layout slots (title, content, …).
     */
    public function slot(string $name, string $default = ''): string
    {
        $items = $this->sections[$name] ?? [];
        if ($items === []) {
            return $default;
        }
        //return implode("\n", array_map('file_get_contents', array_values($items)));
        ob_start();
        foreach ($items as $path) {
            $this->executeFile($path, $this->params);
        }
        return (string) ob_get_clean();
    }

    /**
     * HTML-escape a value for safe output. Handles null gracefully.
     */
    public function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Copy an asset to the public directory (if stale) and return its public URL.
     */
    public function asset(string $source, ?string $destination = null): string
    {
        if ($this->publicDir !== null) {
            $srcFile = $this->componentsDir . '/' . ltrim($source, '/');
            $destRel = $destination ?? $source;
            $destFile = $this->publicDir . '/' . ltrim($destRel, '/');

            if (file_exists($srcFile) && (!file_exists($destFile) || filemtime($destFile) < filemtime($srcFile))) {
                $dir = dirname($destFile);
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                copy($srcFile, $destFile);
            }
        }
        return $this->publicUrl . '/' . ltrim($destination ?? $source, '/');
    }

    public function print(string $key, mixed $default = null): void
    {
        echo $this->e($this->get($key, $default));
    }

    /**
     * Executes a cache file and returns its captured output.
     */
    public function fetchFile(string $path): string
    {
        ob_start();
        $this->executeFile($path, $this->params);
        return (string) ob_get_clean();
    }

    /**
     * Output collected errors in the specified format.
     */
    public function logErrores(string $format = 'js'): void
    {
        if ($this->errors === []) {
            return;
        }

        if ($format === 'js') {
            $json = json_encode($this->errors);
            echo "<script>console.error('Template Errors:', {$json});</script>";
        } else {
            foreach ($this->errors as $error) {
                echo "[Error] {$error}\n";
            }
        }
    }

    /** @return array<string> */
    public function errors(): array
    {
        return $this->errors;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function resolveBlock(array $entry): string
    {
        $paths    = $this->sections[$entry['section']] ?? [];
        $modifier = $entry['modifier'] !== null ? ($this->modifiers[$entry['modifier']] ?? null) : null;

        if ($entry['type'] === 'files') {
            if ($modifier instanceof FileModifierInterface) {
                return $modifier->processFiles($paths, $this);
            }
            $contents = [];
            foreach ($paths as $comp => $path) {
                $contents[$comp] = $this->fetchFile($path);
            }
            return $modifier instanceof ModifierInterface
                ? $modifier->process($contents)
                : implode("\n", $contents);
        }

        $contents = [];
        foreach ($paths as $comp => $path) {
            $contents[$comp] = $this->fetchFile($path);
        }
        return $modifier instanceof ModifierInterface
            ? $modifier->process($contents)
            : implode("\n", $contents);
    }

    private function componentToRelativePath(string $component): string
    {
        $path = str_replace('.', '', $component);
        $path = (string) preg_replace('/[A-Z]/', '/$0', $path);
        return '/' . ltrim(mb_strtolower($path), '/');
    }

    private function resolveComponentPath(string $component): ?string
    {
        $rel      = $this->componentToRelativePath($component);
        $dirPathPhp  = $this->componentsDir . $rel . '/component.php';
        $dirPathHtml = $this->componentsDir . $rel . '/component.html';
        $filePathPhp = $this->componentsDir . $rel . '.php';
        $filePathHtml = $this->componentsDir . $rel . '.html';

        if (is_dir($this->componentsDir . $rel)) {
            if (file_exists($dirPathPhp)) return $dirPathPhp;
            if (file_exists($dirPathHtml)) return $dirPathHtml;
        }
        if (file_exists($filePathPhp)) return $filePathPhp;
        if (file_exists($filePathHtml)) return $filePathHtml;
        return null;
    }

    private function isCacheValid(string $cacheFile, string $sourceFile): bool
    {
        return file_exists($cacheFile) && filemtime($cacheFile) >= filemtime($sourceFile);
    }

    private function clearSectionCacheFiles(string $component): void
    {
        foreach (glob($this->cacheDir . '/' . $component . '.*.cache.php') ?: [] as $f) {
            @unlink($f);
        }
    }

    private function writeLoadCache(string $loadFile, array $deps, ?string $extends): void
    {
        $export = var_export(['extends' => $extends, 'deps' => $deps], true);
        file_put_contents($loadFile, "<?php return {$export};");
    }

    private function loadSectionPathsFromCache(string $component): void
    {
        $prefix = $component . '.';
        $suffix = '.cache.php';
        foreach (glob($this->cacheDir . '/' . $component . '.*.cache.php') ?: [] as $file) {
            $inner = substr(basename($file), strlen($prefix), -strlen($suffix));
            if ($inner !== 'load') {
                $this->sections[$inner][$component] = $file;
            }
        }
    }

    /** @return array<string> */
    private function extractRenderDeps(string $cacheContent): array
    {
        preg_match_all('/\$this->render\(\'([^\']+)\'/', $cacheContent, $matches);
        return array_unique($matches[1]);
    }

    /**
     * Include a cache file with $data keys available as local variables.
     * The Template instance is available via $this.
     */
    private function executeFile(string $__file, array $__data): void
    {
        if ($__data !== []) {
            extract($__data, EXTR_SKIP);
        }
        include $__file;
    }

    private function ensureCacheDir(): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }
}
