<?php

declare(strict_types=1);

namespace Demeve\Template;

use Demeve\Template\Modifier\CssModifier;
use Demeve\Template\Modifier\HtmlModifier;
use Demeve\Template\Modifier\JsModifier;
use Demeve\Template\Parser\ContentParser;
use Demeve\Template\Parser\OutputParser;

class Template
{
    private string $componentsDir;
    private string $cacheDir;
    private ?string $publicDir;
    private string $publicUrl;

    /** @var array<string, mixed> */
    private array $params = [];

    /** @var array<string, array<string, string>> sections['sectionKey']['ComponentName'] = content */
    private array $sections = ['errors' => []];

    private ?string $currentSection = null;

    /** @var array<string, true> */
    private array $loadedComponents = [];

    /** @var array<string, string> extendsMap['Child'] = 'Layout' */
    private array $extendsMap = [];

    private string $currentComponent     = '__root__';
    private ?string $currentComponentFile = null;

    /** @var array<string, ModifierInterface> */
    private array $modifiers = [];

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

    public function addModifier(string $key, ModifierInterface $modifier): static
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

        if ($section === 'output') {
            $this->compileAndCacheOutput($this->currentComponent, $content);
        } else {
            $this->sections[$section][$this->currentComponent] = $content;
        }
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

        $loadCache = $this->cacheDir . "/{$component}.load.cache.php";
        if (!$this->isCacheValid($loadCache, $fullPath)) {
            file_put_contents(
                $loadCache,
                $this->contentParser->parse((string) file_get_contents($fullPath), $component)
            );
        }

        $this->executeFile($loadCache, $data);
        $this->sectionStop();

        // After child sections are captured, auto-load its parent layout (if any)
        if (isset($this->extendsMap[$component])) {
            $this->load($this->extendsMap[$component], $data);
        }

        // Pop context
        $this->currentComponent     = $parentComponent;
        $this->currentComponentFile = $parentFile;
    }

    /**
     * Render a previously loaded component into the output buffer.
     * The component MUST be loaded first via load() — render() intentionally does
     * not auto-load so that it is safe to call from inside a section buffer.
     * Pass $return = true to capture the output instead of echoing it.
     */
    public function render(string $component, array $data = [], bool $return = false): ?string
    {
        if (!isset($this->loadedComponents[$component])) {
            $this->sections['errors'][] = "render('{$component}') called but '{$component}' is not loaded";
            return null;
        }

        // Walk up the inheritance chain to find the root layout to render
        $renderTarget = $component;
        while (isset($this->extendsMap[$renderTarget])) {
            $renderTarget = $this->extendsMap[$renderTarget];
        }

        $outputCache = $this->cacheDir . "/{$renderTarget}.output.cache.php";
        if (!file_exists($outputCache)) {
            return null;
        }

        if ($return) {
            ob_start();
            $this->executeFile($outputCache, $data);
            return (string) ob_get_clean();
        }

        $this->executeFile($outputCache, $data);
        return null;
    }

    /**
     * Render a simple named slot — echoes the section's combined content, or $default
     * when nothing was registered. Intended for single-value layout slots (title, content…).
     */
    public function renderSection(string $section, string $default = ''): void
    {
        $items = $this->sectionGet($section);
        echo $items !== [] ? implode("\n", $items) : $default;
    }

    /**
     * Render a multi-item block section (CSS, JS, HTML templates, etc.).
     *
     * $modifierKey refers to a modifier registered via addModifier(). The modifier
     * receives the raw sections array (ComponentName => content) and is responsible
     * for combining and transforming it into the final output string.
     * When no modifier is registered for the key (or key is null), items are joined with "\n".
     */
    public function renderSectionBlock(string $section, ?string $modifierKey = null): void
    {
        if ($section === 'console_errors') {
            $this->consoleErrors();
            return;
        }
        $sections = $this->sectionGet($section);
        if ($modifierKey !== null && isset($this->modifiers[$modifierKey])) {
            echo $this->modifiers[$modifierKey]->process($sections);
        } else {
            echo implode("\n", $sections);
        }
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

    /**
     * Parse the "output" section content and write the output cache file.
     * Skips re-compilation when the output cache is newer than the load cache.
     */
    private function compileAndCacheOutput(string $component, string $content): void
    {
        $loadCache   = $this->cacheDir . "/{$component}.load.cache.php";
        $outputCache = $this->cacheDir . "/{$component}.output.cache.php";

        if ($this->isCacheValid($outputCache, $loadCache)) {
            return;
        }

        file_put_contents($outputCache, $this->outputParser->compile($content));
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
