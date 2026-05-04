# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
composer install                                        # install PHPUnit
composer test                                           # run full suite
./vendor/bin/phpunit --filter ContentParserTest         # single test class
./vendor/bin/phpunit tests/Parser/OutputParserTest.php  # single file
```

## Architecture

`demeve/template` is a PHP 8.1+ library (namespace `Demeve\Template`) that gives a Vue SFC-like authoring experience in plain `.html` files.

### Core concept

A component file is a plain HTML file that calls `$builder->section('name')` (via comment directives) to declare named sections. One section named **`output`** is the renderable template. All other sections (e.g. `style`, `script`) accumulate across components and are rendered together at the end of the page.

### Cache pipeline

Loading a component writes per-section cache files:

| Cache file | When rebuilt | Contains |
|---|---|---|
| `{component}.lock` | Source file newer | Empty — mtime is the validity marker |
| `{component}.{section}.cache.php` | Source file newer | Compiled content for each section |

On a cache miss, `ContentParser` output is `eval()`'d to capture sections; each `sectionStop()` picks a parser by section name (`'output'` → `OutputParser`, all others → `ContentParser` identity) and writes the section cache file. On a cache hit, existing `*.cache.php` files are globbed to restore `$sections` without re-executing.

`render()` `include`s `{component}.output.cache.php` (which remains OPcache-eligible). This is the same pattern used by Laravel Blade.

### Key files

| File | Purpose |
|---|---|
| [`src/Template.php`](src/Template.php) | Main class — the only public API surface |
| [`src/ModifierInterface.php`](src/ModifierInterface.php) | Contract for section post-processors (e.g. CSS minifier) |
| [`src/Parser/SectionParserInterface.php`](src/Parser/SectionParserInterface.php) | `compile(string): string` — implemented by both parsers |
| [`src/Parser/ContentParser.php`](src/Parser/ContentParser.php) | Pass 1 — transforms raw HTML into executable PHP for `eval()`; identity `compile()` for non-output sections |
| [`src/Parser/OutputParser.php`](src/Parser/OutputParser.php) | Pass 2 — compiles the `output` section into a PHP template |

### Component file syntax

Comments are used so IDEs can still highlight and format the HTML normally.

```html
<!--load::OtherComponent-->
<!--section::style-->
<style> ... </style>
<!--section::output-->
<div>
  <%$title%>                                      <!-- HTML-escaped echo -->
  <%%$rawHtml%%>                                  <!-- raw echo -->

  <dmv-php>$x = $builder->get('key');</dmv-php>           <!-- PHP block -->

  <dmv-if test="$x > 0">yes<dmv-else>no</dmv-if>
  <dmv-if test="$x === 'admin'">
    admin
  <dmv-elseif test="$x === 'editor'">
    editor
  <dmv-else>
    guest
  </dmv-if>

  <dmv-foreach on="$items as $i => $v">
    <li><%$v%></li>
  </dmv-foreach>

  <dmv-for expr="$i = 1; $i <= 5; $i++">
    <span><%$i%></span>
  </dmv-for>

  <dmv-while test="$n < 10">
    <%$n%><dmv-php>$n++;</dmv-php>
  </dmv-while>

  <dmv-render component="OtherComponent" />
  <dmv-renderSection name="content" />
  <dmv-renderSection name="title" default="My App" />
  <dmv-renderSectionBlock name="style" />
  <dmv-renderSectionBlock name="style" modifier="css" />
</div>
```

Comment directives call `$builder->methodName(arg1, arg2)`:
- `<!--fn::arg-->` → single arg
- `<!--fn::arg1|arg2-->` → two args
- `/*fn::arg*/` → JS-style (useful inside `<script>` blocks)
- `<!---...comment...--->`  → triple-dash, stripped entirely (dev notes)

### Component naming → file path

`PascalCase` names are mapped to lowercase slash-separated paths:
- `PageHeader` → `components/page/header.html`
- `Ui.Button`  → `components/ui/button.html`
- If the path is a directory, `component.html` inside it is used instead.

### load() vs render()

`load()` and `render()` are intentionally separate steps:

- **`load($component)`** — parses the file, registers all sections, auto-loads parent layout if `<!--extends::-->` is present. Produces no output.
- **`render($component)`** — includes the compiled output cache. **Does not call `load()`**. Fails with an error if the component has not been loaded yet.

```php
// Always load before render
$t->load('Page');   // also auto-loads Layout via <!--extends::Layout-->
$t->render('Page'); // renders Layout's output cache
```

**`<!--load::Dep-->` must come before any `<!--section::...-->`** in the same file. `load()` is blocked when a section is open because the dependency's own `section()` calls would auto-close the currently active section, corrupting the parent's buffer. `<dmv-render component="Dep" />` inside a section is safe — the output cache only echoes HTML and never opens new sections.

```html
<!--extends::Layout-->
<!--load::Widgets.Card-->                   ← load all deps first, before any section
<!--section::content-->
<dmv-render component="Widgets.Card" />    ← render inside a section is fine
```

### `ModifierInterface`

Implement `process(array $sections): string` where `$sections` is `['ComponentName' => 'content']`. Register on the `Template` instance via `addModifier(string $key, ModifierInterface)` and reference by key in component files: `<dmv-renderSectionBlock name="style" modifier="css" />`.

### Component execution scoping

Both execution paths expose the same scope to component code:
- `$builder` = the `Template` instance
- any `$data` keys available as local variables (via `extract()` with `EXTR_SKIP`)

**Load phase** (`evalComponent()`): `ContentParser` output is `eval()`'d. Variable names `$builder` and `$data` are the only locals in scope — no `$__` prefixing needed since there is no `include`.

**Render phase** (`executeFile()`): output cache is `include`'d. Variable names `$__file` and `$__data` are deliberately prefixed to prevent collisions with extracted template variables.
