<?php

declare(strict_types=1);

namespace Demeve\Template\Tests\Parser;

use Demeve\Template\Parser\ContentParser;
use PHPUnit\Framework\TestCase;

class ContentParserTest extends TestCase
{
    private ContentParser $parser;
    private string $tmp;

    protected function setUp(): void
    {
        $this->parser = new ContentParser();
        $this->tmp    = sys_get_temp_dir() . '/dmv_cp_test_' . uniqid();
        mkdir($this->tmp, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmp . '/*') ?: [] as $f) {
            unlink($f);
        }
        rmdir($this->tmp);
    }

    public function test_returns_empty_metadata_for_plain_source(): void
    {
        $meta = $this->parser->parse('<!--section::output-->hello', 'MyComp', $this->tmp);
        $this->assertNull($meta['extends']);
        $this->assertSame([], $meta['deps']);
    }

    public function test_writes_section_cache_file(): void
    {
        $this->parser->parse('<!--section::output-->hello', 'MyComp', $this->tmp);
        $file = $this->tmp . '/MyComp.output.cache.php';
        $this->assertFileExists($file);
        $this->assertSame('hello', file_get_contents($file));
    }

    public function test_writes_multiple_section_cache_files(): void
    {
        $source = '<!--section::style-->.a{color:red}<!--section::output--><div>hi</div>';
        $this->parser->parse($source, 'Comp', $this->tmp);

        $this->assertSame('.a{color:red}', file_get_contents($this->tmp . '/Comp.style.cache.php'));
        $this->assertSame('<div>hi</div>', file_get_contents($this->tmp . '/Comp.output.cache.php'));
    }

    public function test_extracts_extends_directive(): void
    {
        $meta = $this->parser->parse('<!--extends::Layouts.App--><!--section::output-->x', 'Page', $this->tmp);
        $this->assertSame('Layouts.App', $meta['extends']);
    }

    public function test_extracts_load_directives(): void
    {
        $source = '<!--load::Widgets.Card--><!--load::Ui.Button--><!--section::output-->x';
        $meta   = $this->parser->parse($source, 'Page', $this->tmp);
        $this->assertSame(['Widgets.Card', 'Ui.Button'], $meta['deps']);
    }

    public function test_strips_triple_dash_dev_comments(): void
    {
        $source = '<!--section::output-->before<!--- dev note --->after';
        $this->parser->parse($source, 'C', $this->tmp);
        $this->assertSame('beforeafter', file_get_contents($this->tmp . '/C.output.cache.php'));
    }

    public function test_transforms_dmv_template_tag(): void
    {
        $source = '<!--section::output--><dmv-template>foo</dmv-template>';
        $this->parser->parse($source, 'MyComp', $this->tmp);
        $content = (string) file_get_contents($this->tmp . '/MyComp.output.cache.php');
        $this->assertStringContainsString("<script type='text/template' id='template-MyComp'", $content);
        $this->assertStringContainsString('</script>', $content);
        $this->assertStringNotContainsString('<dmv-template>', $content);
    }

    public function test_replaces_self_placeholder(): void
    {
        $source = '<!--section::output-->id="__self"';
        $this->parser->parse($source, 'MyComp', $this->tmp);
        $content = (string) file_get_contents($this->tmp . '/MyComp.output.cache.php');
        $this->assertStringContainsString('id="MyComp"', $content);
    }

    public function test_section_content_is_trimmed(): void
    {
        $source = '<!--section::style-->   .a { }   <!--section::output-->  hello  ';
        $this->parser->parse($source, 'C', $this->tmp);
        $this->assertSame('.a { }', file_get_contents($this->tmp . '/C.style.cache.php'));
        $this->assertSame('hello', file_get_contents($this->tmp . '/C.output.cache.php'));
    }

    public function test_no_section_markers_writes_no_cache_files(): void
    {
        $meta = $this->parser->parse('<!--extends::Layout--><!--load::Widget-->', 'Page', $this->tmp);
        $this->assertEmpty(glob($this->tmp . '/Page.*.cache.php'));
        $this->assertSame('Layout', $meta['extends']);
        $this->assertSame(['Widget'], $meta['deps']);
    }
}
