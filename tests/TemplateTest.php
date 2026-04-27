<?php

declare(strict_types=1);

namespace Demeve\Template\Tests;

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
