# CLAUDE.md

Guidance for Claude Code. Save tokens — use /caveman when possible.

## Commands

```bash
composer install                                        # install PHPUnit
composer test                                           # run full suite
./vendor/bin/phpunit --filter ContentParserTest         # single test class
./vendor/bin/phpunit tests/Parser/ContentParserTest.php # single file
```

## Core goals

**A)** `$t->render($component, $data)` returns a string — the component's `output` section executed with `$data` available as local variables.

**B)** Components can include other components (`<?= $this->render('Widget') ?>`) or declare they are the content slot of a layout (`<!--extends::Layout-->`).

**C)** Components are single `.php` files. Structure uses `<!--comment directives-->` so the section boundaries are visible without custom IDE plugins.

**D)** A component file declares named **sections** (`output`, `style`, `script`, etc.). The `output` section is the rendered PHP template. Other sections are collected and emitted separately (e.g. all `style` blocks merged into one `<head>` spot).

**E)** Loading a component writes one cache file per section (`{Component}.{section}.cache.php`) plus a `{Component}.load.cache.php` recording deps and layout. Cache is OPcache-eligible. Memory-efficient: section content is not loaded until needed.

**F)** Sections accumulate **globally** across every loaded component — `style` and `script` from all widgets on the page are merged by a single `<?= $this->block('style', 'css') ?>` in the layout.

`load()` is a best practice (pre-warms cache, separates concerns) but not required — `render()` auto-loads.

## Key files

| File | Purpose |
|---|---|
| [`src/Template.php`](src/Template.php) | Main class — only public API |
| [`src/Parser/ContentParser.php`](src/Parser/ContentParser.php) | Static text parser — splits `.php` file into section cache files, extracts extends/deps metadata |
| [`src/ModifierInterface.php`](src/ModifierInterface.php) | Post-processor contract for section content (e.g. CSS minifier) |

## Component syntax

```php
<!--extends::Layouts.App-->
<!--load::Widgets.Card-->          <!-- declare dep before any section -->

<!--section::style-->
<style> .hero { padding: 2rem; } </style>

<!--section::output-->
<h1><?= $this->e($title) ?></h1>         <!-- HTML-escaped echo -->
<p><?= $rawHtml ?></p>                 <!-- raw echo -->

<?php $x = $someArray['key']; ?>

<?php if ($x > 0): ?>yes<?php else: ?>no<?php endif; ?>

<?php foreach ($items as $i => $v): ?>
  <li><?= $this->e($v) ?></li>
<?php endforeach; ?>

<?= $this->render('Widgets.Card') ?>
<?= $this->render($dynamicName, $myArray) ?>
<?= $this->slot('content', 'My App') ?>
<?= $this->block('style', 'css') ?>
<?= $this->block('style') ?>
```

Comment directive forms:
- `<!--fn::arg-->` → `$this->fn("arg")`
- `<!--fn::arg1|arg2-->` → `$this->fn("arg1", "arg2")`
- `<!---dev note--->` → stripped entirely, never reaches cache

## Public API

```php
$t = new Template(
    path:   'components/',  // component root
    cache:  '.cache/',      // writable cache dir
    public: 'public/',      // optional, for asset()
    url:    '/',            // optional, for asset() URLs
);

$t->render($component, $data)     // → string, auto-loads
$t->load($component)              // pre-warm cache (optional)
$t->slot($name, $default)         // read single section → string
$t->block($name, $modifier)       // deferred multi-section emit → string (placeholder)
$t->blockFiles($name, $modifier)  // like block() but passes paths to FileModifierInterface
$t->inject($html)                 // resolve block() placeholders in $html → string
$t->e($value)                     // HTML escape → string
$t->set($key, $value)             // set template variable
$t->get($key, $default)           // read template variable
$t->asset($src, $dest)            // copy asset + return URL
$t->errors()                      // → string[]
$t->addModifier($key, $modifier)  // register ModifierInterface|FileModifierInterface
```

Inside templates, the Template instance is available via `$this`.

## Component naming → file path

`PascalCase` / dot-separated → lowercase slash-separated:
- `PageHeader` → `components/page/header.php`
- `Ui.Button` → `components/ui/button.php`
- If path is a directory → `components/ui/button/component.php`
