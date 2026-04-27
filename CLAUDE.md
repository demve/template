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

### Two-cache pipeline

Loading a component writes **two** cache files:

| Cache file | When rebuilt | Contains |
|---|---|---|
| `{component}.load.cache.php` | Source file newer | Raw component HTML with directives replaced by PHP calls |
| `{component}.output.cache.php` | Load cache newer | Compiled PHP template for the `output` section |

`render()` always calls `load()` first (idempotent), then `include`s the output cache. This is the same pattern used by Laravel Blade.

### Key files

| File | Purpose |
|---|---|
| [`src/Template.php`](src/Template.php) | Main class — the only public API surface |
| [`src/ModifierInterface.php`](src/ModifierInterface.php) | Contract for section post-processors (e.g. CSS minifier) |
| [`src/Parser/ContentParser.php`](src/Parser/ContentParser.php) | Pass 1 — transforms raw HTML into executable PHP (load cache) |
| [`src/Parser/OutputParser.php`](src/Parser/OutputParser.php) | Pass 2 — compiles the `output` section into a PHP template (output cache) |

### Component file syntax

Comments are used so IDEs can still highlight and format the HTML normally.

```html
<!--load::OtherComponent-->
<!--section::style-->
<style> ... </style>
<!--section::output-->
<div>
  <%$title%>              <!-- HTML-escaped echo -->
  <%%$rawHtml%%>          <!-- raw echo -->
  @echo($value)           <!-- HTML-escaped echo (alternate) -->
  @render('OtherComponent')
  @renderSection('style')
  @foreach($items as $i) { <li><%$i%></li> }@endforeach
  @php $x = 1; @endphp
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

### `ModifierInterface`

Implement `process(string $content): string` to transform a section's combined output (e.g. minify CSS). Pass an instance as the second argument to `renderSection()`.

### `executeFile()` scoping

Cache files are included inside `executeFile()` where:
- `$builder` = the `Template` instance
- any `$data` keys are available as local variables (via `extract()` with `EXTR_SKIP`)

Variable names `$__file` and `$__data` are deliberately prefixed to prevent collisions with extracted template variables.
