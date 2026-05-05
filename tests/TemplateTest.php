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

    public function test_set_and_get_params(): void
    {
        $t = $this->makeTemplate();
        $t->set('name', 'World');
        $this->assertSame('World', $t->get('name'));
        $this->assertNull($t->get('missing'));
        $this->assertSame('default', $t->get('missing', 'default'));
    }

    public function test_load_nonexistent_component_adds_error(): void
    {
        $t = $this->makeTemplate();
        $t->load('Ghost');
        $this->assertNotEmpty($t->getErrors());
    }

    public function test_load_inside_open_section_throws(): void
    {
        file_put_contents(
            $this->tmp . '/components/dep.html',
            '<?php $builder->section("output"); ?>dep<?php $builder->sectionStop(); ?>'
        );
        // Simulate a load-cache file that calls load() while a section is open
        file_put_contents(
            $this->tmp . '/components/bad.html',
            '<?php $builder->section("content"); ?><?php $builder->load("Dep"); ?><?php $builder->sectionStop(); ?>'
        );
        $t = $this->makeTemplate();
        $this->expectException(\LogicException::class);
        $t->load('Bad');
    }

    public function test_load_valid_component_is_idempotent(): void
    {
        file_put_contents(
            $this->tmp . '/components/widget.html',
            '<?php $builder->section("output"); ?>hello<?php $builder->sectionStop(); ?>'
        );
        $t = $this->makeTemplate();
        $t->load('Widget');
        $t->load('Widget'); // second call must be a no-op
        $this->assertEmpty($t->getErrors());
    }

    public function test_render_section_returns_default_when_empty(): void
    {
        $t = $this->makeTemplate();
        ob_start();
        $t->renderSection('missing', 'fallback');
        $this->assertSame('fallback', ob_get_clean());
    }

    public function test_render_section_echoes_registered_content(): void
    {
        file_put_contents(
            $this->tmp . '/components/page.html',
            '<?php $builder->section("title"); ?>Hello<?php $builder->sectionStop(); ?>'
            . '<?php $builder->section("output"); ?>body<?php $builder->sectionStop(); ?>'
        );
        $t = $this->makeTemplate();
        $t->load('Page');
        ob_start();
        $t->renderSection('title', 'Default Title');
        $this->assertSame('Hello', trim((string) ob_get_clean()));
    }

    public function test_render_nonexistent_component_returns_null_with_error(): void
    {
        $t = $this->makeTemplate();
        $result = $t->render('NotExisting');
        $this->assertNull($result);
        $this->assertNotEmpty($t->getErrors());
    }

    public function test_render_auto_loads_unloaded_component(): void
    {
        file_put_contents(
            $this->tmp . '/components/widget.html',
            '<?php $builder->section("output"); ?>hello<?php $builder->sectionStop(); ?>'
        );
        $t = $this->makeTemplate();
        // render without prior load — should auto-load and succeed
        $output = trim((string) $t->render('Widget', [], true));
        $this->assertSame('hello', $output);
        $this->assertEmpty($t->getErrors());
    }

    public function test_render_deps_auto_loaded_from_output_section(): void
    {
        // Use HTML syntax so <dmv-render> goes through OutputParser → cache file
        file_put_contents(
            $this->tmp . '/components/badge.html',
            '<!--section::output-->BADGE'
        );
        file_put_contents(
            $this->tmp . '/components/card.html',
            '<!--section::output-->CARD-<dmv-render component="Badge" />'
        );

        $t = $this->makeTemplate();
        $t->load('Card'); // must auto-discover Badge from the compiled output cache
        $output = trim((string) $t->render('Card', [], true));
        $this->assertSame('CARD-BADGE', $output);
        $this->assertEmpty($t->getErrors());
    }

    public function test_render_deps_discovered_transitively(): void
    {
        file_put_contents(
            $this->tmp . '/components/leaf.html',
            '<!--section::output-->LEAF'
        );
        file_put_contents(
            $this->tmp . '/components/mid.html',
            '<!--section::output-->MID-<dmv-render component="Leaf" />'
        );
        file_put_contents(
            $this->tmp . '/components/root.html',
            '<!--section::output-->ROOT-<dmv-render component="Mid" />'
        );

        $t = $this->makeTemplate();
        $t->load('Root');
        $output = trim((string) $t->render('Root', [], true));
        $this->assertSame('ROOT-MID-LEAF', $output);
        $this->assertEmpty($t->getErrors());
    }

    public function test_layout_inheritance_renders_layout_output(): void
    {
        // Layout: wraps content slot in a <main> tag
        file_put_contents(
            $this->tmp . '/components/layout.html',
            '<?php $builder->section("output"); ?>'
            . '<main><?php $builder->renderSection("content"); ?></main>'
            . '<?php $builder->sectionStop(); ?>'
        );

        // Page: extends Layout, fills content slot
        file_put_contents(
            $this->tmp . '/components/page.html',
            '<?php $builder->extends("Layout"); ?>'
            . '<?php $builder->section("content"); ?>Hello World<?php $builder->sectionStop(); ?>'
        );

        $t = $this->makeTemplate();
        $t->load('Page');
        $output = trim((string) $t->render('Page', [], true));
        $this->assertSame('<main>Hello World</main>', $output);
    }

    public function test_layout_inheritance_title_slot_with_default(): void
    {
        file_put_contents(
            $this->tmp . '/components/base.html',
            '<?php $builder->section("output"); ?>'
            . '<title><?php $builder->renderSection("title", "My App"); ?></title>'
            . '<?php $builder->sectionStop(); ?>'
        );
        file_put_contents(
            $this->tmp . '/components/about.html',
            '<?php $builder->extends("Base"); ?>'
            // no title section — should fall back to default
        );

        $t = $this->makeTemplate();
        $t->load('About');
        $output = trim((string) $t->render('About', [], true));
        $this->assertSame('<title>My App</title>', $output);
    }

    public function test_render_section_block_implodes_without_modifier(): void
    {
        file_put_contents(
            $this->tmp . '/components/a.html',
            '<?php $builder->section("style"); ?>a<?php $builder->sectionStop(); ?>'
        );
        file_put_contents(
            $this->tmp . '/components/b.html',
            '<?php $builder->section("style"); ?>b<?php $builder->sectionStop(); ?>'
        );
        $t = $this->makeTemplate();
        $t->load('A');
        $t->load('B');
        ob_start();
        $t->renderSectionBlock('style');
        $this->assertSame("a\nb", trim((string) ob_get_clean()));
    }

    public function test_render_section_block_calls_registered_modifier(): void
    {
        file_put_contents(
            $this->tmp . '/components/widget.html',
            '<?php $builder->section("style"); ?>  raw css  <?php $builder->sectionStop(); ?>'
        );

        $modifier = new class implements ModifierInterface {
            public function process(array $sections): string
            {
                return strtoupper(implode('|', $sections));
            }
        };

        $t = $this->makeTemplate();
        $t->addModifier('upper', $modifier);
        $t->load('Widget');

        ob_start();
        $t->renderSectionBlock('style', 'upper');
        $this->assertSame('RAW CSS', (string) ob_get_clean()); // sectionStop() trims content
    }

    public function test_render_section_block_falls_back_to_implode_for_unknown_key(): void
    {
        file_put_contents(
            $this->tmp . '/components/widget.html',
            '<?php $builder->section("style"); ?>css<?php $builder->sectionStop(); ?>'
        );
        $t = $this->makeTemplate();
        $t->load('Widget');
        ob_start();
        $t->renderSectionBlock('style', 'nonexistent');
        $this->assertSame('css', trim((string) ob_get_clean()));
    }

    public function test_render_section_files_implodes_without_modifier(): void
    {
        file_put_contents(
            $this->tmp . '/components/a.html',
            '<?php $builder->section("style"); ?>a<?php $builder->sectionStop(); ?>'
        );
        file_put_contents(
            $this->tmp . '/components/b.html',
            '<?php $builder->section("style"); ?>b<?php $builder->sectionStop(); ?>'
        );
        $t = $this->makeTemplate();
        $t->load('A');
        $t->load('B');
        ob_start();
        $t->renderSectionFiles('style');
        $this->assertSame("a\nb", trim((string) ob_get_clean()));
    }

    public function test_render_section_files_calls_file_modifier_with_paths(): void
    {
        file_put_contents(
            $this->tmp . '/components/widget.html',
            '<?php $builder->section("style"); ?>css<?php $builder->sectionStop(); ?>'
        );

        $receivedPaths = [];
        $modifier = new class ($receivedPaths) implements FileModifierInterface {
            public function __construct(private array &$captured) {}
            public function processFiles(array $files): string
            {
                $this->captured = $files;
                return 'FILE_MODIFIER_OUTPUT';
            }
        };

        $t = $this->makeTemplate();
        $t->addModifier('fm', $modifier);
        $t->load('Widget');

        ob_start();
        $t->renderSectionFiles('style', 'fm');
        $output = (string) ob_get_clean();

        $this->assertSame('FILE_MODIFIER_OUTPUT', $output);
        $this->assertArrayHasKey('Widget', $receivedPaths);
        $this->assertStringEndsWith('.cache.php', $receivedPaths['Widget']);
        $this->assertFileExists($receivedPaths['Widget']);
    }

    public function test_render_section_files_falls_back_to_modifier_interface(): void
    {
        file_put_contents(
            $this->tmp . '/components/widget.html',
            '<?php $builder->section("style"); ?>  raw  <?php $builder->sectionStop(); ?>'
        );

        $modifier = new class implements ModifierInterface {
            public function process(array $sections): string
            {
                return strtoupper(implode('|', $sections));
            }
        };

        $t = $this->makeTemplate();
        $t->addModifier('upper', $modifier);
        $t->load('Widget');

        ob_start();
        $t->renderSectionFiles('style', 'upper');
        $this->assertSame('RAW', (string) ob_get_clean());
    }

    public function test_render_section_files_skips_unknown_modifier_key(): void
    {
        file_put_contents(
            $this->tmp . '/components/widget.html',
            '<?php $builder->section("style"); ?>x<?php $builder->sectionStop(); ?>'
        );
        $t = $this->makeTemplate();
        $t->load('Widget');
        ob_start();
        $t->renderSectionFiles('style', 'nonexistent');
        $this->assertSame('x', trim((string) ob_get_clean()));
    }

    // -------------------------------------------------------------------------
    // dmv-render: variable component + data attribute
    // -------------------------------------------------------------------------

    public function test_dmv_render_with_variable_component_name(): void
    {
        file_put_contents(
            $this->tmp . '/components/block.html',
            '<!--section::output-->BLOCK'
        );
        file_put_contents(
            $this->tmp . '/components/page.html',
            '<!--section::output--><dmv-render component="$comp" />'
        );

        $t = $this->makeTemplate();
        $output = trim((string) $t->render('Page', ['comp' => 'Block'], true));
        $this->assertSame('BLOCK', $output);
        $this->assertEmpty($t->getErrors());
    }

    public function test_dmv_render_passes_data_attribute_to_component(): void
    {
        file_put_contents(
            $this->tmp . '/components/item.html',
            '<!--section::output--><%$label%>'
        );
        file_put_contents(
            $this->tmp . '/components/list.html',
            '<!--section::output--><dmv-render component="Item" data="$itemData" />'
        );

        $t = $this->makeTemplate();
        $output = trim((string) $t->render('List', ['itemData' => ['label' => 'hello']], true));
        $this->assertSame('hello', $output);
        $this->assertEmpty($t->getErrors());
    }

    // -------------------------------------------------------------------------
    // inject() / deferred section blocks (CMS blocks pattern)
    // -------------------------------------------------------------------------

    public function test_dynamic_component_styles_visible_after_inject(): void
    {
        // Page uses HTML dmv-syntax so the foreach and renderSectionBlock are compiled
        // to PHP by OutputParser and execute at render-time (not baked in at load-time).
        // renderSectionBlock runs at depth=1 → emits placeholder.
        // foreach renders blocks dynamically → their style sections accumulate.
        // inject() replaces placeholder with all collected styles.
        file_put_contents(
            $this->tmp . '/components/page.html',
            '<!--section::output-->'
            . '<dmv-renderSectionBlock name="style" />'
            . '<dmv-foreach on="$bloques as $b">'
            . '<dmv-render component="$b" />'
            . '</dmv-foreach>'
        );

        file_put_contents(
            $this->tmp . '/components/blocka.html',
            '<!--section::style-->.a{color:red}'
            . '<!--section::output--><div class="a">A</div>'
        );
        file_put_contents(
            $this->tmp . '/components/blockb.html',
            '<!--section::style-->.b{color:blue}'
            . '<!--section::output--><div class="b">B</div>'
        );

        $t = $this->makeTemplate();
        $output = (string) $t->render('Page', ['bloques' => ['Blocka', 'Blockb']], true);

        $this->assertStringContainsString('.a{color:red}', $output);
        $this->assertStringContainsString('.b{color:blue}', $output);
        $this->assertStringNotContainsString('__DMV:', $output);
        $this->assertEmpty($t->getErrors());
    }

    public function test_repeated_render_section_block_deduplicates_queue(): void
    {
        // Page calls renderSectionBlock('style') twice via HTML syntax.
        // Both emit the SAME placeholder key → one sectionQueue entry → str_replace replaces both.
        file_put_contents(
            $this->tmp . '/components/page.html',
            '<!--section::style-->body{}'
            . '<!--section::output-->'
            . '<dmv-renderSectionBlock name="style" />'
            . '<p>mid</p>'
            . '<dmv-renderSectionBlock name="style" />'
        );

        $t = $this->makeTemplate();
        $output = (string) $t->render('Page', [], true);

        $this->assertSame(2, substr_count($output, 'body{}'));
        $this->assertStringNotContainsString('__DMV:', $output);
    }

    public function test_inject_is_noop_when_no_placeholders(): void
    {
        $t = $this->makeTemplate();
        $this->assertSame('<p>hello</p>', $t->inject('<p>hello</p>'));
    }

    // -------------------------------------------------------------------------

    private function makeTemplate(): Template
    {
        return new Template(
            $this->tmp . '/components',
            $this->tmp . '/cache'
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
