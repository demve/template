# Demeve Template

A lightweight, high-performance PHP template engine that allows you to build component-based applications using a Single File Component (SFC) approach.

## Key Features

- **Single File Components (SFC)**: Define your HTML, CSS, and JS in a single `.php` or `.html` file using `<!--section::name-->` markers.
- **Inheritance & Layouts**: Easily extend layouts with `<!--extends::layout_name-->`.
- **Automatic Dependency Management**: Use `<!--load::component_name-->` to ensure all required components and their assets are loaded.
- **Asset Management**: Automatic copying of assets to the public directory with cache busting.
- **Modifiers & Bundling**: Built-in support for minifying and bundling CSS and JS files for production.
- **High Performance**: Compiles components into optimized PHP cache files.
- **Flexible API**: Pure PHP logic within templates, with a clean and intuitive API.

## Installation

```bash
composer require demeve/template
```

## Basic Usage

### 1. Initialize the Engine

```php
use Demeve\Template\Template;

$t = new Template(
    __DIR__ . '/components', // Directory where components are stored
    __DIR__ . '/cache',      // Directory for compiled cache files
    __DIR__ . '/public',     // (Optional) Public directory for assets
    '/assets'                // (Optional) Public URL prefix for assets
);
```

### 2. Create a Layout (`components/layout.html`)

```html
<!--section::output-->
<!DOCTYPE html>
<html>
<head>
    <title><!--=slot::title|Default Title--></title>
    <!--=block::css-->
</head>
<body>
    <div class="app">
        <!--=slot::content-->
    </div>
    <!--=block::js-->
</body>
</html>
```

### 3. Create a Component (`components/Home.html`)

```html
<!--extends::layout-->
<!--load::Header-->

<!--section::title-->Welcome to Demeve<!--section::title-->

<!--section::content-->
<?= $this->render('Header') ?>
<h1>Home Page</h1>
<p>Hello, world!</p>

<!--section::css-->
<style>
    h1 { color: #333; }
</style>

<!--section::js-->
<script>
    console.log('Home loaded');
</script>
```

### 4. Render the Component

```php
echo $t->render('Home', ['name' => 'John']);
```

## Advanced Features

### Blocks and Slots

- **Slots**: Used for one-time content replacement (like titles or main content). Use `<!--=slot::name-->`.
- **Blocks**: Used for accumulating content from multiple components (like CSS or JS). Use `<!--=block::name-->`.

### Modifiers

You can post-process sections using modifiers. For example, to minify CSS:

```php
use Demeve\Template\Modifier\CssModifier;

$t->addModifier('css', new CssModifier());
```

In your layout:
```html
<!--=block::css|css-->
```

### File Modifiers (Bundling)

For production, you can use file modifiers to combine all CSS/JS into a single minified file:

```php
use Demeve\Template\Modifier\CssFileModifier;

$t->addModifier('css', new CssFileModifier([
    'output_dir' => __DIR__ . '/public/dist',
    'public_url_prefix' => '/dist',
    'read_file' => false // Returns the URL instead of the content
]));
```

In your layout:
```html
<link rel="stylesheet" href="<!--=files::css-->">
```

## Component Resolution

Component names are automatically resolved to file paths (case-insensitive conversion from PascalCase to slash-separated):
- `UserCard` -> `/user/card.php` or `/user/card.html`
- `Admin.Sidebar` -> `/admin/sidebar.php` or `/admin/sidebar.html`
- `Button` -> `/button/component.php` or `/button/component.html` (if it's a directory)

The engine checks for files in the following order:
1. `directory/component.php`
2. `directory/component.html`
3. `file.php`
4. `file.html`

## Template API

Inside your component files, the `Template` instance is available via `$this`. Useful methods include:

- `<?= $this->render('ComponentName', $data) ?>`: Render a sub-component.
- `<?= $this->e($variable) ?>`: Escape HTML output.
- `<?= $this->slot('slotName', 'Default Content') ?>`: Output a slot content.
- `<?= $this->block('blockName') ?>`: Output an accumulated block.
- `<?= $this->asset('path/to/asset.png') ?>`: Handle and link to assets.

## License

MIT
