<?php

declare(strict_types=1);

namespace Demeve\Template;

use Demeve\Template\FileModifierInterface;
use Demeve\Template\Modifier\CssModifier;
use Demeve\Template\Modifier\HtmlModifier;
use Demeve\Template\Modifier\JsModifier;
use Demeve\Template\Parser\ContentParser;
use Demeve\Template\Parser\OutputParser;
use Demeve\Template\Parser\SectionParserInterface;

class Template
{
    private string $componentsDir;
    private string $cacheDir;
    private ?string $publicDir;
    private string $publicUrl;

    /** @var array<string, mixed> */
    private array $params = [];

    /** @var array<string, array<string, string>> sections['sectionKey']['ComponentName'] = '/path/to/cache.php' */
    private array $sections = ['errors' => []];

    /** @var array<string, SectionParserInterface> */
    private array $sectionParsers = [];

    private ?string $currentSection = null;

    /** @var array<string, true> */
    private array $loadedComponents = [];

    /** @var array<string, string> extendsMap['Child'] = 'Layout' */
    private array $extendsMap = [];

    private string $currentComponent     = '__root__';
    private ?string $currentComponentFile = null;

    /** @var array<string, ModifierInterface|FileModifierInterface> */
    private array $modifiers = [];

    private int $renderDepth = 0;

    /** @var array<string, array{type: string, section: string, modifier: string|null}> */
    private array $sectionQueue = [];

    private ContentParser $contentParser;
    private OutputParser $outputParser;

    public function __construct(
        string $componentsDir,
        string $cacheDir,
        ?string $publicDir = null,
        string $publicUrl = '/',
        ?ContentParser $contentParser = null,
        ?OutputParser $outputParser = null
    ) {
        $this->componentsDir  = rtrim($componentsDir, '/\\');
        $this->cacheDir       = rtrim($cacheDir, '/\\');
        $this->publicDir      = $publicDir !== null ? rtrim($publicDir, '/\\') : null;
        $this->publicUrl      = rtrim($publicUrl, '/');
        $this->contentParser  = $contentParser ?? new ContentParser();
        $this->outputParser   = $outputParser  ?? new OutputParser();
        $this->sectionParsers = ['output' => $this->outputParser];
        $this->modifiers      = [
            'html' => new HtmlModifier(),
            'css'  => new CssModifier(),
            'js'   => new JsModifier(),
        ];
        $this->ensureCacheDir();
    }

    // -------------------------------------------------------------------------
    // Settings (fluent)
    // -------------------------------------------------------------------------

    public function setComponentsDir(string $dir): static
    {
        $this->componentsDir = rtrim($dir, '/\\');
        return $this;
    }

    public function setCacheDir(string $dir): static
    {
        $this->cacheDir = rtrim($dir, '/\\');
        $this->ensureCacheDir();
        return $this;
    }

    public function setPublicDir(string $dir): static
    {
        $this->publicDir = rtrim($dir, '/\\');
        return $this;
    }

    public function setPublicUrl(string $url): static
    {
        $this->publicUrl = rtrim($url, '/');
        return $this;
    }

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
    // Section management — called from within component files via $builder
    // -------------------------------------------------------------------------

    /**
     * Open a new section capture. If a section is already open it is closed first.
     * Sections other than "output" get an HTML comment marker for IDE navigation.
     */
    public function section(string $name): void
    {
        if ($this->currentSection !== null) {
            $this->sectionStop();
        }
        $this->currentSection = $name;
        ob_start();
    }

    public function sectionStop(): void
    {
        if ($this->currentSection === null) {
            return;
        }
        $content = trim((string) ob_get_clean());
        $section = $this->currentSection;
        $this->currentSection = null;

        $parser    = $this->sectionParsers[$section] ?? $this->contentParser;
        $cacheFile = $this->cacheDir . "/{$this->currentComponent}.{$section}.cache.php";
        file_put_contents($cacheFile, $parser->compile($content));
        $this->sections[$section][$this->currentComponent] = $cacheFile;
    }

    /** @return array<string, string> */
    public function sectionGet(string $key): array
    {
        return $this->sections[$key] ?? [];
    }

    // -------------------------------------------------------------------------
    // Core API
    // -------------------------------------------------------------------------

    /**
     * Load a component: resolve its path, rebuild the load cache if stale, then
     * include the cache so the component's section() calls register its sections.
     * Never outputs anything to the buffer.
     */
    public function load(string $component, array $data = []): void
    {
        if (isset($this->loadedComponents[$component])) {
            return;
        }
        if ($this->currentSection !== null) {
            $section = $this->currentSection;
            ob_end_clean();
            $this->currentSection = null;
            throw new \LogicException(
                "load('{$component}') called while section '{$section}' is open in '{$this->currentComponent}'. "
                . "All load() calls must appear before any section() in the same file."
            );
        }

        $fullPath = $this->resolveComponentPath($component);
        if ($fullPath === null) {
            $this->sections['errors'][] = "'{$this->currentComponent}' tried to load '{$component}' which does not exist";
            return;
        }

        // Push context so nested loads restore correctly
        $parentComponent = $this->currentComponent;
        $parentFile      = $this->currentComponentFile;

        $this->currentComponent     = $component;
        $this->currentComponentFile = $this->componentToRelativePath($component);
        $this->loadedComponents[$component] = true;

        $loadFile = $this->cacheDir . "/{$component}.load.cache.php";
        if (!$this->isCacheValid($loadFile, $fullPath)) {
            $this->clearSectionCacheFiles($component);
            $depsBefore = array_keys($this->loadedComponents);
            $execPhp = $this->contentParser->parse((string) file_get_contents($fullPath), $component);
            $this->evalComponent($execPhp, $data);
            $this->sectionStop();
            $outputCachePath = $this->sections['output'][$component] ?? null;
            if ($outputCachePath !== null && file_exists($outputCachePath)) {
                foreach ($this->extractRenderDeps((string) file_get_contents($outputCachePath)) as $dep) {
                    $this->load($dep, $data);
                }
            }
            if (isset($this->extendsMap[$component])) {
                $this->load($this->extendsMap[$component], $data);
            }
            $deps = array_values(array_diff(array_keys($this->loadedComponents), $depsBefore));
            $this->writeLoadCache($loadFile, $deps, $this->extendsMap[$component] ?? null);
        } else {
            $cacheData = include $loadFile;
            if (isset($cacheData['extends'])) {
                $this->extendsMap[$component] = $cacheData['extends'];
            }
            foreach ($cacheData['deps'] as $dep) {
                $this->load($dep, $data);
            }
            $this->loadSectionPathsFromCache($component);
        }

        // Pop context
        $this->currentComponent     = $parentComponent;
        $this->currentComponentFile = $parentFile;
    }

    /**
     * Render a previously loaded component. Auto-loads if needed.
     *
     * At depth 0 (top-level call): wraps execution in an output buffer so that
     * any renderSectionBlock/renderSectionFiles placeholders emitted by nested
     * templates are replaced with fully-accumulated section content once all
     * dynamic loads have completed.
     *
     * Pass $return = true to capture the final output instead of echoing it.
     */
    public function render(string $component, array $data = [], bool $return = false): ?string
    {
        if (!isset($this->loadedComponents[$component])) {
            $this->load($component, $data);
            if (!isset($this->loadedComponents[$component])) {
                return null;
            }
        }

        // Walk up the inheritance chain to find the root layout to render
        $renderTarget = $component;
        while (isset($this->extendsMap[$renderTarget])) {
            $renderTarget = $this->extendsMap[$renderTarget];
        }

        $outputCache = $this->sections['output'][$renderTarget] ?? null;
        if ($outputCache === null || !file_exists($outputCache)) {
            return null;
        }

        $isTopLevel = ($this->renderDepth === 0);

        if ($isTopLevel) {
            ob_start();
        }

        $this->renderDepth++;
        $this->executeFile($outputCache, $data);
        $this->renderDepth--;

        if ($isTopLevel) {
            $html = $this->inject((string) ob_get_clean());
            $this->sectionQueue = [];
            if ($return) {
                return $html;
            }
            echo $html;
            return null;
        }

        return null;
    }

    /**
     * Replace all deferred section-block placeholders in $html with their
     * fully-accumulated content. Safe to call manually when render() is not
     * used as the outer wrapper (e.g., tests that build $html themselves).
     */
    public function inject(string $html): string
    {
        foreach ($this->sectionQueue as $key => $entry) {
            $placeholder = "<!-- __DMV:{$key}__ -->";
            ob_start();
            if ($entry['type'] === 'files') {
                $this->renderSectionFilesInline($entry['section'], $entry['modifier']);
            } else {
                $this->renderSectionBlockInline($entry['section'], $entry['modifier']);
            }
            $html = str_replace($placeholder, (string) ob_get_clean(), $html);
        }
        return $html;
    }

    /**
     * Render a simple named slot — echoes the section's combined content, or $default
     * when nothing was registered. Intended for single-value layout slots (title, content…).
     */
    public function renderSection(string $section, string $default = ''): void
    {
        $items = $this->sectionGet($section);
        if ($items === []) {
            echo $default;
            return;
        }
        $parts = [];
        foreach ($items as $cachePath) {
            $parts[] = (string) file_get_contents($cachePath);
        }
        echo implode("\n", $parts);
    }

    /**
     * Render a multi-item block section (CSS, JS, HTML templates, etc.).
     * Inside a render() call (depth > 0) emits a deferred placeholder so that
     * sections accumulated by dynamic child renders are included at inject() time.
     */
    public function renderSectionBlock(string $section, ?string $modifierKey = null): void
    {
        if ($section === 'console_errors') {
            $this->consoleErrors();
            return;
        }
        if ($this->renderDepth > 0) {
            $key = "block:{$section}:" . ($modifierKey ?? '');
            $this->sectionQueue[$key] = ['type' => 'block', 'section' => $section, 'modifier' => $modifierKey];
            echo "<!-- __DMV:{$key}__ -->";
            return;
        }
        $this->renderSectionBlockInline($section, $modifierKey);
    }

    /**
     * Like renderSectionBlock but passes file paths to the modifier instead of
     * reading file contents first. Defers to a placeholder inside render() calls.
     */
    public function renderSectionFiles(string $section, ?string $modifierKey = null): void
    {
        if ($this->renderDepth > 0) {
            $key = "files:{$section}:" . ($modifierKey ?? '');
            $this->sectionQueue[$key] = ['type' => 'files', 'section' => $section, 'modifier' => $modifierKey];
            echo "<!-- __DMV:{$key}__ -->";
            return;
        }
        $this->renderSectionFilesInline($section, $modifierKey);
    }

    /**
     * Register that $currentComponent extends $layout.
     * Called from load-cache files via <!--extends::Layout-->.
     * Must be called before any section() opens.
     */
    public function extends(string $layout): void
    {
        if ($this->currentSection !== null) {
            $this->sections['errors'][] = "'{$this->currentComponent}' called extends() inside an open section '{$this->currentSection}'";
            return;
        }
        $this->extendsMap[$this->currentComponent] = $layout;
    }

    // -------------------------------------------------------------------------
    // Assets
    // -------------------------------------------------------------------------

    /**
     * Copy an asset to the public directory (if stale) and return its public URL.
     */
    public function asset(string $source, ?string $destination = null): string
    {
        $this->copy($source, $destination);
        return $this->publicUrl . '/' . ltrim($destination ?? $source, '/');
    }

    /**
     * Copy $source (relative to componentsDir) to $destination (relative to publicDir).
     * Skips the copy when the destination is newer than the source.
     */
    public function copy(string $source, ?string $destination = null): bool
    {
        if ($this->publicDir === null) {
            $this->sections['errors'][] = "'{$this->currentComponent}' tried to copy '{$source}' without a public dir configured";
            return false;
        }
        $sourceFile = $this->componentsDir . '/' . ltrim($source, '/');
        if (!file_exists($sourceFile)) {
            $this->sections['errors'][] = "'{$this->currentComponent}' tried to copy '{$source}' which does not exist";
            return false;
        }
        $dest            = $destination ?? $source;
        $destinationFile = $this->publicDir . '/' . ltrim($dest, '/');

        if (file_exists($destinationFile) && filemtime($destinationFile) >= filemtime($sourceFile)) {
            return true;
        }

        $destinationDir = dirname($destinationFile);
        if (!is_dir($destinationDir) && !mkdir($destinationDir, 0777, true) && !is_dir($destinationDir)) {
            $this->sections['errors'][] = "'{$this->currentComponent}' could not create directory for '{$dest}'";
            return false;
        }

        return copy($sourceFile, $destinationFile);
    }

    // -------------------------------------------------------------------------
    // Template helpers — invoked from component files via comment syntax
    // e.g. <!--print::myKey--> or <!--switch::myKey|Yes|No-->
    // -------------------------------------------------------------------------

    public function print(string $key, mixed $default = null): void
    {
        echo $this->get($key, $default);
    }

    public function switch(string $key, string $true, string $false): void
    {
        echo $this->get($key) ? $true : $false;
    }

    // -------------------------------------------------------------------------
    // Errors
    // -------------------------------------------------------------------------

    public function consoleErrors(): void
    {
        if ($this->sections['errors'] === []) {
            return;
        }
        $msg = str_replace('"', "'", implode('@@@', $this->sections['errors']));
        echo "<script>console.error(\"{$msg}\".split('@@@'));</script>";
    }

    /** @return array<string> */
    public function getErrors(): array
    {
        return $this->sections['errors'];
    }

    // -------------------------------------------------------------------------
    // Private inline renderers (used by public methods and inject())
    // -------------------------------------------------------------------------

    private function renderSectionBlockInline(string $section, ?string $modifierKey): void
    {
        $paths = $this->sectionGet($section);
        $sections = [];
        foreach ($paths as $component => $cachePath) {
            $sections[$component] = (string) file_get_contents($cachePath);
        }
        if ($modifierKey !== null && isset($this->modifiers[$modifierKey])) {
            $modifier = $this->modifiers[$modifierKey];
            if ($modifier instanceof ModifierInterface) {
                echo $modifier->process($sections);
            }
        } else {
            echo implode("\n", $sections);
        }
    }

    private function renderSectionFilesInline(string $section, ?string $modifierKey): void
    {
        $paths = $this->sectionGet($section);

        if ($modifierKey !== null && isset($this->modifiers[$modifierKey])) {
            $modifier = $this->modifiers[$modifierKey];
            if ($modifier instanceof FileModifierInterface) {
                echo $modifier->processFiles($paths);
                return;
            }
            $sections = [];
            foreach ($paths as $component => $path) {
                $sections[$component] = (string) file_get_contents($path);
            }
            echo $modifier->process($sections);
            return;
        }

        $parts = [];
        foreach ($paths as $path) {
            $parts[] = (string) file_get_contents($path);
        }
        echo implode("\n", $parts);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Convert PascalCase component name (with optional dots) to a relative file path.
     * e.g. "PageHeader" → "/page/header", "Ui.Button" → "/ui/button"
     */
    private function componentToRelativePath(string $component): string
    {
        $path = str_replace('.', '', $component);
        $path = (string) preg_replace('/[A-Z]/', '/$0', $path);
        return '/' . ltrim(mb_strtolower($path), '/');
    }

    private function resolveComponentPath(string $component): ?string
    {
        $rel     = $this->componentToRelativePath($component);
        $dirPath = $this->componentsDir . $rel . '/component.html';
        $filePath = $this->componentsDir . $rel . '.html';

        if (is_dir($this->componentsDir . $rel) && file_exists($dirPath)) {
            return $dirPath;
        }
        if (file_exists($filePath)) {
            return $filePath;
        }
        return null;
    }

    private function isCacheValid(string $cacheFile, string $sourceFile): bool
    {
        return file_exists($cacheFile) && filemtime($cacheFile) >= filemtime($sourceFile);
    }

    private function evalComponent(string $phpCode, array $data): void
    {
        $builder = $this;
        if ($data !== []) {
            extract($data, EXTR_SKIP);
        }
        eval('?>' . $phpCode);
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
            $this->sections[$inner][$component] = $file;
        }
    }

    /** @return array<string> */
    private function extractRenderDeps(string $cacheContent): array
    {
        preg_match_all('/\$builder->render\(\'([^\']+)\'/', $cacheContent, $matches);
        return array_unique($matches[1]);
    }

    private function ensureCacheDir(): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }

    /**
     * Include a cache file in an isolated scope where:
     *  - $builder = $this (so component files can call $builder->section(), etc.)
     *  - any $data keys are available as local variables via extract()
     *
     * Double-underscore prefixes guard against extract() collisions with template vars.
     */
    private function executeFile(string $__file, array $__data): void
    {
        if ($__data !== []) {
            extract($__data, EXTR_SKIP);
        }
        $builder = $this;
        include $__file;
    }
}
