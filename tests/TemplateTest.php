<?php

declare(strict_types=1);

namespace Demeve\Template\Tests;

use Demeve\Template\FileModifierInterface;
use Demeve\Template\ModifierInterface;
use Demeve\Template\Template;
use PHPUnit\Framework\TestCase;

class TemplateTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/demeve_template_test_' . uniqid();
        mkdir($this->tmp . '/components', 0777, true);
        mkdir($this->tmp . '/cache',      0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmp);
    }

    // -------------------------------------------------------------------------
    // Parameters
    // -------------------------------------------------------------------------

    public function test_set_and_get_params(): void
    {
        $t = $this->makeTemplate();
        $t->set('name', 'World');
        $this->assertSame('World', $t->get('name'));
        $this->assertNull($t->get('missing'));
        $this->assertSame('default', $t->get('missing', 'default'));
    }

    // -------------------------------------------------------------------------
    // load()
    // -------------------------------------------------------------------------

    public function test_load_nonexistent_component_adds_error(): void
    {
        $t = $this->makeTemplate();
        $t->load('Ghost');
        $this->assertNotEmpty($t->errors());
    }

    public function test_load_valid_component_is_idempotent(): void
    {
        $this->writeComponent('widget', '<!--section::output-->hello');
        $t = $this->makeTemplate();
        $t->load('Widget');
        $t->load('Widget'); // second call must be a no-op
        $this->assertEmpty($t->errors());
    }

    // -------------------------------------------------------------------------
    // slot()
    // -------------------------------------------------------------------------

    public function test_slot_returns_default_when_section_not_registered(): void
    {
        $t = $this->makeTemplate();
        $this->assertSame('fallback', $t->slot('missing', 'fallback'));
    }

    public function test_slot_returns_registered_content(): void
    {
        $this->writeComponent('page', '<!--section::title-->Hello<!--section::output-->body');
        $t = $this->makeTemplate();
        $t->load('Page');
        $this->assertSame('Hello', $t->slot('title', 'Default Title'));
    }

    // -------------------------------------------------------------------------
    // render()
    // -------------------------------------------------------------------------

    public function test_render_nonexistent_component_returns_empty_string_with_error(): void
    {
        $t = $this->makeTemplate();
        $this->assertSame('', $t->render('NotExisting'));
        $this->assertNotEmpty($t->errors());
    }

    public function test_render_auto_loads_unloaded_component(): void
    {
        $this->writeComponent('widget', '<!--section::output-->hello');
        $t = $this->makeTemplate();
        $this->assertSame('hello', trim($t->render('Widget')));
        $this->assertEmpty($t->errors());
    }

    public function test_render_returns_string(): void
    {
        $this->writeComponent('widget', '<!--section::output-->hi');
        $t = $this->makeTemplate();
        $this->assertIsString($t->render('Widget'));
    }

    // -------------------------------------------------------------------------
    // Dependency auto-discovery
    // -------------------------------------------------------------------------

    public function test_render_deps_auto_loaded_from_output_section(): void
    {
        $this->writeComponent('badge', '<!--section::output-->BADGE');
        $this->writeComponent('card',  '<!--section::output-->CARD-<?= $this->render(\'Badge\') ?>');

        $t = $this->makeTemplate();
        $t->load('Card');
        $this->assertSame('CARD-BADGE', trim($t->render('Card')));
        $this->assertEmpty($t->errors());
    }

    public function test_render_deps_discovered_transitively(): void
    {
        $this->writeComponent('leaf', '<!--section::output-->LEAF');
        $this->writeComponent('mid',  '<!--section::output-->MID-<?= $this->render(\'Leaf\') ?>');
        $this->writeComponent('root', '<!--section::output-->ROOT-<?= $this->render(\'Mid\') ?>');

        $t = $this->makeTemplate();
        $t->load('Root');
        $this->assertSame('ROOT-MID-LEAF', trim($t->render('Root')));
        $this->assertEmpty($t->errors());
    }

    // -------------------------------------------------------------------------
    // Layout inheritance
    // -------------------------------------------------------------------------

    public function test_layout_inheritance_renders_layout_output(): void
    {
        $this->writeComponent('layout', '<!--section::output--><main><?= $this->slot(\'content\') ?></main>');
        $this->writeComponent('page',   '<!--extends::Layout--><!--section::content-->Hello World');

        $t = $this->makeTemplate();
        $t->load('Page');
        $this->assertSame('<main>Hello World</main>', trim($t->render('Page')));
    }

    public function test_layout_inheritance_title_slot_with_default(): void
    {
        $this->writeComponent('base',  '<!--section::output--><title><?= $this->slot(\'title\', \'My App\') ?></title>');
        $this->writeComponent('about', '<!--extends::Base-->');

        $t = $this->makeTemplate();
        $t->load('About');
        $this->assertSame('<title>My App</title>', trim($t->render('About')));
    }

    // -------------------------------------------------------------------------
    // block() — deferred multi-section emit
    // -------------------------------------------------------------------------

    public function test_block_implodes_sections_without_modifier(): void
    {
        $this->writeComponent('a', '<!--section::style-->a');
        $this->writeComponent('b', '<!--section::style-->b');
        $t = $this->makeTemplate();
        $t->load('A');
        $t->load('B');
        // block() is deferred; call inject() to resolve
        $html = $t->inject($t->block('style'));
        $this->assertSame("a\nb", trim($html));
    }

    public function test_block_calls_registered_modifier(): void
    {
        $this->writeComponent('widget', '<!--section::style-->  raw css  ');

        $modifier = new class implements ModifierInterface {
            public function process(array $sections): string
            {
                return strtoupper(implode('|', $sections));
            }
        };

        $t = $this->makeTemplate();
        $t->addModifier('upper', $modifier);
        $t->load('Widget');

        $html = $t->inject($t->block('style', 'upper'));
        $this->assertSame('RAW CSS', $html);
    }

    public function test_block_falls_back_to_implode_for_unknown_modifier_key(): void
    {
        $this->writeComponent('widget', '<!--section::style-->css');
        $t = $this->makeTemplate();
        $t->load('Widget');
        $html = $t->inject($t->block('style', 'nonexistent'));
        $this->assertSame('css', trim($html));
    }

    // -------------------------------------------------------------------------
    // blockFiles() — file-path-based emit
    // -------------------------------------------------------------------------

    public function test_block_files_implodes_sections_without_modifier(): void
    {
        $this->writeComponent('a', '<!--section::style-->a');
        $this->writeComponent('b', '<!--section::style-->b');
        $t = $this->makeTemplate();
        $t->load('A');
        $t->load('B');
        $html = $t->inject($t->blockFiles('style'));
        $this->assertSame("a\nb", trim($html));
    }

    public function test_block_files_calls_file_modifier_with_paths(): void
    {
        $this->writeComponent('widget', '<!--section::style-->css');

        $receivedPaths = [];
        $modifier = new class ($receivedPaths) implements FileModifierInterface {
            public function __construct(private array &$captured) {}
            public function processFiles(array $files, Template $template): string
            {
                $this->captured = $files;
                return 'FILE_MODIFIER_OUTPUT';
            }
        };

        $t = $this->makeTemplate();
        $t->addModifier('fm', $modifier);
        $t->load('Widget');

        $html = $t->inject($t->blockFiles('style', 'fm'));
        $this->assertSame('FILE_MODIFIER_OUTPUT', $html);
        $this->assertArrayHasKey('Widget', $receivedPaths);
        $this->assertStringEndsWith('.cache.php', $receivedPaths['Widget']);
        $this->assertFileExists($receivedPaths['Widget']);
    }

    public function test_block_files_falls_back_to_modifier_interface(): void
    {
        $this->writeComponent('widget', '<!--section::style-->  raw  ');

        $modifier = new class implements ModifierInterface {
            public function process(array $sections): string
            {
                return strtoupper(implode('|', $sections));
            }
        };

        $t = $this->makeTemplate();
        $t->addModifier('upper', $modifier);
        $t->load('Widget');

        $html = $t->inject($t->blockFiles('style', 'upper'));
        $this->assertSame('RAW', $html);
    }

    public function test_block_files_skips_unknown_modifier_key(): void
    {
        $this->writeComponent('widget', '<!--section::style-->x');
        $t = $this->makeTemplate();
        $t->load('Widget');
        $html = $t->inject($t->blockFiles('style', 'nonexistent'));
        $this->assertSame('x', trim($html));
    }

    // -------------------------------------------------------------------------
    // e() — HTML escaping
    // -------------------------------------------------------------------------

    public function test_e_escapes_html_special_chars(): void
    {
        $t = $this->makeTemplate();
        $this->assertSame('&lt;script&gt;', $t->e('<script>'));
        $this->assertSame('&amp;', $t->e('&'));
        $this->assertSame('&quot;', $t->e('"'));
    }

    public function test_e_handles_null(): void
    {
        $t = $this->makeTemplate();
        $this->assertSame('', $t->e(null));
    }

    // -------------------------------------------------------------------------
    // render() with variable component name
    // -------------------------------------------------------------------------

    public function test_render_with_variable_component_name(): void
    {
        $this->writeComponent('block', '<!--section::output-->BLOCK');
        $this->writeComponent('page',  '<!--section::output--><?= $this->render($comp) ?>');

        $t = $this->makeTemplate();
        $this->assertSame('BLOCK', trim($t->render('Page', ['comp' => 'Block'])));
        $this->assertEmpty($t->errors());
    }

    public function test_render_passes_data_to_component(): void
    {
        $this->writeComponent('item', '<!--section::output--><?= $this->e($label) ?>');
        $this->writeComponent('list', '<!--section::output--><?= $this->render(\'Item\', $itemData) ?>');

        $t = $this->makeTemplate();
        $this->assertSame('hello', trim($t->render('List', ['itemData' => ['label' => 'hello']])));
        $this->assertEmpty($t->errors());
    }

    // -------------------------------------------------------------------------
    // inject() / deferred block (CMS blocks pattern)
    // -------------------------------------------------------------------------

    public function test_dynamic_component_styles_visible_after_inject(): void
    {
        $this->writeComponent('page', implode('', [
            '<!--section::output-->',
            '<?= $this->block(\'style\') ?>',
            '<?php foreach ($bloques as $b): ?>',
            '<?= $this->render($b) ?>',
            '<?php endforeach; ?>',
        ]));
        $this->writeComponent('blocka', '<!--section::style-->.a{color:red}<!--section::output--><div class="a">A</div>');
        $this->writeComponent('blockb', '<!--section::style-->.b{color:blue}<!--section::output--><div class="b">B</div>');

        $t      = $this->makeTemplate();
        $output = $t->render('Page', ['bloques' => ['Blocka', 'Blockb']]);

        $this->assertStringContainsString('.a{color:red}', $output);
        $this->assertStringContainsString('.b{color:blue}', $output);
        $this->assertStringNotContainsString('__DMV:', $output);
        $this->assertEmpty($t->errors());
    }

    public function test_repeated_block_call_deduplicates_placeholder_but_str_replaces_all(): void
    {
        $this->writeComponent('page', implode('', [
            '<!--section::style-->body{}',
            '<!--section::output-->',
            '<?= $this->block(\'style\') ?>',
            '<p>mid</p>',
            '<?= $this->block(\'style\') ?>',
        ]));

        $t      = $this->makeTemplate();
        $output = $t->render('Page');

        $this->assertSame(2, substr_count($output, 'body{}'));
        $this->assertStringNotContainsString('__DMV:', $output);
    }

    public function test_inject_is_noop_when_no_placeholders(): void
    {
        $t = $this->makeTemplate();
        $this->assertSame('<p>hello</p>', $t->inject('<p>hello</p>'));
    }

    public function test_is_rendering_resets_after_render(): void
    {
        $this->writeComponent('widget', '<!--section::output-->hi');
        $t = $this->makeTemplate();
        $t->render('Widget');
        // Second render must resolve placeholders (isRendering back to false)
        $this->assertSame('hi', trim($t->render('Widget')));
    }

    public function test_print_method_echoes_escaped_param(): void
    {
        $t = $this->makeTemplate();
        $t->set('name', '<b>World</b>');
        ob_start();
        $t->print('name');
        $this->assertSame('&lt;b&gt;World&lt;/b&gt;', ob_get_clean());
    }

    public function test_content_parser_transforms_comment_directives(): void
    {
        $this->writeComponent('widget', '<!--section::output--><!--print::name-->');
        $t = $this->makeTemplate();
        $t->set('name', 'World');
        $this->assertSame('World', trim($t->render('Widget')));
    }

    public function test_content_parser_transforms_multi_arg_directives(): void
    {
        $this->writeComponent('layout', '<!--section::output--><!--=slot::content|default-->');
        $t = $this->makeTemplate();
        $this->assertSame('default', trim($t->render('Layout')));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function writeComponent(string $name, string $content): void
    {
        file_put_contents($this->tmp . '/components/' . $name . '.php', $content);
    }

    private function makeTemplate(): Template
    {
        return new Template(
            path:  $this->tmp . '/components',
            cache: $this->tmp . '/cache'
        );
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
