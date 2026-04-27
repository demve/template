<?php

declare(strict_types=1);

namespace Demeve\Template\Tests;

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
